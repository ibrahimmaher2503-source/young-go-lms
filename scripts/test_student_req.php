<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Domain\Identity\TwoFactorSession;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

Config::set('security.sessions.enabled', false);
Config::set('identity.two_factor_enforced', false);

$student = User::where('email', 'student@younggo.test')->firstOrFail();
Auth::guard('web')->login($student);

$session = app('session.store');
$session->start();
$session->put(TwoFactorSession::KEY, (int) $student->id);
$session->put(Auth::guard('web')->getName(), $student->id);

$requestCourses = Request::create('/my/courses', 'GET');
$requestCourses->setLaravelSession($session);
$requestCourses->setUserResolver(fn () => $student);
app()->instance('request', $requestCourses);

$responseCourses = $app->handle($requestCourses);
if ($responseCourses->exception) {
    echo 'Exception: '.get_class($responseCourses->exception)."\n";
    echo 'Message: '.$responseCourses->exception->getMessage()."\n";
} else {
    echo 'Status /my/courses: '.$responseCourses->getStatusCode()."\n";
}
