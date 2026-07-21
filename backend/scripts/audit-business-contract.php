<?php

$root = dirname(__DIR__);
$scanRoots = [
    $root.'/app',
    $root.'/config',
    $root.'/database',
    $root.'/routes',
];
$failures = [];

foreach ($scanRoots as $scanRoot) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if (! $file->isFile() || ! in_array($file->getExtension(), ['php', 'json', 'yml', 'yaml'], true)) {
            continue;
        }

        $content = file_get_contents($file->getPathname());
        if ($content === false) {
            $failures[] = 'Unreadable file: '.$file->getPathname();
            continue;
        }

        if (preg_match('/\bgrind(?:Type|_type|Setting|_setting)?\b/i', $content) === 1) {
            $failures[] = 'Forbidden grind state: '.$file->getPathname();
        }
    }
}

$rostaConfig = file_get_contents($root.'/config/rosta.php');
if ($rostaConfig === false) {
    $failures[] = 'Missing config/rosta.php';
} else {
    foreach (['50', '100', '250', '500', '1000'] as $weight) {
        if (! str_contains($rostaConfig, $weight)) {
            $failures[] = 'Missing whole-bean weight '.$weight.' in config/rosta.php';
        }
    }

    if (! str_contains($rostaConfig, "'single_roastery_orders' => true")) {
        $failures[] = 'Single-roastery order boundary is not locked.';
    }
}

$contractPath = dirname($root).'/docs/openapi/rosta-v1-phase6.yaml';
if (! is_file($contractPath) || filesize($contractPath) === 0) {
    $failures[] = 'Frozen OpenAPI contract is missing.';
}

if ($failures !== []) {
    fwrite(STDERR, "Backend business contract audit failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- '.$failure."\n");
    }
    exit(1);
}

fwrite(STDOUT, "Backend business contract audit passed.\n");
