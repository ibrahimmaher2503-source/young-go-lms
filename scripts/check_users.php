<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

echo "All Users:\n";
foreach (User::all() as $u) {
    echo "- ID: {$u->id}, Email: {$u->email}, Roles: ".$u->roles->pluck('name')->join(', ')."\n";
}
$instructor = User::where('email', 'instructor@younggo.test')->first();
if ($instructor) {
    echo "\nInstructor: ".$instructor->email."\n";
    echo 'Status: '.$instructor->status."\n";
    echo 'Roles: '.implode(', ', $instructor->getRoleNames()->all())."\n";
    echo 'Can panel.instructor.access: '.($instructor->can('panel.instructor.access') ? 'YES' : 'NO')."\n";
    echo "Password 'password123': ".(Hash::check('password123', $instructor->password) ? 'YES' : 'NO')."\n";
}
