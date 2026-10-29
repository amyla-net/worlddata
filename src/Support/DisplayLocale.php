<?php

namespace Amyla\Worlddata\Support;

use Symfony\Component\Intl\Locales;

/** @internal */
final class DisplayLocale
{
    /** @var list<string> */
    public readonly array $candidates;

    public readonly string $locale;

    public function __construct(string $locale, string $fallback)
    {
        $candidates = [];
        foreach ([$locale, $fallback, 'en'] as $value) {
            $value = \Locale::canonicalize(str_replace('-', '_', $value)) ?? '';
            while ($value !== '') {
                $candidates[] = $value;
                $value = str_contains($value, '_') ? substr($value, 0, (int) strrpos($value, '_')) : '';
            }
        }
        $this->candidates = array_values(array_unique($candidates));
        $supported = array_flip(Locales::getLocales());
        foreach ($this->candidates as $candidate) {
            if (isset($supported[$candidate])) {
                $this->locale = $candidate;

                return;
            }
        }
        $this->locale = 'en';
    }

    /** @param array<string, string> $names */
    public function name(array $names): string
    {
        foreach ($this->candidates as $candidate) {
            if (isset($names[$candidate])) {
                return $names[$candidate];
            }
        }

        return $names['en'];
    }
}
