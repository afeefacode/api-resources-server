<?php

namespace Afeefa\ApiResources\Resolver;

use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Exception\Exceptions\MissingCallbackException;
use Afeefa\ApiResources\Model\ModelInterface;
use Afeefa\ApiResources\Resolver\Mutation\BaseMutationActionResolver;
use Afeefa\ApiResources\Resolver\Mutation\MutationResolveContext;
use Closure;

class MutationActionSimpleResolver extends BaseMutationActionResolver
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

        // save model

        $input = $this->getInput();
        if ($input->isUnion() && !$this->request->hasParam('type')) {
            throw new InvalidConfigurationException('Must specify a type in the payload of the union action {$actionName} on resource {$resourceType}');
        };

        $typeName = $input->isUnion() ? $this->request->getParam('type') : $input->getTypeClass()::type();

        $resolveContext = $this->container->create(MutationResolveContext::class)
            ->type($this->getTypeByName($typeName))
            ->fieldsToSave($this->request->getFieldsToSave());

        $model = ($this->saveCallback)($resolveContext->getSaveFields());
        if ($model !== null && !$model instanceof ModelInterface) {
            throw new InvalidConfigurationException("Save {$mustReturn} a ModelInterface object or null.");
        }

        // forward if present

        if ($this->forwardCallback) {
            $request = $this->getRequest();
            ($this->forwardCallback)($request, $model);
            return $request->dispatch();
        }

        return $this->returnResponse($model);
    }

    protected function returnResponse(?ModelInterface $model = null): array
    {
        return [
            'data' => $model,
            'input' => json_decode(file_get_contents('php://input'), true),
            'request' => $this->request
        ];
    }
}
