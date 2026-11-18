<?php

declare(strict_types=1);

require __DIR__.'/LmsClient.php';

$client = new LmsClient;
$client->login('admin@younggo.test', 'password123');
$res = $client->get('/admin/courses');
echo 'Status: '.$res['status']."\n";
if (preg_match('/<title>(.*?)<\/title>/', $res['content'], $m)) {
    echo 'Title: '.$m[1]."\n";
} else {
    echo "No title found\n";
}
