<?php

namespace Afeefa\ApiResources\Type;

class TypeMeta
{
    public $TypeClassOrClasses = [];
    public bool $link = false;
    public bool $list = false;
    public bool $create = false;
    public bool $update = false;

    public function typeClassOrClassesOrMeta($TypeClassOrClassesOrMeta): TypeMeta
    {
        if ($TypeClassOrClassesOrMeta instanceof TypeMeta) {
            $typeMeta = $TypeClassOrClassesOrMeta;
            $this->TypeClassOrClasses = $typeMeta->TypeClassOrClasses;
            $this->link = $typeMeta->link;
            $this->list = $typeMeta->list;
            $this->create = $typeMeta->create;
            $this->update = $typeMeta->update;
        } else {
            $this->TypeClassOrClasses = $TypeClassOrClassesOrMeta;
        }

        return $this;
    }

    public function list($list = true): TypeMeta
    {
        $this->list = $list;
        return $this;
    }

    public function link($link = true): TypeMeta
    {
        $this->link = $link;
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
