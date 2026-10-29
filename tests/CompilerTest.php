<?php

namespace Amyla\Worlddata\Tests;

use Amyla\Worlddata\Build\Compiler;
use Amyla\Worlddata\Build\SnapshotWriter;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CompilerTest extends TestCase
{
    private function source(): array
    {
        return json_decode(file_get_contents(__DIR__.'/../build/sources.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_checked_in_snapshot_is_reproducible_without_network_access(): void
    {
        $writer = new SnapshotWriter;
        $this->assertSame(file_get_contents(__DIR__.'/../resources/data.json'), $writer->encode((new Compiler)->compile($this->source())));
    }

    public static function corruptions(): array
    {
        return [['schema'], ['metadata'], ['empty'], ['cycle'], ['missing_continent'], ['missing_language'], ['tld_type'], ['duplicate_tld'], ['idn_country'], ['idn_label']];
    }

    #[DataProvider('corruptions')]
    public function test_invalid_sources_never_replace_either_snapshot(string $corruption): void
    {
        $source = $this->source();
        switch ($corruption) {
            case 'metadata':
                unset($source['meta']['sources']['ianaTlds']['sha256']);
                break;
            case 'schema': $source['schema'] = 99;
                break;
            case 'empty': $source['tlds'] = [];
                break;
            case 'cycle': $source['containment']['002'] = ['002'];
                break;
            case 'missing_continent': $source['containment']['155'] = [];
                break;
            case 'missing_language': unset($source['languageNames']['caa']);
                break;
            case 'tld_type': $source['tlds'][0]['type'] = 'unknown';
                break;
            case 'duplicate_tld': $source['tlds'][] = $source['tlds'][0];
                break;
            case 'idn_country':
                foreach ($source['tlds'] as &$record) {
                    if ($record['name'] === 'xn--p1ai') {
                        unset($record['iso3166_code']);
                    }
                }
                unset($record);
                break;
            case 'idn_label':
                foreach ($source['tlds'] as &$record) {
                    if ($record['name'] === 'xn--p1ai') {
                        $record['ulabel'] = 'com';
                    }
                }
                unset($record);
                break;
        }
        $directory = sys_get_temp_dir().'/worlddata-test-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $sourcePath = $directory.'/source.json';
        $outputPath = $directory.'/output.json';
        file_put_contents($sourcePath, 'original source');
        file_put_contents($outputPath, 'original output');
        try {
            try {
                (new SnapshotWriter)->write($source, $sourcePath, $outputPath);
                $this->fail('Expected invalid source data to be rejected.');
            } catch (InvalidArgumentException) {
                $this->assertSame('original source', file_get_contents($sourcePath));
                $this->assertSame('original output', file_get_contents($outputPath));
            }
        } finally {
            unlink($sourcePath);
            unlink($outputPath);
            rmdir($directory);
        }
    }

    public function test_a_metadata_only_refresh_does_not_create_update_noise(): void
    {
        $directory = sys_get_temp_dir().'/worlddata-test-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $sourcePath = $directory.'/source.json';
        $outputPath = $directory.'/output.json';
        $writer = new SnapshotWriter;
        $source = $this->source();
        try {
            $this->assertTrue($writer->write($source, $sourcePath, $outputPath));
            $original = file_get_contents($outputPath);
            $source['meta']['ianaTldsDate'] = '2099-01-01T00:00:00Z';
            $this->assertFalse($writer->write($source, $sourcePath, $outputPath));
            $this->assertSame($original, file_get_contents($outputPath));
        } finally {
            unlink($sourcePath);
            unlink($outputPath);
            rmdir($directory);
        }
    }
}
