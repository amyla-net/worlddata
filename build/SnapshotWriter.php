<?php

namespace Amyla\Worlddata\Build;

use RuntimeException;

final class SnapshotWriter
{
    public function write(array $source, string $sourcePath, string $outputPath): bool
    {
        // Complete validation and serialization before touching either destination.
        $compiled = (new Compiler)->compile($source);
        $json = $this->encode($compiled);
        $sourceJson = $this->encode($source);
        $oldOutput = is_file($outputPath) ? file_get_contents($outputPath) : false;
        if ($oldOutput !== false) {
            $old = json_decode($oldOutput, true, 512, JSON_THROW_ON_ERROR);
            $previousMeta = $old['meta'];
            $old['meta'] = $compiled['meta'];
            if ($this->encode($old) === $json && ($previousMeta['cldrVersion'] ?? null) === $compiled['meta']['cldrVersion']) {
                return false;
            }
        }

        $oldSource = is_file($sourcePath) ? file_get_contents($sourcePath) : false;
        $this->replace($sourcePath, $sourceJson);
        try {
            $this->replace($outputPath, $json);
        } catch (\Throwable $exception) {
            if ($oldSource !== false) {
                $this->replace($sourcePath, $oldSource);
            } else {
                unlink($sourcePath);
            }
            throw $exception;
        }

        return true;
    }

    public function encode(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    }

    private function replace(string $path, string $contents): void
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException("Cannot create {$directory}.");
        }
        $temporary = tempnam($directory, '.worlddata-');
        if ($temporary === false) {
            throw new RuntimeException("Cannot create temporary snapshot in {$directory}.");
        }
        try {
            if (file_put_contents($temporary, $contents) !== strlen($contents) || ! chmod($temporary, 0644) || ! rename($temporary, $path)) {
                throw new RuntimeException("Cannot replace snapshot {$path}.");
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
