<?php

namespace Amyla\Worlddata\Exceptions;

use OutOfBoundsException;

final class ItemNotFoundException extends OutOfBoundsException
{
    public function __construct(string $code)
    {
        parent::__construct("World data item [{$code}] was not found.");
    }
}
