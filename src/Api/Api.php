<?php

namespace Afeefa\ApiResources\Api;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\DB\TypeClassMap;
use Afeefa\ApiResources\DI\ContainerAwareInterface;
use Afeefa\ApiResources\DI\ContainerAwareTrait;
use Afeefa\ApiResources\Exception\Exceptions\MissingTypeException;
use Afeefa\ApiResources\Resource\Resource;
use Afeefa\ApiResources\Resource\ResourceBag;
use Closure;

class Api implements ContainerAwareInterface
{
    use ContainerAwareTrait;
    use ToSchemaJsonTrait;

    public static string $type;

    protected ResourceBag $resources;

    public function created(): void
    {
        if (!static::$type) {
            throw new MissingTypeException('Missing type for api of class ' . static::class . '.');
        };

        $this->container->registerAlias($this, self::class);

        $this->resources = $this->container->create(ResourceBag::class);
        $this->resources($this->resources);
    }

    public function getResource(string $resourceType): Resource
    {
        return $this->resources->get($resourceType);
    }

    public function getAction(string $resourceType, string $actionName): Action
    {
        $resource = $this->resources->get($resourceType);
        return $resource->getAction($actionName);
    }

    public function request(Closure $callback)
    {
        /** @var ApiRequest */
        $request = $this->container->get(ApiRequest::class);
        $request->api($this);
        $callback($request);
        return $request->dispatch();
    }

    public function requestFromInput()
    {
        /** @var ApiRequest */
        $request = $this->container->get(ApiRequest::class);
        $request->api($this);
        $request->fromInput();
        return $request->dispatch();
    }

    public function getSchemaJson(TypeRegistry $typeRegistry, TypeClassMap $typeClassMap): array
    {
        $resources = $this->resources->toSchemaJson();

        // $typeRegistry->dumpEntries();
        // debug_dump($typeClassMap);
        // $this->container->dumpEntries();

        $types = [];
        foreach ($typeRegistry->getTypeClasses() as $TypeClass) {
            $type = $this->container->get($TypeClass);
            $types[$type::$type] = $type->toSchemaJson();
        }

        $validators = [];
        foreach ($typeRegistry->validators() as $ValidatorClass) {
            $validator = $this->container->get($ValidatorClass);
            $validators[$validator::$type] = $validator->toSchemaJson();
        }

        return [
            'type' => static::$type,
            'resources' => $resources,
            'types' => $types,
            'validators' => $validators
            // 'fields' => $fields,
            // 'relations' => $relations,
        ];
    }

    protected function resources(ResourceBag $resources): void
    {
    }
}
