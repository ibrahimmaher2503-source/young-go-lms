<?php

declare(strict_types=1);

use App\Domain\Certificates\Actions\IssueCertificate;
use App\Domain\Certificates\Actions\SaveCertificateTemplates;
use App\Domain\Certificates\CertificatePdf;
use App\Domain\Certificates\CertificateTemplate;
use App\Domain\Completion\CourseCompletion;
use App\Domain\Identity\Actions\UpdateCertificateNames;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('snapshots both certificate names and locks future self-service edits', function (): void {
    $learner = User::factory()->create();
    app(UpdateCertificateNames::class)->handle($learner, [
        'certificate_name_ar' => 'اسم الشهادة',
        'certificate_name_en' => 'Certificate Name',
    ]);
    $course = Course::query()->create(['title' => ['en' => 'Course'], 'slug_ar' => 'course', 'slug_en' => 'course', 'status' => 'published']);
    $completion = CourseCompletion::query()->create([
        'course_id' => $course->id, 'learner_id' => $learner->id, 'completed_at' => now(), 'source' => CourseCompletion::AUTO,
    ]);

    $certificate = app(IssueCertificate::class)($completion);

    expect($certificate->nameIn('ar'))->toBe('اسم الشهادة')
        ->and($certificate->nameIn('en'))->toBe('Certificate Name')
        ->and($learner->refresh()->getAttribute('certificate_names_locked_at'))->not->toBeNull();
    expect(fn () => app(UpdateCertificateNames::class)->handle($learner, ['certificate_name_en' => 'Changed']))
        ->toThrow(ValidationException::class);
})->group('T-P6-06', 'A1', 'PRD-14');

it('uses the selected locale certificate template and its configured issuer text', function (): void {
    $learner = User::factory()->create();
    $course = Course::query()->create(['title' => ['en' => 'Course'], 'slug_ar' => 'course', 'slug_en' => 'course', 'status' => 'published']);
    $completion = CourseCompletion::query()->create([
        'course_id' => $course->id, 'learner_id' => $learner->id, 'completed_at' => now(), 'source' => CourseCompletion::AUTO,
    ]);
    $certificate = app(IssueCertificate::class)($completion);

    $template = CertificateTemplate::query()->where('locale', 'en')->firstOrFail();
    $template->update(['issuer_text' => 'Configured issuer']);
    $pdf = app(CertificatePdf::class);

    expect($pdf->data($certificate, 'en')['issuer'])->toBe('Configured issuer')
        ->and($pdf->data($certificate, 'ar')['issuer'])->toBe(__('certificates.pdf.issuer_placeholder', [], 'ar'));
})->group('T-P6-06', 'PRD-14', 'O-019');

it('allows only a system settings manager to edit certificate issuer text', function (): void {
    $this->seed(RolePermissionSeeder::class);
    $reviewer = User::factory()->create()->assignRole('instructor_academy');
    $manager = User::factory()->create()->givePermissionTo('system.settings.manage');
    $save = app(SaveCertificateTemplates::class);

    expect(fn () => $save($reviewer, ['en' => 'Updated issuer']))->toThrow(AuthorizationException::class);
    $save($manager, ['en' => 'Updated issuer']);
    expect(CertificateTemplate::query()->firstWhere('locale', 'en')->issuer_text)->toBe('Updated issuer');
})->group('T-P6-06', 'PRD-14', 'O-019');
