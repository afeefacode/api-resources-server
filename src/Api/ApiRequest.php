<?php

namespace Afeefa\ApiResources\Api;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\DB\ActionResolver;
use Afeefa\ApiResources\DI\ContainerAwareInterface;
use Afeefa\ApiResources\DI\ContainerAwareTrait;
use Afeefa\ApiResources\DI\DependencyResolver;
use Afeefa\ApiResources\Exception\Exceptions\ApiException;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Resource\Resource;
use JsonSerializable;

class ApiRequest implements ContainerAwareInterface, ToSchemaJsonInterface, JsonSerializable
{
    use ContainerAwareTrait;

    protected Api $api;

    protected string $resourceType;

    protected string $actionName;

    protected array $filters = [];

    protected array $params = [];

    protected RequestedFields $fields;

    protected FieldsToSave $fieldsToSave;

    public function fromInput(): ApiRequest
    {
        $input = json_decode(file_get_contents('php://input'), true);

        $this->resourceType = $input['resource'] ?? '';
        if (!$this->resourceType) {
            throw new ApiException('No resource field');
        }

        $this->actionName = $input['action'] ?? '';
        if (!$this->actionName) {
            throw new ApiException('No action field');
        }

        $this->params = $input['params'] ?? [];

        $this->filters = $input['filters'] ?? [];

        $this->fields($input['fields'] ?? []);

        $this->fieldsToSave($input['data'] ?? []);

        return $this;
    }

    public function resourceType(string $resourceType): ApiRequest
    {
        $this->resourceType = $resourceType;
        return $this;
    }

    public function api(Api $api): ApiRequest
    {
        $this->api = $api;
        return $this;
    }

    public function actionName(string $actionName): ApiRequest
    {
        $this->actionName = $actionName;
        return $this;
    }

    public function getResource(): Resource
    {
        return $this->api->getResource($this->resourceType);
    }

    public function getAction(): Action
    {
        return $this->api->getAction($this->resourceType, $this->actionName);
    }

    public function param(string $name, string $value): ApiRequest
    {
        $this->params[$name] = $value;
        return $this;
    }

    public function params(array $params): ApiRequest
    {
        $this->params = $params;
        return $this;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function hasParam(string $name): bool
    {
        return isset($this->params[$name]);
    }

    public function getParam(string $name)
    {
        return $this->params[$name];
    }

    public function filter(string $name, string $value): ApiRequest
    {
        $this->filters[$name] = $value;
        return $this;
    }

    public function filters(array $filters): ApiRequest
    {
        foreach ($filters as $name => $value) {
            $this->filters[$name] = $value;
        }
        return $this;
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function fields(array $fields): ApiRequest
    {
        $this->fields = $this->container->create(function (RequestedFields $requestedFields) use ($fields) {
            $TypeClass = $this->getAction()->getResponse()->getTypeClass();
            $requestedFields
                ->typeClass($TypeClass)
                ->fields($fields);
        });

        return $this;
    }

    public function getFields(): RequestedFields
    {
        return $this->fields;
    }

    public function fieldsToSave(array $fields): ApiRequest
    {
        $this->fieldsToSave = $this->container->create(function (FieldsToSave $fieldsToSave) use ($fields) {
            $TypeClass = $this->getAction()->getResponse()->getTypeClass();
            $operation = $this->hasParam('id') ? Operation::UPDATE : Operation::CREATE;

            $fieldsToSave
                ->typeClass($TypeClass)
                ->operation($operation)
                ->fields($fields);

            if ($operation === Operation::UPDATE) {
                $fieldsToSave->id($this->getParam('id'));
            }
        });

        return $this;
    }

    public function getFieldsToSave(): FieldsToSave
    {
        return $this->fieldsToSave;
    }

    public function dispatch()
    {
        if (!isset($this->fields)) {
            $this->fields([]);
        }

        $action = $this->getAction();
        $resolveCallback = $action->getResolve();

        $actionResolver = null;

        $this->container->call(
            $resolveCallback,
            function (DependencyResolver $r) {
                if ($r->isOf(ActionResolver::class)) {
                    $r->create();
                }
            },
            function () use (&$actionResolver) {
                $arguments = func_get_args();
                foreach ($arguments as $argument) {
                    if ($argument instanceof ActionResolver) {
                        $actionResolver = $argument;
                    }
                }
            }
        );

        if (!$actionResolver) {
            throw new InvalidConfigurationException("Resolve callback for action {$this->actionName} on type {$this->resourceType} must receive an ActionResolver as argument.");
        }

        return $actionResolver
            ->action($action)
            ->request($this)
            ->resolve();
    }

    public function toSchemaJson(): array
    {
        $json = [
            'api' => $this->api::$type,
            'resource' => $this->resourceType,
            'action' => $this->actionName,
            'fields' => $this->fields
        ];

        if (count($this->params)) {
            $json['params'] = $this->params;
        }

        if (count($this->filters)) {
            $json['filters'] = $this->filters;
        }

        return $json;
    }

    public function jsonSerialize()
    {
        $json = [
            'api' => $this->api::$type,
            'resource' => $this->resourceType,
            'action' => $this->actionName,
            'params' => $this->params,
            'filters' => $this->filters,
            'fields' => $this->fields,
            'fieldsToSave' => $this->fieldsToSave
        ];
        return $json;
    }
}
