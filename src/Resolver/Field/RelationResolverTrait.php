<?php

namespace Afeefa\ApiResources\Resolver\Field;

use Afeefa\ApiResources\Field\Relation;

trait RelationResolverTrait
{
    protected Relation $relation;

    public function relation(Relation $relation): static
    {
        $this->relation = $relation;
        return $this;
    }

    public function getRelation(): Relation
    {
        return $this->relation;
    }

    public function getResolveParam(string $name)
    {
        return $this->relation->getResolveParam($name);
    }
}
