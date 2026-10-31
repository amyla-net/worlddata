<?php

namespace Amyla\Worlddata\Rules;

use Amyla\Worlddata\Items\Language;
use Amyla\Worlddata\World;
use Amyla\Worlddata\WorldCollection;

final class LanguageCode extends CodeRule
{
    /** @return WorldCollection<string, Language> */
    protected function items(World $world): WorldCollection
    {
        return $world->languages();
    }
}
