<?php

namespace Amyla\Worlddata\Items;

use Amyla\Worlddata\World;
use Amyla\Worlddata\WorldCollection;
use Symfony\Component\Intl\Currencies;

final class Currency extends Item
{
    public readonly string $symbol;

    public readonly int $fractionDigits;

    public readonly int $roundingIncrement;

    public readonly int $cashFractionDigits;

    public readonly int $cashRoundingIncrement;

    public function __construct(World $world, string $code, string $name)
    {
        parent::__construct($world, $code, $name);
        $this->symbol = Currencies::getSymbol($code, $world->displayLocale());
        $this->fractionDigits = Currencies::getFractionDigits($code);
        $this->roundingIncrement = Currencies::getRoundingIncrement($code);
        $this->cashFractionDigits = Currencies::getCashFractionDigits($code);
        $this->cashRoundingIncrement = Currencies::getCashRoundingIncrement($code);
    }

    /** @return WorldCollection<string, Country> */
    public function countries(): WorldCollection
    {
        return $this->world->relatedCountries('currencies', $this->code);
    }

    public function toArray(): array
    {
        return parent::toArray() + [
            'symbol' => $this->symbol,
            'fractionDigits' => $this->fractionDigits,
            'roundingIncrement' => $this->roundingIncrement,
            'cashFractionDigits' => $this->cashFractionDigits,
            'cashRoundingIncrement' => $this->cashRoundingIncrement,
        ];
    }
}
