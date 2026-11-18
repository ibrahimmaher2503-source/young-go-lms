<?php

declare(strict_types=1);

require __DIR__.'/telescope_tool.php';

$p = getLatestRequestDetails('/progress');
echo json_encode($p, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
