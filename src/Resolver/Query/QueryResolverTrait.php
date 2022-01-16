<?php

namespace Afeefa\ApiResources\Resolver\Query;

use Afeefa\ApiResources\Action\ActionResponse;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Closure;

trait QueryResolverTrait
{
    protected array $requestedFields = [];

    protected ?Closure $loadCallback = null;

    public function load(Closure $callback): self
    {
        $this->loadCallback = $callback;
        return $this;
    }

    public function getRequestedFields(?string $typeName = null): array
    {
        if (!isset($this->requestedFields[$typeName])) {
            $this->requestedFields[$typeName] = $this->calculateRequestedFields($typeName);
        }

        return $this->requestedFields[$typeName];
    }

    public function getRequestedFieldNames(?string $typeName = null): array
    {
        return array_keys($this->getRequestedFields($typeName));
    }

    public function fieldIsRequested(string $fieldName, ?string $typeName = null): bool
    {
        return array_key_exists($fieldName, $this->getRequestedFields($typeName));
    }

    protected function calculateRequestedFields(?string $typeName = null): array
    {
        return [];
    }

    protected function getResolveContext(string $typeName, array $fields): QueryResolveContext
    {
        if (!isset($this->resolveContexts[$typeName])) {
            $this->resolveContexts[$typeName] = $this->container->create(function (QueryResolveContext $resolveContext) use ($typeName, $fields) {
                $resolveContext
                    ->type($this->getTypeByName($typeName))
                    ->fields($fields);
            });
        }

        return $this->resolveContexts[$typeName];
    }

    protected function validateRequestedType(ActionResponse $response, ?string $typeName, string $noTypeMessage, string $wrongTypeMessage): string
    {
        if ($response->isUnion()) {
            if (!$typeName) {
                throw new InvalidConfigurationException($noTypeMessage);
            }
        } else {
            $typeName ??= $response->getTypeClass()::type();
        }

        if (!$response->allowsType($typeName)) {
            throw new InvalidConfigurationException($wrongTypeMessage);
        }

        return $typeName;
    }

    protected function resolveModels(array $models, array $fields): void
    {
        $modelsByType = $this->sortModelsByType($models);

        foreach ($modelsByType as $typeName => $models) {
            $resolveContext = $this->getResolveContext($typeName, $fields);

            // resolve attributes

            foreach ($resolveContext->getAttributeResolvers() as $attributeResolver) {
                $attributeResolver->addOwners($models);
                $attributeResolver->resolve();
            }

            // resolve relations

            foreach ($resolveContext->getRelationResolvers() as $relationResolver) {
                $relationResolver->addOwners($models);
                $relationResolver->resolve();
            }

            // mark visible fields

            $getRequestedFieldNames = $this->getRequestedFieldNames($typeName);
            foreach ($models as $model) {
                $visibleFields = ['id', 'type', ...$getRequestedFieldNames];
                $model->apiResourcesSetVisibleFields($visibleFields);
            }
        }
    }
}
