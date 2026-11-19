<?php

declare(strict_types=1);

namespace App\Domain\Migration\Support;

use Illuminate\Support\Facades\File;
use PDO;
use RuntimeException;

/**
 * Generates a deterministic synthetic SQLite fixture database matching the legacy schema in docs/migration/INVENTORY.md.
 */
final class LegacyFixtureBuilder
{
    public function build(string $sqlitePath, bool $force = true): string
    {
        $resolvedTarget = str_replace('\\', '/', $sqlitePath);
        $protectedTarget = str_replace('\\', '/', database_path('database.sqlite'));
        if (strtolower($resolvedTarget) === strtolower($protectedTarget)) {
            throw new RuntimeException('Refusing to overwrite the primary application database.sqlite file.');
        }

        if (is_file($sqlitePath)) {
            if (! $force) {
                throw new RuntimeException("Fixture file already exists at {$sqlitePath}. Pass --force to rebuild.");
            }
            unlink($sqlitePath);
        }

        $directory = dirname($sqlitePath);
        if (! is_dir($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $pdo = new PDO('sqlite:'.$sqlitePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $schemaSql = File::get(database_path('fixtures/legacy/schema.sql'));
        $pdo->exec($schemaSql);

        $this->seed($pdo);

        return $sqlitePath;
    }

    private function seed(PDO $pdo): void
    {
        $t0 = 1760000000;

        $pdo->exec("INSERT INTO role (id, name, date_added, last_modified) VALUES
            (1, 'Admin', {$t0}, {$t0}),
            (2, 'User', {$t0}, {$t0})");

        // Users: 1 admin, 2 instructors (Arabic + English names), 2 valid students, 1 invalid_email, 1 duplicate_email
        $users = [
            [1, 'أحمد', 'المدير', 'admin.legacy@example.test', '01001234567', 1, 1, 0, 'System Admin', 'Platform administrator', null, $t0, null],
            [2, 'سارة', 'المخرجة', 'sara.director@example.test', '01012345678', 2, 1, 1, 'Acting Coach', 'مدربة تمثيل ومسرح للأطفال والشباب', 'sara.jpg', $t0 + 100, '1992-05-14'],
            [3, 'Karim', 'Nabil', 'karim.voice@example.test', 'bad-phone', 2, 1, 1, 'Voiceover Artist', 'Voice acting & dubbing instructor', 'karim.jpg', $t0 + 200, '1990-11-02'],
            [4, 'ليلى', 'طارق', 'layla.learner@example.test', '01122334455', 2, 1, 0, null, null, null, $t0 + 300, '2012-03-20'],
            [5, 'Youssef', 'Samir', 'youssef.learner@example.test', '01234567890', 2, 1, 0, null, null, null, $t0 + 400, '2004-08-09'],
            [6, 'Invalid', 'User', 'not-an-email', '01000000000', 2, 1, 0, null, null, null, $t0 + 500, null],
            [7, 'Duplicate', 'Email', 'layla.learner@example.test', '01099999999', 2, 1, 0, null, null, null, $t0 + 600, null],
        ];
        $stmt = $pdo->prepare('INSERT INTO users (id, first_name, last_name, email, phone, role_id, status, is_instructor, title, biography, image, date_added, birth_date, verification_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "")');
        foreach ($users as $u) {
            $stmt->execute($u);
        }

        // Categories: 2 parents, 1 child, 1 level-3 (rejected: category_depth_exceeded), 1 orphan (rejected: missing_parent_category)
        $categories = [
            [1, 'ACT', 'التمثيل والمسرح', 0, 'acting-theater', 'fa-masks-theater', 'cat-acting.jpg'],
            [2, 'VOX', 'Voice & Dubbing', 0, 'voice-dubbing', 'fa-microphone', 'cat-voice.jpg'],
            [3, 'STAGE', 'Stage Performance', 1, 'stage-performance', 'fa-theater-masks', 'cat-stage.jpg'],
            [4, 'DEEP', 'Too Deep Subcategory', 3, 'too-deep', 'fa-layer-group', null],
            [5, 'ORPH', 'Orphan Category', 999, 'orphan-cat', 'fa-ban', null],
        ];
        $stmt = $pdo->prepare('INSERT INTO category (id, code, name, parent, slug, font_awesome_class, thumbnail, date_added) VALUES (?, ?, ?, ?, ?, ?, ?, '.$t0.')');
        foreach ($categories as $c) {
            $stmt->execute($c);
        }

        // Courses: 1 paid Arabic acting course (published), 1 free English voiceover course (published), 1 draft course, 1 orphan-category (rejected)
        $courses = [
            [
                101,
                'أساسيات التمثيل المسرحي للشباب',
                'تعلم الوقوف على المسرح والتعبير الصوتي والجسدي.',
                '<p>دورة عملية متكاملة لتطوير مهارات التمثيل المسرحي.</p>',
                json_encode(['إتقان مخارج الحروف', 'الثقة أمام الجمهور'], JSON_UNESCAPED_UNICODE),
                json_encode([['faq_question' => 'هل تناسب المبتدئين؟', 'faq_answer' => 'نعم من سن 10 سنوات.']], JSON_UNESCAPED_UNICODE),
                'arabic',
                1,
                3,
                json_encode(['كاميرا هاتف أو حاسوب'], JSON_UNESCAPED_UNICODE),
                1200.0,
                1,
                950.0,
                'beginner',
                '2',
                'course-101.jpg',
                'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                $t0,
                'general',
                1,
                0,
                'active',
                'youtube',
                'تمثيل, مسرح',
                'دورة أساسيات التمثيل المسرحي من يانج جو',
                0,
                0,
                1,
                2,
                6,
            ],
            [
                102,
                'Voice Acting & Dubbing Starter',
                'Free introductory workshop on voice modulation.',
                '<p>Hands-on vocal warmups and studio mic techniques.</p>',
                json_encode(['Breath control', 'Character voices']),
                '[]',
                'english',
                2,
                0,
                json_encode(['Quiet room']),
                0.0,
                0,
                0.0,
                'all',
                '3',
                'course-102.jpg',
                null,
                $t0 + 100,
                'general',
                0,
                1,
                'active',
                null,
                'voiceover, dubbing',
                'Free voiceover course',
                1,
                0,
                0,
                1,
                null,
            ],
            [
                103,
                'Orphan Category Course',
                'Should be rejected',
                '<p>Missing category</p>',
                '[]',
                '[]',
                'english',
                999,
                0,
                '[]',
                500.0,
                0,
                0.0,
                'beginner',
                '2',
                null,
                null,
                $t0 + 200,
                'general',
                0,
                0,
                'draft',
                null,
                '',
                '',
                0,
                0,
                0,
                2,
                null,
            ],
        ];
        $stmt = $pdo->prepare('INSERT INTO course (id, title, short_description, description, outcomes, faqs, language, category_id, sub_category_id, requirements, price, discount_flag, discounted_price, level, user_id, thumbnail, video_url, date_added, course_type, is_top_course, is_admin, status, course_overview_provider, meta_keywords, meta_description, is_free_course, multi_instructor, enable_drip_content, creator, expiry_period) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($courses as $course) {
            $stmt->execute($course);
        }

        // Sections: 2 for course 101, 1 for course 102, 1 orphan (rejected: missing_course)
        $sections = [
            [201, 'مدخل إلى المسرح', 101, '2026-01-01', '2026-06-01', 'date', 1],
            [202, 'الارتجال وبناء الشخصية', 101, null, null, null, 2],
            [203, 'Vocal Warmups', 102, null, null, null, 1],
            [204, 'Orphan Section', 999, null, null, null, 1],
        ];
        $stmt = $pdo->prepare('INSERT INTO section (id, title, course_id, start_date, end_date, restricted_by, "order") VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($sections as $s) {
            $stmt->execute($s);
        }

        // Lessons (non-quiz) + 1 Quiz row in `lesson` + 1 orphan lesson
        $demoMobile = 'https://www.html5rocks.com/en/tutorials/video/basics/devstories.webm';
        $lessons = [
            // 301: Paid T3 YouTube lesson + mobile demo placeholder
            [301, 'الدرس الأول: لغة الجسد على المسرح', '00:12:30', 101, 201, 'youtube', null, 'https://www.youtube.com/watch?v=M7lc1UVf-VE', null, $t0, 'video', null, null, 'ملخص الدرس الأول', 1, 1, 0, 'html5', $demoMobile],
            // 302: Paid Wasabi lesson
            [302, 'تمارين التنفس والحوار', '00:15:00', 101, 201, 'url', null, 'https://s3.wasabisys.com/yg-media/lesson-302.mp4', null, $t0 + 10, 'wasabi', null, null, 'شرح عملي للتنفس', 0, 2, 0, null, null],
            // 303: Audio lesson
            [303, 'تطبيق صوتي على الإلقاء', '00:08:00', 101, 202, 'audio', null, null, '/uploads/lesson_files/audios/voice-303.mp3', $t0 + 20, 'audio', null, null, 'مقطع تدريبي', 0, 1, 0, null, null],
            // 304: Rich text lesson
            [304, 'نص المشهد التدريبي', '00:05:00', 101, 202, null, null, null, null, $t0 + 30, 'text', 'اقرأ المشهد التالي بصوت واضح ثم سجّل ملاحظاتك.', 'text', 'مشهد قصير', 0, 2, 0, null, null],
            // 305: Quiz row inside lesson table
            [305, 'اختبار أساسيات المسرح', null, 101, 202, null, null, null, null, $t0 + 40, 'quiz', '{"duration":15,"pass_mark":60}', null, 'اختبار قصير لقياس الفهم', 0, 3, 3, null, null],
            // 306: Free course Vimeo lesson
            [306, 'Breath Support Basics', '00:10:00', 102, 203, 'vimeo', null, 'https://vimeo.com/76979871', null, $t0 + 50, 'video', null, null, 'Free Vimeo intro', 1, 1, 0, null, null],
            // 307: Orphan section lesson (rejected)
            [307, 'Orphan Lesson', '00:04:00', 101, 999, 'youtube', null, 'https://www.youtube.com/watch?v=M7lc1UVf-VE', null, $t0 + 60, 'video', null, null, '', 0, 1, 0, null, null],
        ];
        $stmt = $pdo->prepare('INSERT INTO lesson (id, title, duration, course_id, section_id, video_type, cloud_video_id, video_url, audio_url, date_added, lesson_type, attachment, attachment_type, summary, is_free, "order", quiz_attempt, video_type_for_mobile_application, video_url_for_mobile_application) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($lessons as $l) {
            $stmt->execute($l);
        }

        // Resources: 1 PDF candidate on lesson 301, 1 image on lesson 302, 1 orphan (rejected)
        $resources = [
            [401, 301, 'ملف منهج لغة الجسد', 'lesson-301-curriculum.pdf'],
            [402, 302, 'مخطط المسرح', 'stage-diagram.png'],
            [403, 999, 'Orphan Resource', 'missing.pdf'],
        ];
        $stmt = $pdo->prepare('INSERT INTO resource_files (id, lesson_id, title, file_name, created_at, updated_at) VALUES (?, ?, ?, ?, "2026-10-10 10:00:00", "2026-10-10 10:00:00")');
        foreach ($resources as $r) {
            $stmt->execute($r);
        }

        // Questions for Quiz 305
        $questions = [
            [501, 305, 'ما العنصر الأهم في الوقوف على المسرح؟', 'mcq', 3, json_encode(['توازن الجسم والتنفس', 'السرعة في الكلام', 'النظر للأرض'], JSON_UNESCAPED_UNICODE), json_encode(['1']), 1],
            [502, 305, 'اذكر خطوة أساسية قبل الإلقاء الصوتي', 'fill_in_the_blank', 1, '[]', json_encode(['إحماء الصوت']), 2],
        ];
        $stmt = $pdo->prepare('INSERT INTO question (id, quiz_id, title, type, number_of_options, options, correct_answers, "order") VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($questions as $q) {
            $stmt->execute($q);
        }

        // BBB Meetings: 1 valid for course 101, 1 orphan (rejected)
        $pdo->exec("INSERT INTO bbb_meetings (id, course_id, meeting_id, moderator_pw, viewer_pw, instructions, created_at) VALUES
            (601, 101, 'yg-acting-live-1', 'modpass', 'viewpass', 'الدخول قبل الجلسة بـ 10 دقائق', '2026-10-15 17:00:00'),
            (602, 999, 'orphan-meeting', 'mod', 'view', '', '2026-10-15 17:00:00')");

        // Enrollments: 1 active paid (Layla in 101), 1 active free (Youssef in 102), 1 expired (Youssef in 101), 1 invalid expiry (rejected), 1 orphan user (rejected)
        $futureExpiry = $t0 + (3650 * 86400);
        $pastExpiry = $t0 + 86400;
        $enrols = [
            [701, 4, 101, 0, (string) $futureExpiry, $t0],
            [702, 5, 102, 1, null, $t0 + 10],
            [703, 5, 101, 0, (string) $pastExpiry, $t0 - 86400],
            [704, 4, 102, 0, (string) ($t0 - 10), $t0 + 100], // expiry <= start => rejected
            [705, 999, 101, 0, null, $t0], // missing user => rejected
        ];
        $stmt = $pdo->prepare('INSERT INTO enrol (id, user_id, course_id, gifted_by, expiry_date, date_added) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($enrols as $e) {
            $stmt->execute($e);
        }

        // Watch Histories & Watched Durations
        $pdo->exec("INSERT INTO watch_histories (watch_history_id, course_id, student_id, completed_lesson, course_progress, watching_lesson_id, quiz_result, completed_date, date_added, date_updated) VALUES
            (801, 101, 4, '[301,302]', 50, 303, '{\"305\":2}', NULL, '2026-10-16 12:00:00', '2026-10-17 12:00:00'),
            (802, 102, 5, '[306]', 100, 306, '[]', '2026-10-18 15:00:00', '2026-10-18 14:00:00', '2026-10-18 15:00:00')");
        $pdo->exec('INSERT INTO watched_duration (watched_id, watched_student_id, watched_course_id, watched_lesson_id, current_duration) VALUES
            (851, 4, 101, 301, 750),
            (852, 4, 101, 303, 180)');

        // Quiz Results: 1 submitted (Layla), 1 unsubmitted (skipped)
        $pdo->exec("INSERT INTO quiz_results (quiz_result_id, quiz_id, user_id, user_answers, correct_answers, total_obtained_marks, date_added, date_updated, is_submitted) VALUES
            (901, 305, 4, '{\"501\":[\"1\"],\"502\":\"إحماء الصوت\"}', '[\"501\",\"502\"]', 2.0, '2026-10-17 13:00:00', '2026-10-17 13:08:00', 1),
            (902, 305, 5, '[]', '[]', 0.0, '2026-10-17 14:00:00', '2026-10-17 14:00:00', 0)");

        // Payments: 1 valid EGP payment, 1 negative amount (rejected)
        $pdo->exec("INSERT INTO payment (id, user_id, payment_type, course_id, amount, date_added, admin_revenue, instructor_revenue, transaction_id) VALUES
            (1001, 4, 'paymob', 101, 950.0, {$t0}, '285', '665', 'TX-LEG-1001'),
            (1002, 5, 'manual', 101, -50.0, {$t0}, '0', '0', 'TX-BAD-1002')");

        // Ratings: 1 valid course review, 1 invalid rating (9 -> rejected), 1 non-course type (rejected)
        $pdo->exec("INSERT INTO rating (id, rating, user_id, ratable_id, ratable_type, date_added, review) VALUES
            (1101, 5, 4, 101, 'course', {$t0}, 'دورة رائعة ومفيدة جداً في التمثيل المسرحي.'),
            (1102, 9, 5, 102, 'course', {$t0}, 'Out of range score'),
            (1103, 5, 5, 2, 'instructor', {$t0}, 'Instructor rating not mapped as course review')");

        // Certificates: 1 valid (Youssef in 102), 1 orphan course (rejected)
        $pdo->exec("INSERT INTO certificates (id, student_id, course_id, shareable_url, issued_at) VALUES
            (1201, 5, 102, 'cert-leg-5-102', '2026-10-18 16:00:00'),
            (1202, 4, 999, 'cert-orphan', '2026-10-18 16:00:00')");
    }
}
