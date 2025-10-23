<?php

namespace Afeefa\ApiResources\Api;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\DI\ContainerAwareInterface;
use Afeefa\ApiResources\DI\ContainerAwareTrait;
use Afeefa\ApiResources\DI\DependencyResolver;
use Afeefa\ApiResources\Exception\Exceptions\ApiException;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Field\Fields\DateAttribute;
use Afeefa\ApiResources\Resolver\Action\BaseActionResolver;
use Afeefa\ApiResources\Resource\Resource;
use Afeefa\ApiResources\Type\TypeClassMap;
use Afeefa\ApiResources\Validator\ValidationFailedException;
use Carbon\Carbon;
use DateTimeZone;
use JsonSerializable;

class ApiRequest implements ContainerAwareInterface, ToSchemaJsonInterface, JsonSerializable
{
    use ContainerAwareTrait;

    protected Api $api;

    protected string $resourceType;

    protected string $actionName;

    protected array $filters = [];

    protected array $params = [];

    protected array $fields = [];

    protected ?array $fieldsToSave = [];

    public function fromInput(?array $input = null): ApiRequest
    {
        $input ??= json_decode(file_get_contents('php://input'), true);

        $this->resourceType = $input['resource'] ?? '';
        if (!$this->resourceType) {
            throw new ApiException('No resource field given.');
        }

        $this->actionName = $input['action'] ?? '';
        if (!$this->actionName) {
            throw new ApiException('No action field field given.');
        }

        // todo validate params
        $this->params = $input['params'] ?? [];

        // todo validate filters
        $this->filters = $input['filters'] ?? [];

        $this->fields = $input['fields'] ?? [];

        // todo validate data
        if (array_key_exists('data', $input)) {
            $this->fieldsToSave($input['data']);
        }

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

    public function getApi(): Api
    {
        return $this->api;
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

    public function getParam(string $name, $default = null)
    {
        $param = $this->params[$name] ?? $default;

        if ($this->getAction()->hasParam($name)) {
            if ($this->getAction()->getParam($name)::class === DateAttribute::class) {
                if ($param) {
                    $param = Carbon::parse($param);
                    $tz = date_default_timezone_get();
                    $param->setTimezone(new DateTimeZone($tz));
                }
            }
        }

        return $param;
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

    public function getFilter(string $name, $default = null)
    {
        if ($default === null) {
            $default = $this->getAction()->getFilter($name)->getDefaultValue();
        }
        return $this->filters[$name] ?? $default;
    }

    public function fields(array $fields): ApiRequest
    {
        $this->fields = $fields;
        return $this;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function fieldsToSave($fields): ApiRequest
    {
        // validate fields
        $actionName = $this->getAction()->getName();
        $resourceType = $this->getResource()::type();
        if ($fields !== null && !is_array($fields)) {
            throw new ValidationFailedException("Data passed to the mutation action {$actionName} on resource {$resourceType} must be an array or null.");
        }

        $this->fieldsToSave = $fields;
        return $this;
    }

    public function getFieldsToSave(): ?array
    {
        return $this->fieldsToSave;
    }

    public function dispatch(): array
    {
        $action = $this->getAction();

        // find and register all necessary types
        $this->container->get(TypeClassMap::class)
            ->overrideTypes($this->api->getOverriddenTypes())
            ->createUsedTypesForAction($action);

        $resolveCallback = $action->getResolve();

        /** @var BaseActionResolver */
        $actionResolver = null;

        $this->container->call(
            $resolveCallback,
            function (DependencyResolver $r) {
                if ($r->isOf(BaseActionResolver::class)) {
                    $r->create();
                }
            },
            function () use (&$actionResolver) {
                $arguments = func_get_args();
                foreach ($arguments as $argument) {
                    if ($argument instanceof BaseActionResolver) {
                        $actionResolver = $argument;
                    }
                }
            }
        );

        if (!$actionResolver) {
            throw new InvalidConfigurationException("Resolve callback for action {$this->actionName} on resource {$this->resourceType} must receive an ActionResolver as argument.");
        }

        $result = $actionResolver
            ->request($this)
            ->resolve();

        if ($this->api->getDebug()) {
            $result['__input'] = json_decode(file_get_contents('php://input'));
            $result['__request'] = $this;
        }

        return $result;
    }

    public function toSchemaJson(): array
    {
        $json = [
            'api' => $this->api::type(),
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

    public function jsonSerialize(): mixed
    {
        $json = [
            'api' => $this->api::type(),
            'resource' => $this->resourceType,
            'action' => $this->actionName,
            'params' => $this->params,
            'filters' => $this->filters,
            'fields' => $this->fields,
            'fieldsToSave' => $this->fieldsToSave ?? []
        ];
        return $json;
    }

    public function toArray(): array
    {
        return $this->jsonSerialize();
    }
}
