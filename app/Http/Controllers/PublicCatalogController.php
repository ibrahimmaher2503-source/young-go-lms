<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalog\AgeBandResolver;
use App\Domain\Catalog\Models\AgeBand;
use App\Domain\Catalog\Queries\CatalogQuery;
use App\Domain\Identity\ActingLearner;
use App\Models\Category;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PublicCatalogController
{
    public function __invoke(Request $request, CatalogQuery $catalog, ActingLearner $acting, AgeBandResolver $bands): View
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
        ]), filled(...));

        $learner = $acting->current();
        $learnerBand = $bands->resolve($learner?->birth_year, $learner?->birth_month);
        $configuredBands = AgeBand::query()->orderBy('sort')->orderBy('id')->get();
        $band = ($filters['age_band'] ?? null) === 'learner'
            ? $learnerBand
            : $configuredBands->find($filters['age_band'] ?? null);
        $options = $catalog->published();
        $levels = (clone $options)->whereNotNull('level')->distinct()->orderBy('level')->pluck('level')->all();
        $languages = (clone $options)->whereNotNull('content_language')->distinct()->orderBy('content_language')->pluck('content_language')->all();

        return view('catalog.index', [
            'courses' => $catalog->matching($filters, $band)->paginate(12)->appends($filters),
            'filters' => $filters,
            'options' => [
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
            ],
        ]);
    }
}
