<?php

namespace Afeefa\ApiResources\Type;

class TypeMeta
{
    public $TypeClassOrClasses = [];
    public bool $mixed = false;
    public bool $list = false;
    public bool $create = false;
    public bool $update = false;

    public function typeClassOrClasses($TypeClassOrClasses): TypeMeta
    {
        $this->TypeClassOrClasses = $TypeClassOrClasses;
        $this->mixed = is_array($TypeClassOrClasses);
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
