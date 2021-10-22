<?php

namespace Afeefa\ApiResources\Test;

use Afeefa\ApiResources\Type\Type;

class TypeRegistry
{
    public static array $TypeClasses = [];

    public static function reset(): void
    {
        self::$TypeClasses = [];
    }

    public static function register(Type $type): void
    {
        if (!isset(self::$TypeClasses[$type::$type])) {
            self::$TypeClasses[$type::$type] = $type;
        }
    }

    public static function has(string $type): bool
    {
        return isset(self::$TypeClasses[$type]);
    }

    public static function get(string $type): Type
    {
        return self::$TypeClasses[$type];
    }

    public static function getOrCreate(string $type): Type
    {
        if (!isset(self::$TypeClasses[$type])) {
            self::$TypeClasses[$type] = (new TypeBuilder())->type($type)->get();
        }
        $type = self::$TypeClasses[$type];

        return $type;
    }

    public static function dump(): void
    {
        foreach (static::$TypeClasses as $type => $TypeClass) {
            debug_dump([$type, $TypeClass::class, $TypeClass::$type]);
        }
    }

    public static function count(): int
    {
        return count(self::$TypeClasses);
    }
}
