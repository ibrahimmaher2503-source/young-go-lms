<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

$instructor = User::where('email', 'instructor@younggo.test')->first();
Auth::guard('web')->login($instructor);

$request = Request::create('/instructor/courses', 'GET');
$request->setUserResolver(fn () => $instructor);

$response = $app->handle($request);
echo 'Status: '.$response->getStatusCode()."\n";
echo 'Location: '.$response->headers->get('Location')."\n";
echo 'Content length: '.strlen((string) $response->getContent())."\n";
