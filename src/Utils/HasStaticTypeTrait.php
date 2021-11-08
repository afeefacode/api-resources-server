<?php

namespace Afeefa\ApiResources\Utils;

use Afeefa\ApiResources\Exception\Exceptions\MissingTypeException;

trait HasStaticTypeTrait
{
    protected static string $type;

    public static function type(): string
    {
        if (!isset(static::$type)) {
            throw new MissingTypeException('Missing type for class ' . static::class . '.');
        };

        return static::$type;
    }
}
