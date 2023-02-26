<?php

namespace Afeefa\ApiResources\Resolver;

use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Exception\Exceptions\MissingCallbackException;
use Afeefa\ApiResources\Model\ModelInterface;
use Afeefa\ApiResources\Resolver\Mutation\BaseMutationActionResolver;
use Afeefa\ApiResources\Resolver\Mutation\MutationResolveContext;
use Closure;

class MutationActionResolver extends BaseMutationActionResolver
{
    protected ?Closure $saveCallback = null;

    public function save(Closure $callback): self
    {
        $this->saveCallback = $callback;
        return $this;
    }

    protected function _resolve(): array
    {
        // if errors

        [$resourceType, $actionName] = $this->getResourceAndActionNames();
        $mustReturn = "callback of mutation resolver for action {$actionName} on resource {$resourceType} must return";
        $needsToImplement = "Resolver for action {$actionName} on resource {$resourceType} needs to implement";

        if (!$this->saveCallback) {
            throw new MissingCallbackException("{$needsToImplement} a save() method.");
        }

        $action = $this->request->getAction();

        $result = null;

        if ($action->hasInput()) { // requires an input type
            $input = $this->getInput();
            if ($input->isUnion() && !$this->request->hasParam('type')) {
                throw new InvalidConfigurationException('Must specify a type in the payload of the union action {$actionName} on resource {$resourceType}');
            };

            $typeName = $input->isUnion() ? $this->request->getParam('type') : $input->getTypeClass()::type();

            $resolveContext = $this->container->create(MutationResolveContext::class)
                ->type($this->getTypeByName($typeName))
                ->fieldsToSave($this->request->getFieldsToSave());

            $result = ($this->saveCallback)($this->getRequest(), $resolveContext->getSaveFields());
        } else {
            $result = ($this->saveCallback)($this->getRequest());
        }

        if ($action->hasResponse()) {
            if ($result !== null && !$result instanceof ModelInterface) {
                throw new InvalidConfigurationException("Save {$mustReturn} a ModelInterface object or null.");
            }
        }

        // forward if present

        if ($this->forwardCallback) {
            $request = $this->getRequest();
            ($this->forwardCallback)($request, $result);
            return $request->dispatch();
        }

        return [
            'data' => $result
        ];
    }
}
