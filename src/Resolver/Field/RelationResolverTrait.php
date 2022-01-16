<?php

namespace Afeefa\ApiResources\Resolver\Field;

use Afeefa\ApiResources\Field\Relation;

trait RelationResolverTrait
{
    protected Relation $relation;

    public function relation(Relation $relation): self
    {
        $this->relation = $relation;
        return $this;
    }

    public function getRelation(): Relation
    {
        return $this->relation;
    }

    public function getResolveParams(): array
    {
        return $this->relation->getResolveParams();
    }

    public function getResolveParam(string $name)
    {
        return $this->relation->getResolveParam($name);
    }
}
