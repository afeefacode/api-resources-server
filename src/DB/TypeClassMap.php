<?php

namespace Afeefa\ApiResources\DB;

class TypeClassMap
{
    protected array $map;

    public function __construct(array $map)
    {
        $this->map = $map;
    }

    public function getClass(string $type): ?string
    {
        return $this->map[$type] ?? null;
    }
}
