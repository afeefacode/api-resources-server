<?php

namespace Afeefa\ApiResources\Type;

class TypeMeta
{
    public string $TypeClass;
    public bool $list = false;
    public bool $create = false;
    public bool $update = false;

    public function typeClass(string $TypeClass): TypeMeta
    {
        $this->TypeClass = $TypeClass;
        return $this;
    }

    public function list($list = true): TypeMeta
    {
        $this->list = $list;
        return $this;
    }

    public function create($create = true): TypeMeta
    {
        $this->create = $create;
        return $this;
    }

    public function update($update = true): TypeMeta
    {
        $this->update = $update;
        return $this;
    }
}
