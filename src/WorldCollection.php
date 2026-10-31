<?php

namespace Amyla\Worlddata;

use Amyla\Worlddata\Exceptions\ItemNotFoundException;
use Amyla\Worlddata\Items\Item;
use Amyla\Worlddata\Support\DisplayLocale;
use Amyla\Worlddata\Support\SearchText;
use Collator;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;

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

    /** @param list<string> $locales Additional search locales, alongside the display locale and English. */
    public function searchByName(string $query, array $locales = []): static
    {
        $needle = SearchText::normalize($query);
        $result = clone $this;
        foreach ($this->items as $key => $item) {
            if (! $item instanceof Item) {
                throw new InvalidArgumentException('Name helpers require a collection of worlddata items.');
            }
            if ($needle === '') {
                continue;
            }
            $matched = false;
            foreach ($item->searchTerms($locales) as $term) {
                if (str_contains(SearchText::normalize($term), $needle)) {
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                $result->forget($key);
            }
        }

        return $result;
    }

    public function sortByName(?string $locale = null): static
    {
        $this->assertItems();
        $first = $this->first();
        if (! $first instanceof Item) {
            return clone $this;
        }
        $display = new DisplayLocale($locale ?? $first->displayLocale(), $first->displayLocale());
        $collator = new Collator($display->locale);

        return $this->sort(function ($left, $right) use ($collator): int {
            if (! $left instanceof Item || ! $right instanceof Item) {
                throw new InvalidArgumentException('Name helpers require a collection of worlddata items.');
            }
            $comparison = $collator->compare($left->name, $right->name);
            if ($comparison === false) {
                throw new RuntimeException('Could not compare item names.');
            }

            return $comparison;
        });
    }

    private function assertItems(): void
    {
        foreach ($this->items as $item) {
            if (! $item instanceof Item) {
                throw new InvalidArgumentException('Name helpers require a collection of worlddata items.');
            }
        }
    }
}
