<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Instructors\Models\InstructorAgreement;
use Illuminate\Database\Seeder;

/**
 * Placeholder instructor agreement (T-P9-01). The legal wording is OPEN (owner/counsel). It is seeded as an unpublished DRAFT so
 * it never blocks anyone; an admin edits and publishes the approved wording. Never overwrites an existing version.
 */
final class InstructorAgreementSeeder extends Seeder
{
    public function run(): void
    {
        $version = (string) config('instructor_onboarding.placeholder_agreement_version');

        if (InstructorAgreement::query()->where('version', $version)->exists()) {
            return;
        }

        $agreement = new InstructorAgreement(['version' => $version, 'status' => InstructorAgreement::DRAFT]);
        $agreement->setTranslations('title', [
            'en' => 'Instructor agreement (draft)',
            'ar' => 'اتفاقية المدرّس (مسوّدة)',
        ]);
        $agreement->setTranslations('body', [
            'en' => 'DRAFT PLACEHOLDER. This wording is not approved and must be replaced by the agreement approved by Young Go and its legal counsel before publishing.',
            'ar' => 'نص مسوّدة مؤقت. هذه الصياغة غير معتمدة ويجب استبدالها بالاتفاقية المعتمدة من يَنغ غو ومستشارها القانوني قبل النشر.',
        ]);
        $agreement->save();
    }
}
