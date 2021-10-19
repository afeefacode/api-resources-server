<?php

namespace Afeefa\ApiResources\Test;

use Afeefa\ApiResources\Type\Type;
use Closure;

class TypeBuilder
{
    public Type $type;

    public function type(string $type, ?Closure $callback = null): TypeBuilder
    {
        $newType = new class() extends TestType {};
        $TypeClass = get_class($newType);
        $TypeClass::$type = $type;
        $TypeClass::$createCallback = $callback;

        $this->type = $newType;

        return $this;
    }

    public function get(): Type
    {
        return $this->type;
    }
}

class TestType extends Type
{
    public static ?Closure $createCallback;

    public function created(): void
    {
        parent::created();

        if (static::$createCallback) {
            (static::$createCallback)($this);
        }
    }

    public function attribute(string $name, $classOrCallback): TestType
    {
        $this->fields->attribute($name, $classOrCallback);
        return $this;
    }

    public function relation(string $name, string $RelatedTypeClass, $classOrCallback): TestType
    {
        $this->fields->relation($name, $RelatedTypeClass, $classOrCallback);
        return $this;
    }
}
