#!/usr/bin/env php
<?php
declare(strict_types=1);
foreach ([__DIR__ . '/../../vendor/autoload.php'] as $autoloader) {
    if (is_file($autoloader)) { require $autoloader; break; }
}
$fixtureDirs = array_slice($argv, 1);
foreach ($fixtureDirs as $dir) {
    foreach (glob($dir . '/Fixture/*.php.inc') ?: [] as $fixture) {
        $contents = (string) file_get_contents($fixture);
        $hasSeparator = str_contains($contents, '-----');
        $before = $hasSeparator ? explode('-----', $contents)[0] : $contents;
        $tmp = sys_get_temp_dir() . '/regen-' . md5($fixture) . '.php';
        file_put_contents($tmp, rtrim($before) . "\n");
        $sharedConfig = dirname(dirname(dirname($fixture))) . '/config/configured_rule.php';
        $localConfig = dirname(dirname($fixture)) . '/config/configured_rule.php';
        $config = is_file($sharedConfig) ? $sharedConfig : $localConfig;
        exec(sprintf('vendor/bin/rector process %s --config %s --no-progress-bar --clear-cache',
            escapeshellarg($tmp), escapeshellarg($config)), $out, $code);
        $actual = (string) file_get_contents($tmp);
        unlink($tmp);
        if ($code !== 0) { printf("[ERROR] %s\n", $fixture); continue; }
        if (! $hasSeparator) {
            echo rtrim($actual) === rtrim($before) ? "[skip-ok]   {$fixture}\n" : "[SKIP-CHANGED!] {$fixture}\n";
            continue;
        }
        $newContents = rtrim($before) . "\n-----\n" . rtrim($actual) . "\n";
        if ($newContents === $contents) { echo "[ok]        {$fixture}\n"; continue; }
        file_put_contents($fixture, $newContents);
        echo "[regen]     {$fixture}\n";
    }
}
