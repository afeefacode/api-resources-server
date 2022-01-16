<?php

namespace Afeefa\ApiResources\Resolver;

use Afeefa\ApiResources\Api\Operation;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Exception\Exceptions\MissingCallbackException;
use Afeefa\ApiResources\Model\ModelInterface;
use Afeefa\ApiResources\Resolver\Mutation\BaseMutationActionResolver;
use Afeefa\ApiResources\Resolver\Mutation\MutationRelationHasResolverTrait;
use Closure;

class MutationActionModelResolver extends BaseMutationActionResolver
{
    use MutationRelationHasResolverTrait;

    protected ?Closure $getCallback = null;

    protected ?Closure $addCallback = null;

    protected ?Closure $updateCallback = null;

    protected ?Closure $deleteCallback = null;

    public function get(Closure $callback): self
    {
        $this->getCallback = $callback;
        return $this;
    }

    public function update(Closure $callback): self
    {
        $this->updateCallback = $callback;
        return $this;
    }

    public function add(Closure $callback): self
    {
        $this->addCallback = $callback;
        return $this;
    }

    public function delete(Closure $callback): self
    {
        $this->deleteCallback = $callback;
        return $this;
    }

    public function resolve(): array
    {
        $action = $this->request->getAction();
        $resolveContext = $this->getResolveContext2();

        // if errors

        $actionName = $action->getName();
        $resourceType = $this->request->getResource()::type();
        $mustReturn = "callback of mutation resolver for action {$actionName} on resource {$resourceType} must return";
        $needsToImplement = "Resolver for action {$actionName} on resource {$resourceType} needs to implement";

        if (!$this->getCallback) {
            throw new MissingCallbackException("{$needsToImplement} a get() method.");
        }

        if (!$this->addCallback) {
            throw new MissingCallbackException("{$needsToImplement} an add() method.");
        }

        if (!$this->updateCallback) {
            throw new MissingCallbackException("{$needsToImplement} an update() method.");
        }

        if (!$this->deleteCallback) {
            throw new MissingCallbackException("{$needsToImplement} a delete() method.");
        }

        // get model (if update)

        /** @var ModelInterface */
        $existingModel = null;
        /** @var ModelsInterface */
        $model = null;

        $input = $action->getInput();

        if ($input->isUnion() && !$this->request->hasParam('type')) {
            throw new InvalidConfigurationException('Must specify a type in the payload of the union action {$actionName} on resource {$resourceType}');
        };

        $id = $this->request->getParam('id', null);
        $typeName = $input->isUnion() ? $this->request->getParam('type') : $input->getTypeClass()::type();

        if ($id) {
            $existingModel = ($this->getCallback)($id, $typeName);
            if ($existingModel !== null && !$existingModel instanceof ModelInterface) {
                throw new InvalidConfigurationException("Update {$mustReturn} a ModelInterface object.");
            }
        }

        $operation = Operation::CREATE;
        if ($existingModel) {
            $operation = Operation::UPDATE;
        }

        // resolve relation before owner (save related to owner, linkOne or hasOne)

        foreach ($resolveContext->getRelationResolvers() as $relationResolver) {
            if ($relationResolver->shouldSaveRelatedToOwner()) {
                $relationResolver->operation($operation);

                if ($existingModel) {
                    $relationResolver->addOwner($existingModel);
                }

                $relationResolver->resolve(); // model to save in the owner

                $this->relatedSaveFields = $relationResolver->getSaveRelatedToOwnerFields();
            }
        }

        // save model

        if ($existingModel) {
            $model = ($this->updateCallback)($existingModel, $resolveContext->getSaveFields());
            if (!$model instanceof ModelInterface) {
                throw new InvalidConfigurationException("Update {$mustReturn} a ModelInterface object.");
            }
        } else {
            $model = ($this->addCallback)();
            if (!$model instanceof ModelInterface) {
                throw new InvalidConfigurationException("Add {$mustReturn} a ModelInterface object.");
            }
        }

        // save relations after owner

        foreach ($resolveContext->getRelationResolvers() as $relationResolver) {
            if ($relationResolver->shouldSaveRelatedToOwner()) {
                continue; // already resolved
            }

            $ownerSaveFields = [];

            // save owner field to related

            if ($relationResolver->shouldSaveOwnerToRelated()) {
                $ownerSaveFields = $relationResolver->getSaveOwnerToRelatedFields(
                    $model->apiResourcesGetId(),
                    $model->apiResourcesGetType()
                );
            }

            $relationResolver
                ->operation($operation)
                ->addOwner($model)
                ->ownerSaveFields($ownerSaveFields)
                ->resolve();
        }

        // forward if present

        if ($this->forwardCallback) {
            $request = $this->getRequest();
            ($this->forwardCallback)($request, $model);
            return $request->dispatch();
        }

        return [
            'data' => $model,
            'input' => json_decode(file_get_contents('php://input'), true),
            'request' => $this->request
        ];
    }
}
