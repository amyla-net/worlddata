<?php

namespace Amyla\Worlddata\Build;

use PharData;
use RecursiveIteratorIterator;
use RuntimeException;

final class SourceDownloader
{
    private array $sources = [];

    public function fetch(string $cldrVersion): array
    {
        if (! preg_match('/^\d+\.\d+\.\d+$/D', $cldrVersion)) {
            throw new RuntimeException('CLDR must be a stable x.y.z release.');
        }
        $core = $this->archive('cldr-core', $cldrVersion);
        $locales = $this->archive('cldr-localenames-full', $cldrVersion);
        $containment = $this->readJson($core['package/supplemental/territoryContainment.json']->getContent())['supplemental']['territoryContainment'];
        $territoryInfo = $this->readJson($core['package/supplemental/territoryInfo.json']->getContent())['supplemental']['territoryInfo'];
        $regions = [];
        foreach ($containment as $code => $entry) {
            if (! str_contains($code, '-status-')) {
                $regions[$code] = $entry['_contains'];
            }
        }
        $territories = [];
        foreach ($territoryInfo as $code => $info) {
            $territories[$code] = [];
            foreach ($info['languagePopulation'] ?? [] as $language => $attributes) {
                if (isset($attributes['_officialStatus'])) {
                    $territories[$code][$language] = $attributes['_officialStatus'];
                }
            }
        }
        $names = [];
        foreach (new RecursiveIteratorIterator($locales) as $file) {
            if ($file->getFilename() !== 'territories.json') {
                continue;
            }
            $data = $this->readJson($file->getContent());
            $locale = array_key_first($data['main']);
            $labels = $data['main'][$locale]['localeDisplayNames']['territories'];
            foreach (['002', '003', '005', '009', '142', '150', 'AQ'] as $region) {
                if (isset($labels[$region])) {
                    $names[$locale][$region] = $labels[$region];
                }
            }
        }
        ksort($names);

        $tlds = $this->readJson($this->download('https://data.iana.org/TLD/v1/tlds.json', 'ianaTlds'));
        if (($tlds['meta']['specversion'] ?? null) !== 1 || ! is_array($tlds['domains'] ?? null) || $tlds['domains'] === []) {
            throw new RuntimeException('Unsupported or empty IANA TLD export.');
        }
        $records = [];
        foreach ($tlds['domains'] as $record) {
            $records[] = array_intersect_key($record, array_flip(['name', 'ulabel', 'type', 'status', 'iso3166_code']));
        }

        $registry = $this->download('https://www.iana.org/assignments/language-subtag-registry/language-subtag-registry', 'ianaLanguages');
        if (! preg_match('/^File-Date: (\d{4}-\d{2}-\d{2})/D', strtok($registry, "\n"), $date)) {
            throw new RuntimeException('Missing IANA language registry date.');
        }
        $languageNames = [];
        foreach (preg_split('/\R%%\R/', $registry) as $record) {
            if (preg_match('/^Type: language$/m', $record) && preg_match('/^Subtag: ([a-z]{2,3})$/m', $record, $code) && preg_match('/^Description: (.+)$/m', $record, $description)) {
                $languageNames[$code[1]] = trim($description[1]);
            }
        }
        if (count($languageNames) < 1000) {
            throw new RuntimeException('Incomplete IANA language registry.');
        }
        // Preserve only names needed for the selected official-language relationships.
        $needed = [];
        foreach ($territories as $languages) {
            foreach ($languages as $language => $status) {
                if (in_array($status, ['official', 'de_facto_official', 'official_regional'], true)) {
                    $code = explode('_', $language)[0];
                    if (isset($languageNames[$code])) {
                        $needed[$code] = $languageNames[$code];
                    }
                }
            }
        }
        ksort($needed);

        return [
            'schema' => 1,
            'meta' => ['cldrVersion' => $cldrVersion, 'ianaTldsDate' => $tlds['meta']['generated'], 'ianaLanguagesDate' => $date[1], 'sources' => $this->sources],
            'containment' => $regions,
            'territories' => $territories,
            'continentNames' => $names,
            'languageNames' => $needed,
            'tlds' => $records,
        ];
    }

    public function latestVersion(): string
    {
        $metadata = $this->readJson($this->download('https://registry.npmjs.org/cldr-core/latest', 'cldrLatest'));

        return $metadata['version'];
    }

    private function archive(string $package, string $version): PharData
    {
        $url = "https://registry.npmjs.org/{$package}/-/{$package}-{$version}.tgz";
        $contents = $this->download($url, $package);
        $directory = __DIR__.'/../.cache/worlddata';
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException('Cannot create source archive cache.');
        }
        $path = $directory.'/'.hash('sha256', $contents).'.tar.gz';
        if (! is_file($path) && file_put_contents($path, $contents) !== strlen($contents)) {
            throw new RuntimeException('Cannot cache source archive.');
        }

        $archive = new PharData($path);
        $metadata = $this->readJson($archive['package/package.json']->getContent());
        if (($metadata['name'] ?? null) !== $package || ($metadata['version'] ?? null) !== $version) {
            throw new RuntimeException('CLDR archive does not match the requested package/version.');
        }

        return $archive;
    }

    private function download(string $url, string $name): string
    {
        $context = stream_context_create(['http' => ['timeout' => 60, 'header' => "User-Agent: Amyla-worlddata-maintainer/1.0\r\n", 'follow_location' => 0]]);
        $body = file_get_contents($url, false, $context);
        if ($body === false || $body === '') {
            throw new RuntimeException("Could not download {$url}.");
        }
        $this->sources[$name] = ['url' => $url, 'sha256' => hash('sha256', $body)];

        return $body;
    }

    private function readJson(string $body): array
    {
        $result = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($result)) {
            throw new RuntimeException('Expected a JSON object.');
        }

        return $result;
    }
}
