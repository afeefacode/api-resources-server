<?php

namespace Afeefa\ApiResources\Resolver\Mutation;

use Closure;

trait MutationRelationResolverTrait
{
    protected ?Closure $getCallback = null;

    public function get(Closure $callback): self
    {
        $this->getCallback = $callback;
        return $this;
    }
}
