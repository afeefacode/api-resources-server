<?php

namespace Afeefa\ApiResources\Type;

class TypeMeta
{
    public $TypeClassOrClasses = [];
    public bool $link = false;
    public bool $list = false;

    public function typeClassOrClassesOrMeta($TypeClassOrClassesOrMeta): TypeMeta
    {
        if ($TypeClassOrClassesOrMeta instanceof TypeMeta) {
            $typeMeta = $TypeClassOrClassesOrMeta;
            $this->TypeClassOrClasses = $typeMeta->TypeClassOrClasses;
            $this->link = $typeMeta->link;
            $this->list = $typeMeta->list;
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
}
