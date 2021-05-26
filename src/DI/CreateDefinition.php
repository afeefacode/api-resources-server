<?php

namespace Afeefa\ApiResources\DI;

class CreateDefinition
{
    protected ?string $initFunction = null;

    public function call(string $initFunction): CreateDefinition
    {
        $this->initFunction = $initFunction;
        return $this;
    }

    public function getInitFunction(): ?string
    {
        return $this->initFunction;
    }
}
