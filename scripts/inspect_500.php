<?php

declare(strict_types=1);

$content = file_get_contents(__DIR__.'/../storage/logs/laravel.log');
$pos = strrpos($content, 'QueryException');
if ($pos !== false) {
    echo substr($content, max(0, $pos - 100), 2000)."\n";
}
