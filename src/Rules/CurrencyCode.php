<?php

namespace Amyla\Worlddata\Rules;

use Amyla\Worlddata\Items\Currency;
use Amyla\Worlddata\Items\Item;
use Amyla\Worlddata\World;
use Amyla\Worlddata\WorldCollection;

final class CurrencyCode extends CodeRule
{
    /** @param list<string> $only */
    public function __construct(array $only = [], private readonly bool $currentOnly = false)
    {
        parent::__construct($only);
    }

    /** @return WorldCollection<string, Currency> */
    protected function items(World $world): WorldCollection
    {
        return $world->currencies();
    }

    protected function accepts(Item $item): bool
    {
        return ! $this->currentOnly || ($item instanceof Currency && $item->countries()->isNotEmpty());
    }
}
