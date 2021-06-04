<?php

namespace Afeefa\ApiResources\Api;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\DB\ActionResolver;
use Afeefa\ApiResources\DI\ContainerAwareInterface;
use Afeefa\ApiResources\DI\ContainerAwareTrait;
use Afeefa\ApiResources\DI\DependencyResolver;
use Afeefa\ApiResources\Exception\Exceptions\ApiException;
use JsonSerializable;

class ApiRequest implements ContainerAwareInterface, ToSchemaJsonInterface, JsonSerializable
{
    use ContainerAwareTrait;

    protected Api $api;

    protected string $resourceName;

    protected string $actionName;

    protected array $filters = [];

    protected array $params = [];

    protected array $data = [];

    protected RequestedFields $fields;

    public function fromInput(): ApiRequest
    {
        $input = json_decode(file_get_contents('php://input'), true);

        $this->resourceName = $input['resource'] ?? '';
        if (!$this->resourceName) {
            throw new ApiException('No resource field');
        }

        $this->actionName = $input['action'] ?? '';
        if (!$this->actionName) {
            throw new ApiException('No action field');
        }

        $this->fields($input['fields'] ?? []);

        $this->filters = $input['filters'] ?? [];

        $this->params = $input['params'] ?? [];

        $this->data = $input['data'] ?? [];

        return $this;
    }

    public function resourceName(string $resourceName): ApiRequest
    {
        $this->resourceName = $resourceName;
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

    public function getAction(): Action
    {
        return $this->api->getAction($this->resourceName, $this->actionName);
    }

    public function filter(string $name, string $value): ApiRequest
    {
        $this->filters[$name] = $value;
        return $this;
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function getParam(string $name)
    {
        return $this->params[$name];
    }

    public function getData(): array
    {
        $data = $this->data;
        unset($data['id']);
        unset($data['type']);

        if (isset($data['author'])) {
            $data['author_id'] = $data['author']['id'];
            unset($data['author']);
        }

        return $data;
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

    public function dispatch()
    {
        if (!isset($this->fields)) {
            $this->fields([]);
        }

        $action = $this->getAction();

        $actionResolver = $this->container->create(function (ActionResolver $actionResolver) use ($action) {
            $actionResolver
                ->action($action)
                ->request($this);
        });

        $resolveCallback = $action->getResolve();

        $this->container->call(
            $resolveCallback,
            function (DependencyResolver $r) use ($actionResolver) {
                if ($r->isOf(ActionResolver::class)) {
                    $r->fix($actionResolver);
                }
            }
        );

        return $actionResolver->fetch();
    }

    public function toSchemaJson(): array
    {
        $json = [
            'resource' => $this->resourceName,
            'action' => $this->actionName,
            'fields' => $this->fields
        ];

        if (count($this->filters)) {
            $json['filters'] = $this->filters;
        }

        return $json;
    }

    public function jsonSerialize()
    {
        $json = [
            'resource' => $this->resourceName,
            'action' => $this->actionName,
            'fields' => $this->fields,
            'filters' => $this->filters,
            'params' => $this->params,
            'data' => $this->data,
        ];
        return $json;
    }
}
