<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Progress\CourseProgress;
use App\Domain\Progress\LessonProgress;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class DemoStudentSeeder extends Seeder
{
    public function run(): void
    {
        $instructor = User::firstOrCreate(['email' => 'instructor@younggo.test'], [
            'name' => 'أحمد مدرب',
            'status' => 'active',
            'email_verified_at' => now(),
            'password' => Hash::make('password123'),
        ]);
        $instructor->assignRole('instructor_academy');

        $learner = User::firstOrCreate(['email' => 'student@younggo.test'], [
            'name' => 'طالب تجريبي',
            'locale' => 'ar',
            'status' => 'active',
            'email_verified_at' => now(),
            'password' => Hash::make('password123'),
        ]);
        $learner->assignRole('student');

        // Clear previous progress so demo is always in fresh enrolled state
        LessonProgress::query()->where('learner_id', $learner->id)->delete();
        CourseProgress::query()->where('learner_id', $learner->id)->delete();

        $course = Course::firstOrCreate(['slug_ar' => 'demo-course'], [
            'title' => ['ar' => 'دورة تجريبية للرسم', 'en' => 'Demo Drawing Course'],
            'slug_en' => 'demo-course-en',
            'status' => 'published',
            'access_mode' => 'free',
        ]);

        if (! $course->instructors()->where('instructor_id', $instructor->id)->exists()) {
            $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
        }

        $section = CourseSection::query()->where('course_id', $course->id)->first();
        if (! $section) {
            $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['ar' => 'القسم الأول', 'en' => 'Section 1']]);
            $section->update(['published' => true]);
        }

        $lessonItem = $section->items()->first();
        if (! $lessonItem) {
            $lessonItem = app(ManageLessons::class)->add($instructor, $section, [
                'title' => ['ar' => 'الدرس الأول: مقدمة', 'en' => 'Lesson 1: Introduction'],
                'type' => 'text',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [
                                ['type' => 'text', 'text' => 'مرحبا بك في الدرس التجريبي.'],
                            ],
                        ],
                    ],
                ],
                'duration_seconds' => 300,
            ]);
            app(SetCurriculumStatus::class)($instructor, $course, [$lessonItem->id], 'published');
        }

        app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);
    }
}
