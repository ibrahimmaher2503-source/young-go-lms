<?php

declare(strict_types=1);

require __DIR__.'/telescope_tool.php';

use App\Models\User;
use Spatie\Permission\Models\Role;

$parents = User::role('parent')->get();
echo 'Parent users count: '.$parents->count()."\n";
foreach ($parents as $p) {
    echo "- ID: {$p->id}, Email: {$p->email}, Name: {$p->name}\n";
}

$allRoles = Role::pluck('name')->all();
echo "\nAll Roles:\n".implode(', ', $allRoles)."\n";
