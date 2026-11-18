<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalog\AgeBandResolver;
use App\Domain\Catalog\Models\AgeBand;
use App\Domain\Catalog\Queries\CatalogQuery;
use App\Domain\Cohorts\CohortAvailability;
use App\Domain\Commerce\Queries\CheckoutOffers;
use App\Domain\Identity\ActingLearner;
use App\Domain\Seo\CatalogSeo;
use App\Domain\Shared\Markets\Market;
use App\Models\Category;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PublicCatalogController
{
    public function __invoke(Request $request, CatalogQuery $catalog, ActingLearner $acting, AgeBandResolver $bands, CheckoutOffers $offers): View
    {
        $filters = array_filter($request->validate([
            'q' => ['nullable', 'string', 'max:160'],
            'category' => ['nullable', 'integer', 'min:1'],
            'age' => ['nullable', 'integer', 'min:0'],
            'age_band' => ['nullable', 'regex:/^(?:learner|[1-9][0-9]*)$/D'],
            'level' => ['nullable', 'string', 'max:80'],
            'format' => ['nullable', Rule::in(['recorded', 'live', 'hybrid'])],
            'language' => ['nullable', 'string', 'max:16'],
            'instructor' => ['nullable', 'integer', 'min:1'],
            'access' => ['nullable', Rule::in(['free', 'paid', 'subscription'])],
            'price_min' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'price_max' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'availability' => ['nullable', Rule::in(CohortAvailability::filters())],
            'sort' => ['nullable', Rule::in(CatalogQuery::sorts())],
        ]), filled(...));

        if (isset($filters['price_min'], $filters['price_max']) && (float) $filters['price_min'] > (float) $filters['price_max']) {
            [$filters['price_min'], $filters['price_max']] = [$filters['price_max'], $filters['price_min']];
        }
        if (($filters['sort'] ?? null) === CatalogQuery::SORT_NEWEST) {
            unset($filters['sort']);
        }

        $learner = $acting->current();
        $learnerBand = $bands->resolve($learner?->birth_year, $learner?->birth_month);
        $configuredBands = AgeBand::query()->orderBy('sort')->orderBy('id')->get();
        $band = ($filters['age_band'] ?? null) === 'learner'
            ? $learnerBand
            : $configuredBands->find($filters['age_band'] ?? null);
        $options = $catalog->published();
        $levels = (clone $options)->whereNotNull('level')->distinct()->orderBy('level')->pluck('level')->all();
        $languages = (clone $options)->whereNotNull('content_language')->distinct()->orderBy('content_language')->pluck('content_language')->all();

        $courses = $catalog->matching($filters, $band)->paginate(12)->appends($filters);
        $choices = [
            'category' => Category::query()->where('status', 'active')->orderBy('sort')->orderBy('id')->get()
                ->mapWithKeys(static fn (Category $category): array => [$category->id => $category->name])->all(),
            'age_band' => ($learnerBand === null ? [] : ['learner' => __('catalog.learner_band')])
                + $configuredBands->mapWithKeys(static fn (AgeBand $band): array => [$band->id => $band->label])->all(),
            'level' => array_combine($levels, array_map(static fn (string $level): string => trans()->has('catalog.'.$level) ? __('catalog.'.$level) : $level, $levels)),
            'format' => ['recorded' => __('courses.recorded'), 'live' => __('courses.live'), 'hybrid' => __('courses.hybrid')],
            'language' => array_combine($languages, array_map(static fn (string $language): string => trans()->has('catalog.'.$language) ? __('catalog.'.$language) : $language, $languages)),
            'instructor' => User::query()->where('status', 'active')->whereIn('id', $options
                ->join('course_instructors as catalog_assignments', 'catalog_assignments.course_id', '=', 'courses.id')
                ->select('catalog_assignments.instructor_id'))->orderBy('name')->pluck('name', 'id')->all(),
            'access' => ['free' => __('courses.free'), 'paid' => __('courses.paid'), 'subscription' => __('catalog.subscription')],
            'availability' => array_combine(CohortAvailability::filters(), array_map(static fn (string $key): string => __('catalog.avail_'.$key), CohortAvailability::filters())),
        ];
        $sorts = array_combine(CatalogQuery::sorts(), array_map(static fn (string $key): string => __('catalog.sort_'.$key), CatalogQuery::sorts()));

        return view('catalog.index', [
            'courses' => $courses,
            'seo' => CatalogSeo::for($courses, $filters),
            'market' => Market::Egypt,
            'prices' => $offers->coursePrices($courses->items(), Market::Egypt),
            'availability' => CohortAvailability::forCourses($courses->items()),
            'active' => $this->activeFilters($filters, $choices, $sorts, Market::Egypt),
            'filters' => $filters,
            'options' => $choices,
            'sorts' => $sorts,
        ]);
    }

    /**
     * One removable chip per active filter, so a visitor sees why a list is short and can drop one filter at a time.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<string, array<int|string, string>>  $choices
     * @param  array<string, string>  $sorts
     * @return list<array{name: string, label: string, url: string}>
     */
    private function activeFilters(array $filters, array $choices, array $sorts, Market $market): array
    {
        $chips = [];

        foreach ($filters as $name => $value) {
            $label = match (true) {
                $name === 'q' => __('catalog.search_for', ['value' => $value]),
                $name === 'age' => __('catalog.age').': '.$value,
                $name === 'price_min' => __('catalog.price_from', ['value' => $value.' '.$market->currency()]),
                $name === 'price_max' => __('catalog.price_to', ['value' => $value.' '.$market->currency()]),
                $name === 'sort' => __('catalog.sort').': '.($sorts[$value] ?? $value),
                default => __('catalog.'.$name).': '.($choices[$name][$value] ?? $value),
            };
            $chips[] = ['name' => $name, 'label' => (string) $label, 'url' => route('catalog.index', array_diff_key($filters, [$name => true]))];
        }

        return $chips;
    }
}
