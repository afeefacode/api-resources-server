<?php

namespace Afeefa\ApiResources\DB;

class TypeClassMap
{
    protected array $map = [];

    public function add(string $type, string $TypeClass): void
    {
        $this->map[$type] = $TypeClass;
    }

    public function get(string $type): ?string
    {
        return $this->map[$type] ?? null;
    }
}
