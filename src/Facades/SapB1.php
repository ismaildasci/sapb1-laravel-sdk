<?php

declare(strict_types=1);

namespace SapB1\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \SapB1\SapB1
 */
class SapB1 extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \SapB1\SapB1::class;
    }
}
