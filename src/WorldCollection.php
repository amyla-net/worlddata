<?php

namespace Amyla\Worlddata;

use Amyla\Worlddata\Exceptions\ItemNotFoundException;
use Amyla\Worlddata\Items\Item;
use Illuminate\Support\Collection;

/**
 * @template TKey of array-key
 * @template TValue
 *
 * @extends Collection<TKey, TValue>
 */
class WorldCollection extends Collection
{
    /** @return TValue|null */
    public function find(string $code): mixed
    {
        foreach ($this->items as $item) {
            if ($item instanceof Item && $item->matches(trim($code))) {
                return $item;
            }
        }

        return null;
    }

    /** @return TValue */
    public function findOrFail(string $code): mixed
    {
        return $this->find($code) ?? throw new ItemNotFoundException($code);
    }
}
