<?php

namespace Afeefa\ApiResources\Resolver\Mutation;

use Afeefa\ApiResources\Resolver\Action\BaseActionResolver;
use Closure;

class BaseMutationActionResolver extends BaseActionResolver
{
    use MutationResolverTrait;

    protected MutationResolveContext $resolveContext;

    protected ?Closure $forwardCallback = null;

    protected array $relatedSaveFields = [];

    public function forward(Closure $callback): BaseMutationActionResolver
    {
        $this->forwardCallback = $callback;
        return $this;
    }

    public function resolve(): array
    {
        return [];
    }
}
