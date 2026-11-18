param([ValidateSet('BASELINE', 'AFTER')][string]$Phase = 'BASELINE', [string]$Filter = '')

$ErrorActionPreference = 'Stop'
$workspace = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '../..')).Path
$browserDirectory = (Resolve-Path -LiteralPath (Join-Path $workspace 'tests/Browser')).Path
if ($browserDirectory -ne [IO.Path]::GetFullPath((Join-Path $workspace 'tests/Browser'))) {
    throw 'Browser test directory is outside the expected workspace.'
}
$source = [IO.File]::ReadAllText((Join-Path $PSScriptRoot 'UIRefinementMatrixTest.php'))
$functions = [regex]::Matches($source, '(?ms)^function uiRefinement\w+\(.*?^\}\r?$')
$groups = [regex]::Matches($source, '(?ms)^it\(.*?^\}\)->with\(\$uiRefinementViewports\)->group\([^\r\n]+\);')
$dataset = [regex]::Match($source, '(?ms)^\$uiRefinementViewports = \[.*?^\];')
$tuples = [regex]::Matches($dataset.Value, "\['(?:ar|en)', '(?:light|dark)', (?:390|1440), (?:844|900)\]")
if ($functions.Count -ne 3 -or $groups.Count -ne 6 -or $tuples.Count -ne 8) {
    throw 'Expected three helpers, six test groups and eight viewport tuples; source changed.'
}
$prefix = $source.Substring(0, $functions[0].Index)
$imports = ([regex]::Matches($source, '(?m)^use [^\r\n]+;') | ForEach-Object Value) -join "`n"
$support = "<?php`ndeclare(strict_types=1);`n$imports`n" + (($functions | ForEach-Object Value) -join "`n`n")
$supportPath = Join-Path $browserDirectory 'UIRefinementSupport.php'
$targets = @(
    for ($groupIndex = 0; $groupIndex -lt 6; $groupIndex++) {
        for ($viewportIndex = 0; $viewportIndex -lt 8; $viewportIndex++) {
            Join-Path $browserDirectory "UIRefinement${groupIndex}_${viewportIndex}Test.php"
        }
    }
)
if ($targets.Count -ne 48 -or ($targets | Select-Object -Unique).Count -ne 48) {
    throw 'Expected 48 unique shard paths.'
}
foreach ($target in @($supportPath) + $targets) {
    if ([IO.Path]::GetDirectoryName([IO.Path]::GetFullPath($target)) -ne $browserDirectory -or (Test-Path -LiteralPath $target)) {
        throw "Refusing to overwrite or create outside the browser directory: $target"
    }
}

$created = [Collections.Generic.List[string]]::new()
$previousPhase = $env:YG_UI_CAPTURE_PHASE
$previousRuntime = $env:YG_UI_BROWSER_RUNTIME
$previousStorage = $env:LARAVEL_STORAGE_PATH
$tempRoot = (Resolve-Path -LiteralPath ([IO.Path]::GetTempPath())).Path.TrimEnd([IO.Path]::DirectorySeparatorChar)
$runtimeDirectory = Join-Path $tempRoot ('yg-ui-browser-' + [guid]::NewGuid().ToString('N'))
if ((Test-Path -LiteralPath $runtimeDirectory) -or [IO.Path]::GetDirectoryName($runtimeDirectory) -ne $tempRoot) {
    throw 'Browser runtime must be a new child of the temporary directory.'
}
$runtimeCreated = $false
$exitCode = 1
Push-Location -LiteralPath $workspace
try {
    New-Item -ItemType Directory -Path $runtimeDirectory | Out-Null
    $runtimeCreated = $true
    $pluginDirectory = Join-Path $workspace 'vendor/pestphp/pest-plugin-browser'
    foreach ($folder in @('src', 'resources')) {
        Copy-Item -LiteralPath (Join-Path $pluginDirectory $folder) -Destination $runtimeDirectory -Recurse
    }
    $env:YG_UI_BROWSER_RUNTIME = $runtimeDirectory
    $env:LARAVEL_STORAGE_PATH = Join-Path $runtimeDirectory 'storage'
    foreach ($folder in @('framework/views', 'framework/cache/data', 'framework/sessions', 'logs', 'app/private', 'app/public')) {
        New-Item -ItemType Directory -Path (Join-Path $env:LARAVEL_STORAGE_PATH $folder) -Force | Out-Null
    }
    # The prior serial copy is owned by this evidence task, not an existing project test.
    $serialCopy = Join-Path $browserDirectory 'UIRefinementMatrixTest.php'
    if (Test-Path -LiteralPath $serialCopy) {
        if ([IO.File]::ReadAllText($serialCopy) -ne $source) {
            throw 'The serial copy differs from the source; inspect it before removing it.'
        }
        Remove-Item -LiteralPath $serialCopy
    }
    [IO.File]::WriteAllText($supportPath, $support, [Text.UTF8Encoding]::new($false))
    $created.Add($supportPath)
    for ($groupIndex = 0; $groupIndex -lt 6; $groupIndex++) {
        for ($viewportIndex = 0; $viewportIndex -lt 8; $viewportIndex++) {
            $singleDataset = '$uiRefinementViewports = [' + $tuples[$viewportIndex].Value + '];'
            $shardPrefix = $prefix.Replace($dataset.Value, $singleDataset)
            $shard = $shardPrefix + "`nrequire_once __DIR__.'/UIRefinementSupport.php';`n`n" + $groups[$groupIndex].Value + "`n"
            $target = $targets[$groupIndex * 8 + $viewportIndex]
            [IO.File]::WriteAllText($target, $shard, [Text.UTF8Encoding]::new($false))
            $created.Add($target)
        }
    }
    if ($created.Count -ne 49) {
        throw 'Expected 48 generated tests plus one shared helper.'
    }
    $php = (Get-Command php -CommandType Application | Select-Object -First 1).Source
    & $php -d opcache.enable_cli=0 -d extension=sockets -d memory_limit=2G -l @created
    if ($LASTEXITCODE -ne 0) {
        throw 'Generated PHP failed syntax validation.'
    }
    $env:YG_UI_CAPTURE_PHASE = $Phase
    Write-Host "Capturing ${Phase}: 48 files, four browser workers."
    $prependOption = 'auto_prepend_file=' + (Join-Path $PSScriptRoot 'UIBrowserBootstrap.php').Replace('\', '/')
    $workerPhp = '--passthru-php=-d opcache.enable_cli=0 -d extension=sockets -d memory_limit=2G -d "' + $prependOption + '"'
    $arguments = @(
        '-d', 'opcache.enable_cli=0', '-d', 'extension=sockets', '-d', 'memory_limit=2G',
        '-d', $prependOption,
        'vendor/bin/pest', 'tests/Browser', '--group=T-UI-00', '--parallel', '--processes=4',
        $workerPhp
    )
    if ($Filter) { $arguments += '--filter=' + $Filter }
    & $php @arguments
    $exitCode = $LASTEXITCODE
} finally {
    foreach ($target in $created) {
        if (Test-Path -LiteralPath $target) {
            Remove-Item -LiteralPath $target
        }
    }
    if ($runtimeCreated -and (Test-Path -LiteralPath $runtimeDirectory)) {
        $resolvedRuntime = (Resolve-Path -LiteralPath $runtimeDirectory).Path
        if ($resolvedRuntime -ne $runtimeDirectory -or [IO.Path]::GetDirectoryName($resolvedRuntime) -ne $tempRoot -or [IO.Path]::GetFileName($resolvedRuntime) -notmatch '^yg-ui-browser-[a-f0-9]{32}$') {
            throw 'Refusing to remove an unexpected browser runtime path.'
        }
        Remove-Item -LiteralPath $resolvedRuntime -Recurse -Force
    }
    $env:YG_UI_CAPTURE_PHASE = $previousPhase
    $env:YG_UI_BROWSER_RUNTIME = $previousRuntime
    $env:LARAVEL_STORAGE_PATH = $previousStorage
    Pop-Location
}
exit $exitCode
