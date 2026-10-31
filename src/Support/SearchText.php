<?php

namespace Amyla\Worlddata\Support;

use InvalidArgumentException;
use Normalizer;

/** @internal */
final class SearchText
{
    public static function normalize(string $value): string
    {
        $normalized = Normalizer::normalize(trim($value), Normalizer::FORM_D);
        if ($normalized === false) {
            throw new InvalidArgumentException('Search text must be valid UTF-8.');
        }

        return mb_convert_case(preg_replace('/\p{Mn}+/u', '', $normalized) ?? '', MB_CASE_FOLD, 'UTF-8');
    }
}
