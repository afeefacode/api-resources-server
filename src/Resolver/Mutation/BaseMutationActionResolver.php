<?php

namespace Afeefa\ApiResources\Resolver\Mutation;

use Afeefa\ApiResources\Action\ActionInput;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Resolver\Action\BaseActionResolver;
use Closure;

class BaseMutationActionResolver extends BaseActionResolver
{
    use MutationResolverTrait;

    protected MutationResolveContext $resolveContext;

    protected ?Closure $transactionCallback = null;

    protected ?Closure $forwardCallback = null;

    public function transaction(Closure $callback): static
    {
        $this->transactionCallback = $callback;
        return $this;
    }

    public function forward(Closure $callback): static
    {
        $this->forwardCallback = $callback;
        return $this;
    }

    public function resolve(): array
    {
        return $this->wrapInTransaction(function () {
            return $this->_resolve();
        });
    }

    protected function _resolve(): array
    {
        return [];
    }

    protected function wrapInTransaction(Closure $execute): array
    {
        if ($this->transactionCallback) {
            $result = ($this->transactionCallback)($execute);
            if (!is_array($result) || !array_key_exists('data', $result)) {
                [$resourceType, $actionName] = $this->getResourceAndActionNames();
                $mustReturn = "callback of mutation resolver for action {$actionName} on resource {$resourceType} must return";
                throw new InvalidConfigurationException("Transaction {$mustReturn} a result array with at least a data field.");
            }
            return $result;
        }
        return $execute();
    }

    protected function getResourceAndActionNames(): array
    {
        $action = $this->request->getAction();
        $actionName = $action->getName();
        $resourceType = $this->request->getResource()::type();
        return [$resourceType, $actionName];
    }

    protected function getInput(): ActionInput
    {
        $action = $this->request->getAction();
        return $action->getInput();
    }
}
