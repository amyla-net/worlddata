<?php

namespace Amyla\Worlddata\Build;

use InvalidArgumentException;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Intl\Languages;

/** Maintainer-only compiler. No network access or filesystem mutations. */
final class Compiler
{
    private const REGIONS = ['AF' => '002', 'AN' => 'AQ', 'AS' => '142', 'EU' => '150', 'NA' => '003', 'OC' => '009', 'SA' => '005'];

    public function compile(array $source): array
    {
        $this->require(is_array($source['meta'] ?? null) && ($source['schema'] ?? null) === 1, 'Unsupported source snapshot.');
        $meta = $source['meta'];
        $this->require(is_string($meta['cldrVersion'] ?? null) && preg_match('/^\d+\.\d+\.\d+$/D', $meta['cldrVersion']) === 1, 'Missing CLDR version.');
        $this->require(is_string($meta['ianaTldsDate'] ?? null) && is_string($meta['ianaLanguagesDate'] ?? null), 'Missing IANA source dates.');
        foreach (['cldr-core', 'cldr-localenames-full', 'ianaTlds', 'ianaLanguages'] as $name) {
            $descriptor = $meta['sources'][$name] ?? [];
            $this->require(is_string($descriptor['url'] ?? null) && str_starts_with($descriptor['url'], 'https://'), "Missing source URL: {$name}.");
            $this->require(is_string($descriptor['sha256'] ?? null) && preg_match('/^[a-f0-9]{64}$/D', $descriptor['sha256']) === 1, "Missing source checksum: {$name}.");
        }
        foreach (['containment', 'territories', 'continentNames', 'languageNames', 'tlds'] as $key) {
            $this->require(is_array($source[$key] ?? null) && $source[$key] !== [], "Missing source data: {$key}.");
        }
        $countries = array_flip(Countries::getCountryCodes());
        // Symfony optionally exposes Kosovo through its user-assigned-code flag.
        // Keep its relationships ready without changing that global setting.
        $countries['XK'] = true;
        $continents = [];
        $countryContinents = [];
        foreach (self::REGIONS as $continent => $region) {
            foreach ($source['continentNames'] as $locale => $names) {
                if (isset($names[$region]) && is_string($names[$region]) && $names[$region] !== '') {
                    $continents[$continent][str_replace('-', '_', $locale)] = $names[$region];
                }
            }
            $this->require(isset($continents[$continent]['en']), "Missing English continent name: {$continent}.");
            ksort($continents[$continent]);
            foreach ($this->expand($region, $source['containment']) as $country) {
                if (isset($countries[$country]) && $country !== 'AQ') {
                    $this->require(! isset($countryContinents[$country]), "Ambiguous continent for {$country}.");
                    $countryContinents[$country] = $continent;
                }
            }
        }
        $countryContinents['AQ'] = 'AN';
        $this->require(array_diff_key($countries, $countryContinents) === [], 'Incomplete country-continent mapping.');

        $countryLanguages = [];
        $extraLanguages = [];
        $knownLanguages = array_flip(Languages::getLanguageCodes());
        foreach ($source['territories'] as $country => $languages) {
            $this->require(is_array($languages), "Invalid territory languages for {$country}.");
            if (! isset($countries[$country])) {
                continue;
            }
            $codes = [];
            foreach ($languages as $language => $status) {
                $this->require(is_string($status), "Invalid language status for {$country}.");
                if (! in_array($status, ['official', 'de_facto_official', 'official_regional'], true)) {
                    continue;
                }
                $code = explode('_', $language)[0];
                $this->require(preg_match('/^[a-z]{2,3}$/D', $code) === 1, "Invalid language code: {$language}.");
                if (! isset($knownLanguages[$code])) {
                    $this->require(isset($source['languageNames'][$code]) && is_string($source['languageNames'][$code]) && $source['languageNames'][$code] !== '', "Missing IANA language name: {$code}.");
                    $extraLanguages[$code] = $source['languageNames'][$code];
                }
                $codes[] = $code;
            }
            $codes = array_values(array_unique($codes));
            sort($codes);
            $countryLanguages[$country] = $codes;
        }
        $this->require(array_diff_key($countries, $countryLanguages) === [], 'Incomplete country-language source data.');

        $tlds = [];
        foreach ($source['tlds'] as $record) {
            $this->require(is_array($record), 'Invalid TLD record.');
            $code = $record['name'] ?? null;
            $this->require(is_string($code) && preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $code) === 1, 'Invalid TLD label.');
            $this->require(in_array($record['status'] ?? null, ['active', 'retired', 'eligible', 'reserved'], true), "Invalid TLD status: {$code}.");
            $this->require(in_array($record['type'] ?? null, ['generic', 'country-code', 'special-use', 'test'], true), "Invalid TLD type: {$code}.");
            if ($record['status'] !== 'active') {
                continue;
            }
            $this->require(! isset($tlds[$code]), "Duplicate TLD: {$code}.");
            $name = $record['ulabel'] ?? $code;
            $this->require(is_string($name) && $name !== '' && ! str_contains($name, '.'), "Invalid TLD display label: {$code}.");
            $this->require(idn_to_ascii($name, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46) === $code, "Mismatching TLD Unicode label: {$code}.");
            $country = null;
            if ($record['type'] === 'country-code') {
                if (str_starts_with($code, 'xn--')) {
                    $this->require(isset($record['iso3166_code']) && is_string($record['iso3166_code']) && preg_match('/^[A-Z]{2}$/D', $record['iso3166_code']) === 1, "Missing IDN country assignment: {$code}.");
                    $country = $record['iso3166_code'];
                } else {
                    $country = $code === 'uk' ? 'GB' : strtoupper($code);
                }
                if (! isset($countries[$country])) {
                    $country = null;
                }
            }
            $tlds[$code] = ['name' => $name, 'type' => $record['type'], 'countryCode' => $country];
        }
        $this->require(isset($tlds['de'], $tlds['com'], $tlds['uk']), 'Incomplete TLD data.');
        $this->require(count($tlds) >= 1000, 'Suspiciously incomplete TLD data; review before changing the import guard.');
        ksort($countryContinents);
        ksort($countryLanguages);
        ksort($extraLanguages);
        ksort($tlds);

        return [
            'schema' => 1,
            'meta' => $source['meta'],
            'continents' => $continents,
            'countryContinents' => $countryContinents,
            'countryLanguages' => $countryLanguages,
            'extraLanguages' => $extraLanguages,
            'tlds' => $tlds,
        ];
    }

    private function expand(string $region, array $containment, array $ancestors = []): array
    {
        $this->require(! in_array($region, $ancestors, true), 'Cyclic territory containment.');
        if (! isset($containment[$region])) {
            return [$region];
        }
        $this->require(is_array($containment[$region]), "Invalid region: {$region}.");
        $countries = [];
        foreach ($containment[$region] as $child) {
            $this->require(is_string($child), "Invalid child region of {$region}.");
            array_push($countries, ...$this->expand($child, $containment, [...$ancestors, $region]));
        }

        return array_values(array_unique($countries));
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new InvalidArgumentException($message);
        }
    }
}
