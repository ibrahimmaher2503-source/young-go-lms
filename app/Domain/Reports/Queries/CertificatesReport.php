<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

use App\Domain\Reports\ReportDefinition;
use App\Domain\Reports\ReportFilters;
use App\Domain\Reports\ReportKey;
use App\Domain\Reports\ReportLabels;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Certificates issued, one row per certificate with `issued_at` in the range. The certificate number identifies it; the learner's
 * name, the verification token and the file are NOT exported. A revoked certificate stays listed with status revoked.
 */
final class CertificatesReport implements ReportDefinition
{
    public function key(): ReportKey
    {
        return ReportKey::Certificates;
    }

    public function columns(): array
    {
        return ['number' => 'text', 'course' => 'text', 'issued_at' => 'datetime', 'completed_on' => 'text', 'status' => 'text', 'revoked_at' => 'datetime'];
    }

    public function rows(ReportFilters $filters): iterable
    {
        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';
        foreach ($this->query($filters)->orderBy('certificates.id')->lazyById(Config::integer('reports.chunk'), 'certificates.id', 'id') as $certificate) {
            $title = (string) ($certificate->{'course_title_'.$locale} ?? $certificate->course_title_ar ?? $certificate->course_title_en ?? '');

            yield [
                (string) $certificate->number, $title, ReportLabels::datetime((string) $certificate->issued_at), substr((string) $certificate->completed_on, 0, 10),
                ReportLabels::status($certificate->revoked_at === null ? 'active' : 'revoked'), ReportLabels::datetime($certificate->revoked_at === null ? null : (string) $certificate->revoked_at),
            ];
        }
    }

    public function count(ReportFilters $filters): int
    {
        return $this->query($filters)->count();
    }

    public function totals(ReportFilters $filters): array
    {
        $revoked = $this->query($filters)->whereNotNull('certificates.revoked_at')->count();
        $all = $this->count($filters);

        return [
            ['key' => 'issued', 'currency' => null, 'value' => $all, 'money' => false],
            ['key' => 'revoked', 'currency' => null, 'value' => $revoked, 'money' => false],
            ['key' => 'valid', 'currency' => null, 'value' => $all - $revoked, 'money' => false],
        ];
    }

    private function query(ReportFilters $filters): Builder
    {
        return DB::table('certificates')
            ->whereBetween('certificates.issued_at', [$filters->from, $filters->to])
            ->when($filters->courseIds !== null, static fn (Builder $query): Builder => $query->whereIn('certificates.course_id', $filters->courseIds ?? []))
            ->select('certificates.id', 'certificates.number', 'certificates.course_title_ar', 'certificates.course_title_en', 'certificates.issued_at', 'certificates.completed_on', 'certificates.revoked_at');
    }
}
