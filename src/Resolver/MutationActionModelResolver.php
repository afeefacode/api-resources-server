<?php

namespace Afeefa\ApiResources\Resolver;

use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Exception\Exceptions\MissingCallbackException;
use Afeefa\ApiResources\Model\ModelInterface;
use Afeefa\ApiResources\Resolver\Mutation\BaseMutationActionResolver;
use Closure;

class MutationActionModelResolver extends BaseMutationActionResolver
{
    protected ?Closure $transactionCallback = null;

    protected ?Closure $getCallback = null;

    protected ?Closure $addCallback = null;

    protected ?Closure $updateCallback = null;

    protected ?Closure $deleteCallback = null;

    public function transaction(Closure $callback): self
    {
        $this->transactionCallback = $callback;
        return $this;
    }

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
        return $this->wrapInTransaction(function () {
            return $this->_resolve();
        });
    }

    protected function _resolve(): array
    {
        $action = $this->request->getAction();

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
        /** @var ModelInterface */
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
                throw new InvalidConfigurationException("Get {$mustReturn} a ModelInterface object or null.");
            }
        }

        // delete

        if ($existingModel && $this->request->getFieldsToSave2() === null) {
            ($this->deleteCallback)($existingModel);
        } else {
            $model = $this->resolveModel(
                $existingModel,
                $typeName,
                $this->request->getFieldsToSave2(),
                function ($saveFields) use ($existingModel, $typeName, $mustReturn) {
                    if ($existingModel) {
                        ($this->updateCallback)($existingModel, $saveFields);
                        $model = $existingModel;
                    } else {
                        $model = ($this->addCallback)($typeName, $saveFields);
                        if (!$model instanceof ModelInterface) {
                            throw new InvalidConfigurationException("Add {$mustReturn} a ModelInterface object.");
                        }
                    }
                    return $model;
                }
            );

            // forward if present

            if ($this->forwardCallback) {
                $request = $this->getRequest();
                ($this->forwardCallback)($request, $model);
                return $request->dispatch();
            }
        }

        return [
            'data' => $model,
            'input' => json_decode(file_get_contents('php://input'), true),
            'request' => $this->request
        ];
    }

    protected function wrapInTransaction(Closure $execute): array
    {
        if ($this->transactionCallback) {
            return ($this->transactionCallback)($execute);
        }
        return $execute();
    }
}
