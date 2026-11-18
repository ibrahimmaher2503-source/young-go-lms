<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;

foreach (Route::getRoutes() as $r) {
    if (in_array('GET', $r->methods())) {
        echo $r->uri().' => '.$r->getActionName()."\n";
    }
}
