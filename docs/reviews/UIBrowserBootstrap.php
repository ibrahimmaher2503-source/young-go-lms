<?php

declare(strict_types=1);
use Pest\Browser\Plugin;

// Isolate the evidence run's browser-server state without changing the installed package.
$uiRuntime = realpath((string) getenv('YG_UI_BROWSER_RUNTIME'));
if ($uiRuntime === false || ! is_dir($uiRuntime.'/src') || ! is_dir($uiRuntime.'/resources')) {
    throw new RuntimeException('YG_UI_BROWSER_RUNTIME must point to the copied browser plugin.');
}
$uiRuntime = str_replace('\\', '/', $uiRuntime);
$uiWorkspace = str_replace('\\', '/', dirname(__DIR__, 2));
$uiLoader = require $uiWorkspace.'/vendor/autoload.php';
$uiOriginal = $uiWorkspace.'/vendor/pestphp/pest-plugin-browser/';
$uiClassmap = [];
foreach ($uiLoader->getClassMap() as $uiClass => $uiFile) {
    if (str_starts_with($uiClass, 'Pest\\Browser\\')) {
        $uiFile = str_replace('\\', '/', (string) realpath($uiFile));
        if (! str_starts_with($uiFile, $uiOriginal)) {
            throw new RuntimeException('Unexpected browser classmap source: '.$uiClass);
        }
        $uiClassmap[$uiClass] = $uiRuntime.'/'.substr($uiFile, strlen($uiOriginal));
        if (! is_file($uiClassmap[$uiClass])) {
            throw new RuntimeException('Copied browser class is missing: '.$uiClass);
        }
    }
}
$uiLoader->addClassMap($uiClassmap);
$uiLoader->setPsr4('Pest\\Browser\\', [$uiRuntime.'/src']);
$uiPluginFile = str_replace('\\', '/', (new ReflectionClass(Plugin::class))->getFileName());
if (! str_starts_with($uiPluginFile, $uiRuntime.'/src/')) {
    throw new RuntimeException('Browser plugin did not load from the isolated runtime.');
}
