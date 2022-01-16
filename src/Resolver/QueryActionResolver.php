<?php

namespace Afeefa\ApiResources\Resolver;

use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Exception\Exceptions\MissingCallbackException;
use Afeefa\ApiResources\Model\ModelInterface;
use Afeefa\ApiResources\Resolver\Action\BaseActionResolver;
use Afeefa\ApiResources\Resolver\Query\QueryResolverTrait;

class QueryActionResolver extends BaseActionResolver
{
    use QueryResolverTrait;

    protected array $meta = [];

    public function meta(array $meta): QueryActionResolver
    {
        $this->meta = $meta;
        return $this;
    }

    public function getSelectFields(?string $typeName = null): array
    {
        $action = $this->request->getAction();
        $actionName = $action->getName();
        $resourceType = $this->request->getResource()::type();

        $typeName = $this->validateRequestedType(
            $action->getResponse(),
            $typeName,
            "You need to pass a type name to getSelectFields() in the resolver of action {$actionName} on resource {$resourceType} since the action returns an union type.",
            "The type name passed to getSelectFields() in the resolver of action {$actionName} on resource {$resourceType} is not supported by the action."
        );

        return $this->getResolveContext($typeName, $this->request->getFields())
            ->getSelectFields($typeName);
    }

    public function resolve(): array
    {
        $action = $this->request->getAction();

        // if errors

        $actionName = $action->getName();
        $resourceType = $this->request->getResource()::type();
        $mustReturn = "Load callback of action resolver for action {$actionName} on resource {$resourceType} must return";

        // query db

        if (!$this->loadCallback) {
            throw new MissingCallbackException("Action resolver for action {$actionName} on resource {$resourceType} must provide a load callback.");
        }

        $modelOrModels = ($this->loadCallback)();

        $models = [];
        $data = null;

        if ($action->getResponse()->isList()) {
            if (!is_array($modelOrModels)) {
                throw new InvalidConfigurationException("{$mustReturn} an array of ModelInterface objects.");
            }
            foreach ($modelOrModels as $model) {
                if (!$model instanceof ModelInterface) {
                    throw new InvalidConfigurationException("{$mustReturn} an array of ModelInterface objects.");
                }
                if (!$action->getResponse()->allowsType($model->apiResourcesGetType())) {
                    $allowedTypeNames = implode(',', $action->getResponse()->getAllTypeNames());
                    throw new InvalidConfigurationException("{$mustReturn} an array of ModelInterface objects of type [{$allowedTypeNames}].");
                }
            }
            $models = $modelOrModels;
            $data = array_values($modelOrModels);
        } else {
            if ($modelOrModels !== null) {
                if (!$modelOrModels instanceof ModelInterface) {
                    throw new InvalidConfigurationException("{$mustReturn} a ModelInterface object.");
                }
                if (!$action->getResponse()->allowsType($modelOrModels->apiResourcesGetType())) {
                    $allowedTypeNames = implode(',', $action->getResponse()->getAllTypeNames());
                    throw new InvalidConfigurationException("{$mustReturn} a ModelInterface object of type [{$allowedTypeNames}].");
                }
            }
            $models = $modelOrModels ? [$modelOrModels] : [];
            $data = $modelOrModels;
        }

        $this->resolveModels($models, $this->request->getFields());

        return [
            'data' => $data,
            'meta' => $this->meta,
            'input' => json_decode(file_get_contents('php://input'), true),
            'request' => $this->request
        ];
    }

    protected function calculateRequestedFields(?string $typeName = null): array
    {
        $action = $this->request->getAction();
        $actionName = $action->getName();
        $resourceType = $this->request->getResource()::type();

        $typeName = $this->validateRequestedType(
            $action->getResponse(),
            $typeName,
            "You need to pass a type name to getRequestedFields() in the resolver of action {$actionName} on resource {$resourceType} since the action returns an union type.",
            "The type name passed to getRequestedFields() in the resolver of action {$actionName} on resource {$resourceType} is not supported by the action."
        );

        return $this->getResolveContext($typeName, $this->request->getFields())
            ->getRequestedFields();
    }
}
