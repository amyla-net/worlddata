<?php

use Amyla\Worlddata\Build\SnapshotWriter;
use Amyla\Worlddata\Build\SourceDownloader;

require __DIR__.'/../vendor/autoload.php';

$options = getopt('', ['offline', 'latest', 'cldr-version:', 'source:', 'output:']);
$sourcePath = $options['source'] ?? __DIR__.'/sources.json';
$outputPath = $options['output'] ?? __DIR__.'/../resources/data.json';

try {
    if (isset($options['offline'])) {
        $source = json_decode(file_get_contents($sourcePath), true, 512, JSON_THROW_ON_ERROR);
    } else {
        $downloader = new SourceDownloader;
        $version = $options['cldr-version'] ?? '49.0.0';
        if (isset($options['latest'])) {
            $version = $downloader->latestVersion();
        } elseif (! isset($options['cldr-version']) && is_file($sourcePath)) {
            $version = json_decode(file_get_contents($sourcePath), true, 512, JSON_THROW_ON_ERROR)['meta']['cldrVersion'];
        }
        $source = $downloader->fetch($version);
    }
    $changed = (new SnapshotWriter)->write($source, $sourcePath, $outputPath);
    fwrite(STDOUT, $changed ? "World data snapshots updated.\n" : "World data is unchanged.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
}
