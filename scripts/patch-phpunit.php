#!/usr/bin/env php
<?php
/**
 * Correctif pour le bug de compatibilité dans phpunit/php-code-coverage 12.5.x :
 * Les drivers Phpdbg, Xdebug2 et Xdebug3 référencent l'ancien namespace
 * RawCodeCoverageData au lieu de Data\RawCodeCoverageData.
 *
 * Ce script est exécuté automatiquement via post-autoload-dump.
 */

$old = 'use SebastianBergmann\\CodeCoverage\\RawCodeCoverageData;';
$new = 'use SebastianBergmann\\CodeCoverage\\Data\\RawCodeCoverageData;';

$drivers = [
    __DIR__ . '/../vendor/phpunit/php-code-coverage/src/Driver/PhpdbgDriver.php',
    __DIR__ . '/../vendor/phpunit/php-code-coverage/src/Driver/Xdebug2Driver.php',
    __DIR__ . '/../vendor/phpunit/php-code-coverage/src/Driver/Xdebug3Driver.php',
];

foreach ($drivers as $file) {
    if (!file_exists($file)) {
        continue;
    }
    $content = file_get_contents($file);
    if (str_contains($content, $old)) {
        file_put_contents($file, str_replace($old, $new, $content));
        echo "Patch appliqué : " . basename($file) . "\n";
    }
}
