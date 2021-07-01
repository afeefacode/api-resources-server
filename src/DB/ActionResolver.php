<?php

namespace Afeefa\ApiResources\DB;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Api\ApiRequest;
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

    public function action(Action $action): ActionResolver
    {
        $this->action = $action;
        return $this;
    }

    public function getAction(): Action
    {
        return $this->action;
    }

    public function load(Closure $callback): ActionResolver
    {
        $this->loadCallback = $callback;
        return $this;
    }

    public function fetch(): array
    {
        $requestedFields = $this->request->getFields();

        $resolveContext = $this
            ->resolveContext()
            ->requestedFields($requestedFields);

        // query db

        $loadCallback = $this->loadCallback;
        $modelOrModels = $loadCallback($resolveContext);

        $isList = is_array($modelOrModels);
        $hasResult = $isList && count($modelOrModels) || $modelOrModels;

        if ($hasResult) {
            $models = $isList ? $modelOrModels : [$modelOrModels];

            // resolve attributes

            foreach ($resolveContext->getAttributeResolvers() as $attributeResolver) {
                foreach ($models as $model) {
                    $attributeResolver->addOwner($model);
                }
                $attributeResolver->fetch();
            }

            // resolve relations

            foreach ($resolveContext->getRelationResolvers() as $relationResolver) {
                foreach ($models as $model) {
                    $relationResolver->addOwner($model);
                }
                $relationResolver->fetch();
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

    public function forward(Closure $callback): array
    {
        $request = $this->getRequest();
        $callback($this->getRequest());
        return $request->dispatch();
    }
}
