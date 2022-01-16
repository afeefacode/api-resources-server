<?php

namespace Afeefa\ApiResources\DB;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Api\ApiRequest;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Model\ModelInterface;
use Closure;

class ActionResolver extends DataResolver
{
    protected Action $action;

    protected ApiRequest $request;

    protected Closure $loadCallback;

    public function request(ApiRequest $request): ActionResolver
    {
        $this->request = $request;
        return $this;
    }

    public function getRequest(): ApiRequest
    {
        return $this->request;
    }

    public function load(Closure $callback): ActionResolver
    {
        $this->loadCallback = $callback;
        return $this;
    }

    public function resolve(): array
    {
        $requestedFields = $this->request->getFields();

        $resolveContext = $this
            ->resolveContext()
            ->requestedFields($requestedFields);

        $action = $this->request->getAction();

        // if errors

        $actionName = $action->getName();
        $resourceType = $this->request->getResource()::type();
        $mustReturn = "Load callback of action resolver for action {$actionName} on resource {$resourceType} must return";

        // query db

        if (!isset($this->loadCallback)) {
            throw new InvalidConfigurationException("Action resolver for action {$actionName} on resource {$resourceType} must provide a load callback.");
        }

        $modelOrModels = ($this->loadCallback)($resolveContext);
        /** @var ModelInterface[] */
        $models = [];
        $hasResult = false;
        $isList = false;

        if ($action->getResponse()->isList()) {
            if (!is_array($modelOrModels)) {
                throw new InvalidConfigurationException("{$mustReturn} an array of ModelInterface objects.");
            }
            foreach ($modelOrModels as $model) {
                if (!$model instanceof ModelInterface) {
                    throw new InvalidConfigurationException("{$mustReturn} an array of ModelInterface objects.");
                }
            }
            $models = $modelOrModels;
            $hasResult = count($models) > 0;
            $isList = true;
        } else {
            if ($modelOrModels !== null && !$modelOrModels instanceof ModelInterface) {
                throw new InvalidConfigurationException("{$mustReturn} a ModelInterface object or null.");
            }
            $models = $modelOrModels ? [$modelOrModels] : [];
            $hasResult = !!$modelOrModels;
        }

        if ($hasResult) {
            // resolve attributes

            foreach ($resolveContext->getAttributeResolvers() as $attributeResolver) {
                foreach ($models as $model) {
                    $attributeResolver->addOwner($model);
                }
                $attributeResolver->resolve();
            }

            // resolve relations

            foreach ($resolveContext->getRelationResolvers() as $relationResolver) {
                foreach ($models as $model) {
                    $relationResolver->addOwner($model);
                }
                $relationResolver->resolve();
            }

            // mark visible fields

            $this->container->create(function (VisibleFields $visibleFields) use ($models, $requestedFields) {
                $visibleFields
                    ->requestedFields($requestedFields)
                    ->makeVisible($models);
            });
        }

        return [
            'data' => $isList ? array_values($modelOrModels) : $modelOrModels,
            'meta' => $resolveContext->getMeta(),
            'input' => json_decode(file_get_contents('php://input'), true),
            'request' => $this->request
        ];
    }
}
