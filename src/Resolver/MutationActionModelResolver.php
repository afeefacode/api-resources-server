<?php

namespace Afeefa\ApiResources\Resolver;

use Afeefa\ApiResources\Api\NotFoundException;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Exception\Exceptions\MissingCallbackException;
use Afeefa\ApiResources\Model\ModelInterface;
use Afeefa\ApiResources\Resolver\Mutation\BaseMutationActionResolver;
use Closure;

class MutationActionModelResolver extends BaseMutationActionResolver
{
    protected ?Closure $beforeResolveCallback = null;

    protected ?Closure $afterResolveCallback = null;

    protected ?Closure $getCallback = null;

    protected ?Closure $addCallback = null;

    protected ?Closure $updateCallback = null;

    protected ?Closure $deleteCallback = null;

    public function beforeResolve(Closure $callback): self
    {
        $this->beforeResolveCallback = $callback;
        return $this;
    }

    public function afterResolve(Closure $callback): self
    {
        $this->afterResolveCallback = $callback;
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

    protected function _resolve(): array
    {
        // if errors

        [$resourceType, $actionName] = $this->getResourceAndActionNames();
        $mustReturn = "callback of mutation resolver for action {$actionName} on resource {$resourceType} must return";
        $needsToImplement = "Resolver for action {$actionName} on resource {$resourceType} needs to implement";

        if ($this->beforeResolveCallback) {
            $params = $this->request->getParams();
            $fieldsToSave = $this->request->getFieldsToSave();
            $result = ($this->beforeResolveCallback)($params, $fieldsToSave);

            if (!is_array($result) || !array_is_list($result) || count($result) !== 2) {
                throw new InvalidConfigurationException("BeforeResolve {$mustReturn} an array of [params, fieldsToSave].");
            }

            [$params, $fieldsToSave] = $result;
            if (!is_array($params) || !($fieldsToSave === null || is_array($fieldsToSave))) {
                throw new InvalidConfigurationException("BeforeResolve {$mustReturn} an array of [params, fieldsToSave].");
            }

            $this->request->params($params);
            $this->request->fieldsToSave($fieldsToSave);
        }

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

        $input = $this->getInput();
        if ($input->isUnion() && !$this->request->hasParam('type')) {
            throw new InvalidConfigurationException('Must specify a type in the payload of the union action {$actionName} on resource {$resourceType}');
        };

        $typeName = $input->isUnion() ? $this->request->getParam('type') : $input->getTypeClass()::type();
        $id = $this->request->getParam('id', null);

        if ($id) {
            $existingModel = ($this->getCallback)($id, $typeName);
            if (!$existingModel) {
                throw new NotFoundException('Model not found.');
            }
            if (!$existingModel instanceof ModelInterface) {
                throw new InvalidConfigurationException("Get {$mustReturn} a ModelInterface object or null.");
            }
        }

        $fieldsToSave = $this->request->getFieldsToSave();

        if ($existingModel && $fieldsToSave === null) { // delete
            ($this->deleteCallback)($existingModel);

            $this->_afterResolve(null);
        } elseif ($fieldsToSave !== null) { // do not add/update model if not existings && fields=null
            $model = $this->resolveModel(
                $existingModel,
                $typeName,
                $fieldsToSave,
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

            $this->_afterResolve($model);

            // forward if present

            if ($this->forwardCallback) {
                $request = $this->getRequest();
                ($this->forwardCallback)($request, $model);
                return $request->dispatch();
            }
        } else { // not existing and null passed
            $this->_afterResolve(null);
        }

        return [
            'data' => $model
        ];
    }

    private function _afterResolve(?ModelInterface $model): void
    {
        if ($this->afterResolveCallback) {
            ($this->afterResolveCallback)($model);
        }
    }
}
