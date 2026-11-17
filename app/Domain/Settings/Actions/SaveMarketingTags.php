<?php

declare(strict_types=1);

namespace App\Domain\Settings\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Privacy\MarketingTagIds;
use App\Domain\Settings\MarketingSettings;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/** Saves the GA4 and Meta Pixel IDs (blank turns a tag off). Needs content.seo.manage; strict shapes because the IDs are printed into inline script. */
final class SaveMarketingTags
{
    public function __construct(private readonly MarketingSettings $settings, private readonly AuditLogger $audit) {}

    /** @param  array<string, mixed>  $data */
    public function __invoke(User $actor, array $data): void
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('content.seo.manage')) {
            throw new AuthorizationException;
        }

        $clean = Validator::make($data, [
            'ga4_measurement_id' => ['nullable', 'string', 'regex:'.MarketingTagIds::GA4_PATTERN],
            'meta_pixel_id' => ['nullable', 'string', 'regex:'.MarketingTagIds::META_PIXEL_PATTERN],
        ], [], (array) __('consent.admin.fields'))->validate();

        $before = ['ga4_measurement_id' => $this->settings->ga4_measurement_id, 'meta_pixel_id' => $this->settings->meta_pixel_id];
        $after = [
            'ga4_measurement_id' => filled($clean['ga4_measurement_id'] ?? null) ? trim((string) $clean['ga4_measurement_id']) : null,
            'meta_pixel_id' => filled($clean['meta_pixel_id'] ?? null) ? trim((string) $clean['meta_pixel_id']) : null,
        ];

        if ($before === $after) {
            return;
        }

        DB::transaction(function () use ($actor, $before, $after): void {
            $this->settings->ga4_measurement_id = $after['ga4_measurement_id'];
            $this->settings->meta_pixel_id = $after['meta_pixel_id'];
            $this->settings->save();

            if ($this->audit->record('marketing.tags_saved', $actor, (int) $actor->getKey(), $before, $after) === null) {
                throw new RuntimeException('Unable to record marketing tags audit.');
            }
        });
    }
}
