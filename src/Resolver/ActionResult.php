<?php

namespace Afeefa\ApiResources\Resolver;

class ActionResult
{
    public $data = null;
    public array $meta = [];

    public function data($data): ActionResult
    {
        $this->data = $data;
        return $this;
    }

    public function meta(array $meta): ActionResult
    {
        $this->meta = $meta;
        return $this;
    }
}
