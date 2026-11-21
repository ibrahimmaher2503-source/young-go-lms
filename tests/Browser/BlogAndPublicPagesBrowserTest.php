<?php

declare(strict_types=1);

use App\Domain\Catalog\CourseStatus;
use App\Domain\Content\BlogPostStatus;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\CmsPage;
use App\Models\Course;
use App\Models\InstructorProfile;
use App\Models\User;
use Database\Seeders\CmsPageSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('captures About, Privacy and FAQ from the CMS in both directions', function (string $locale, int $width, int $height, string $theme): void {
    $this->seed(CmsPageSeeder::class);
    $about = CmsPage::query()->where('seed_key', 'about')->firstOrFail();
    $about->update(['status' => CmsPage::PUBLISHED, 'body' => [
        'ar' => __('public-editorial.pages.about.body', [], 'ar'),
        'en' => __('public-editorial.pages.about.body', [], 'en'),
    ]]);
    CmsPage::query()->where('seed_key', 'faq')->firstOrFail()->update(['status' => CmsPage::PUBLISHED, 'body' => [
        'ar' => __('public-editorial.pages.faq.body', [], 'ar'),
        'en' => __('public-editorial.pages.faq.body', [], 'en'),
    ]]);
    $terms = [
        ['مقدمة', 'Introduction', 'تعرّف من خلال هذه الصفحة على الأقسام الخاصة بالخصوصية والوصول إلى البيانات.', 'Read the sections on this page to find information about privacy and access to your data.'],
        ['المعلومات التي نجمعها', 'Information we collect', 'يتضمن نموذج التواصل حقول الاسم والبريد الإلكتروني والرسالة، مع رقم هاتف اختياري.', 'The contact form asks for a name, email and message, with an optional phone number.'],
        ['كيف نستخدم بياناتك', 'Use of data', 'يمكنك الرجوع إلى سياسة الخصوصية الرسمية لمعرفة تفاصيل استخدام البيانات.', 'Refer to the official policy for information about how data is used.'],
        ['مشاركة المعلومات', 'Information sharing', 'يمكنك توجيه أسئلتك بشأن مشاركة المعلومات إلى فريق المنصة.', 'You can send questions about information sharing to the platform team.'],
        ['ملفات تعريف الارتباط', 'Cookies', 'افتح إعدادات ملفات تعريف الارتباط للاطّلاع على خياراتك الحالية.', 'Open Cookie settings to view the options currently available to you.'],
        ['حماية البيانات', 'Data protection', 'احتفظ بكلمة المرور الخاصة بك وتجنب إرسالها ضمن رسائل الدعم.', 'Keep your password private and avoid sending it in support messages.'],
        ['طلبات الخصوصية', 'Privacy requests', 'يمكنك فتح إعدادات الخصوصية بعد تسجيل الدخول للاطّلاع على الخيارات المتاحة.', 'Sign in and open privacy settings to explore the available options.'],
        ['التواصل معنا', 'Contact', 'استخدم نموذج التواصل لإرسال سؤال بخصوص الخصوصية.', 'Use the contact form to ask a question about privacy.'],
    ];
    CmsPage::query()->where('seed_key', 'privacy')->firstOrFail()->update([
        'status' => CmsPage::PUBLISHED,
        'body' => [
            'ar' => implode("\n\n", array_map(fn ($term) => '## '.$term[0]."\n".$term[2], $terms)),
            'en' => implode("\n\n", array_map(fn ($term) => '## '.$term[1]."\n".$term[3], $terms)),
        ],
    ]);
    $directory = 'T-P2-02/info-reference';
    if (! is_dir(base_path('tests/Browser/screenshots/'.$directory))) {
        mkdir(base_path('tests/Browser/screenshots/'.$directory), 0755, true);
    }

    $privacyHero = null;
    foreach (['about', 'privacy', 'faq'] as $surface) {
        $page = $this->visit("/{$locale}/pages/{$surface}");
        if ($theme === 'dark') {
            $page = $page->inDarkMode();
        }
        $page->resize($width, $height);
        $page->assertVisible('[data-info-page="'.$surface.'"] h1')->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
        expect($page->script(<<<'JS'
            async () => {
                await document.fonts.ready;
                await document.querySelector('.info-hero img').decode();
                const gap = parseFloat(getComputedStyle(document.querySelector('.info-page')).rowGap);
                return [document.documentElement.dir, document.documentElement.scrollWidth <= innerWidth, document.documentElement.classList.contains('dark'), gap >= 26];
            }
            JS))->toBe([$locale === 'ar' ? 'rtl' : 'ltr', true, $theme === 'dark', true]);
        $page->screenshot(fullPage: true, filename: "{$directory}/{$surface}-{$locale}-{$theme}-{$width}");
        if ($surface !== 'about') {
            $hero = $page->script(<<<'JS'
                () => {
                    const photo = document.querySelector('.info-hero-media img');
                    const {width, height} = photo.getBoundingClientRect();
                    return [photo.currentSrc, width, height, getComputedStyle(photo).borderRadius, getComputedStyle(document.querySelector('.info-hero-title')).fontSize];
                }
                JS);
            if ($surface === 'faq') {
                expect($hero)->toBe($privacyHero);
                $page->click('.info-policy-nav a[href="#faq-section-2"]')->assertFragmentIs('faq-section-2');
                $page->assertVisible('#faq-section-2 h2');
            } else {
                $privacyHero = $hero;
            }
        }
        if ($surface === 'privacy') {
            if ($locale === 'ar' && $width === 1122) {
                expect($page->script('() => { const title = document.querySelector("main h1"); return title.offsetHeight < 1.6 * parseFloat(getComputedStyle(title).fontSize); }'))->toBeTrue();
            }
            $page->click('.info-policy-nav a[href="#policy-section-5"]')->assertFragmentIs('policy-section-5');
        }
    }
})->with(['ar', 'en'])->with([[390, 844], [1122, 1402], [1440, 900]])->with(['light', 'dark'])
    ->group('T-P2-02', 'A1', 'YG-WEB-005', 'browser');

it('renders public blog index and post accessibly in AR and EN across mobile and desktop', function (string $locale, int $width, int $height, string $theme): void {
    $this->seed(RolePermissionSeeder::class);
    $editor = User::factory()->create(['status' => 'active'])->assignRole('content_manager');
    $cat = BlogCategory::query()->create([
        'name' => ['ar' => 'تلوين', 'en' => 'Coloring'],
        'slug_ar' => 'col-ar',
        'slug_en' => 'col-en',
        'status' => BlogCategory::ACTIVE,
        'sort' => 1,
    ]);
    BlogPost::query()->create([
        'blog_category_id' => $cat->id,
        'author_id' => $editor->id,
        'title' => ['ar' => 'أساسيات التظليل', 'en' => 'Shading Basics'],
        'excerpt' => ['ar' => 'مقدمة سريعة في التظليل.', 'en' => 'A quick introduction to shading.'],
        'body' => ['ar' => 'نبدأ بالقلم الرصاص الناعم ثم نتدرج.', 'en' => 'Start with a soft pencil and layer smoothly.'],
        'slug_ar' => 'shading-ar',
        'slug_en' => 'shading-en',
        'status' => BlogPostStatus::Published,
        'published_at' => now()->subDay(),
    ]);

    foreach (['index' => "/{$locale}/blog", 'post' => "/{$locale}/blog/".($locale === 'ar' ? 'shading-ar' : 'shading-en')] as $surface => $url) {
        $page = $this->visit($url);
        if ($theme === 'dark') {
            $page = $page->inDarkMode();
        }
        $page->resize($width, $height);
        $page->script('async () => { await document.fonts.ready; await document.querySelector(".info-hero img").decode(); }');
        $page->assertVisible('.info-hero img[src$="privacy-640.webp"]');
        expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr')
            ->and($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue()
            ->and($page->script('() => document.documentElement.classList.contains("dark")'))->toBe($theme === 'dark');
        $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();

        if ($surface === 'index') {
            $slug = $locale === 'ar' ? 'shading-ar' : 'shading-en';
            $page->assertAttribute('main form[role="search"]', 'action', url("/{$locale}/blog"))
                ->assertVisible('[data-blog-card] a[href$="/'.$locale.'/blog/'.$slug.'"]');
        } else {
            $page->assertVisible('[data-blog-post] nav a[href$="/'.$locale.'/blog"]');
        }

        $dir = base_path('tests/Browser/screenshots/T-P2-07/info-reference');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $page->screenshot(fullPage: true, filename: "T-P2-07/info-reference/blog-{$surface}-{$locale}-{$width}-{$theme}");
    }
})->with(['ar', 'en'])->with([[390, 844], [1122, 1402], [1440, 900]])->with(['light', 'dark'])
    ->group('T-P2-07', 'A2', 'YG-WEB-006')
    ->group('browser');

it('renders public instructor profile and category landing page accessibly in AR and EN', function (string $locale, int $width, int $height, string $theme): void {
    $this->seed(RolePermissionSeeder::class);
    $instUser = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $cat = Category::query()->create([
        'name' => ['ar' => 'فنون الأطفال', 'en' => 'Kids Art'],
        'description' => ['ar' => 'برامج إبداعية ممتعة', 'en' => 'Fun creative studios'],
        'slug_ar' => 'kids-art-ar',
        'slug_en' => 'kids-art-en',
        'status' => 'active',
        'sort' => 1,
    ]);
    $profile = InstructorProfile::query()->create([
        'user_id' => $instUser->id,
        'slug_ar' => 'omar-art-ar',
        'slug_en' => 'omar-art-en',
        'display_name' => ['ar' => 'أ. عمر الخطاط', 'en' => 'Omar Artist'],
        'headline' => ['ar' => 'مدرب خط ورسم', 'en' => 'Calligraphy & Art Coach'],
        'bio' => ['ar' => 'خبرة عشر سنوات مع الأطفال.', 'en' => 'Ten years coaching young creators.'],
        'status' => InstructorProfile::APPROVED,
    ]);
    $profile->disciplines()->sync([$cat->id]);

    $course = Course::query()->create([
        'title' => ['ar' => 'استوديو الخط', 'en' => 'Studio Calligraphy'],
        'slug_ar' => 'callig-ar',
        'slug_en' => 'callig-en',
        'status' => CourseStatus::Published->value,
        'category_id' => $cat->id,
    ]);
    $course->instructors()->attach($instUser->id, ['role' => 'instructor', 'scope' => 'manage']);

    $urls = [
        'instructor' => "/{$locale}/instructors/".($locale === 'ar' ? 'omar-art-ar' : 'omar-art-en'),
        'category' => "/{$locale}/categories/".($locale === 'ar' ? 'kids-art-ar' : 'kids-art-en'),
    ];
    foreach ($urls as $surface => $url) {
        $page = $this->visit($url);
        if ($theme === 'dark') {
            $page = $page->inDarkMode();
        }
        $page->resize($width, $height);
        $page->script('async () => { await document.fonts.ready; }');
        expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr')
            ->and($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue()
            ->and($page->script('() => document.documentElement.classList.contains("dark")'))->toBe($theme === 'dark');
        $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();

        $slug = $locale === 'ar' ? 'callig-ar' : 'callig-en';
        $page->assertVisible('main nav a[href$="/'.$locale.'/courses"]')
            ->assertVisible('[data-course-link][href$="/'.$locale.'/courses/'.$slug.'"]');
        if ($surface === 'instructor') {
            $categorySlug = $locale === 'ar' ? 'kids-art-ar' : 'kids-art-en';
            $page->assertVisible('[data-disciplines] a[href$="/'.$locale.'/categories/'.$categorySlug.'"]');
        } else {
            $page->assertVisible('[data-section="hero"] a[href$="/'.$locale.'/courses?category='.$cat->id.'"]');
        }

        $dir = base_path('tests/Browser/screenshots/T-P2-09/editorial-polish');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $page->screenshot(fullPage: true, filename: "T-P2-09/editorial-polish/{$surface}-{$locale}-{$width}-{$theme}");
    }
})->with(['ar', 'en'])->with([[390, 844], [1440, 900]])->with(['light', 'dark'])
    ->group('T-P2-09', 'A3', 'YG-GAP-CMS-001', 'YG-GAP-CMS-002')
    ->group('browser');

it('presents published CMS reading pages with a localized breadcrumb and one main heading', function (string $locale, int $width, int $height, string $theme): void {
    CmsPage::query()->create([
        'slug' => 'studio-policy',
        'title' => ['ar' => 'سياسة الاستوديو', 'en' => 'Studio policy'],
        'body' => ['ar' => "تعرّف على قواعد الاستوديو.\n\nتواصل معنا عند الحاجة.", 'en' => "Read the studio rules.\n\nContact us if you need help."],
        'status' => 'published',
        'template' => 'policy',
    ]);

    $page = $this->visit("/{$locale}/pages/studio-policy");
    if ($theme === 'dark') {
        $page = $page->inDarkMode();
    }
    $page->resize($width, $height);
    $page->script('async () => { await document.fonts.ready; }');
    $page->assertVisible('main nav a[href$="/'.$locale.'"]')
        ->assertSee($locale === 'ar' ? 'سياسة الاستوديو' : 'Studio policy')
        ->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => [document.querySelectorAll("main h1").length, document.documentElement.scrollWidth <= innerWidth, document.documentElement.classList.contains("dark")]'))
        ->toBe([1, true, $theme === 'dark']);

    $dir = base_path('tests/Browser/screenshots/T-P2-02/editorial-polish');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $page->screenshot(fullPage: true, filename: "T-P2-02/editorial-polish/cms-{$locale}-{$width}-{$theme}");
})->with(['ar', 'en'])->with([[390, 844], [1440, 900]])->with(['light', 'dark'])
    ->group('T-P2-02', 'A1', 'YG-WEB-005', 'browser');
