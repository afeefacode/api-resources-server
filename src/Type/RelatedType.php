<?php

namespace Afeefa\ApiResources\Type;

use Afeefa\ApiResources\Action\ActionResponse;

class RelatedType extends ActionResponse
{
    protected string $relationName;

    public function relationName(string $name): RelatedType
    {
        $this->relationName = $name;
        return $this;
    }

    protected function getNameForException(): string
    {
        return 'relation';
    }

    protected function getArgumentNameForException(): string
    {
        return $this->relationName;
    }
}
