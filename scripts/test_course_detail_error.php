<?php

declare(strict_types=1);

require __DIR__.'/telescope_tool.php';

use App\Domain\Catalog\CourseSeo;
use App\Domain\Catalog\Queries\PublicCourseDetail;
use App\Domain\Seo\PublicUrl;

try {
    $details = app(PublicCourseDetail::class);
    $found = $details->find('demo-course');
    echo "Found course: ID {$found->id}, Title: ".json_encode($found->title)."\n";
    $curriculum = $details->curriculum($found);
    echo 'Curriculum lessons count: '.$curriculum['lessons']."\n";
    $url = PublicUrl::course($found, app()->getLocale());
    echo "Public URL: {$url}\n";
    $seo = CourseSeo::for($found, $url, $curriculum['lessons']);
    echo 'SEO title: '.$seo['title']."\n";

    echo "Attempting to render catalog.show view...\n";
    $view = view('catalog.show', [
        'course' => $found,
        'curriculum' => $curriculum,
        'seo' => $seo,
        'key' => PublicCourseDetail::key($found),
        'entitled' => false,
        'signedIn' => false,
    ])->render();
    echo 'Render SUCCESS, length: '.strlen($view)."\n";
} catch (Throwable $e) {
    echo 'ERROR: '.get_class($e).': '.$e->getMessage()."\n";
    echo 'File: '.$e->getFile().':'.$e->getLine()."\n";
    echo "Trace:\n".$e->getTraceAsString()."\n";
}
