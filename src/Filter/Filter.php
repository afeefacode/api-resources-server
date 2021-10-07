<?php

namespace Afeefa\ApiResources\Filter;

use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Api\ApiRequest;
use Afeefa\ApiResources\Bag\BagEntry;
use Afeefa\ApiResources\Exception\Exceptions\MissingTypeException;
use Closure;

class Filter extends BagEntry
{
    public static string $type;

    protected string $name;

    protected array $options;

    protected Closure $optionsRequestCallback;

    protected $default;

    protected bool $defaultValueSet = false;

    public function created(): void
    {
        if (!static::$type) {
            throw new MissingTypeException('Missing type for filter of class ' . static::class . '.');
        };

        $this->setup();
    }

    public function optionsRequest(Closure $callback): Filter
    {
        $this->optionsRequestCallback = $callback;
        return $this;
    }

    public function name(string $name): Filter
    {
        $this->name = $name;
        return $this;
    }

    public function default($default): Filter
    {
        $this->default = $default;
        $this->defaultValueSet = true;
        return $this;
    }

    public function hasDefaultValue(): bool
    {
        return $this->defaultValueSet;
    }

    public function getDefaultValue()
    {
        return $this->default;
    }

    public function options(array $options): Filter
    {
        $this->options = $options;
        return $this;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function toSchemaJson(): array
    {
        $json = [
            'type' => static::$type
        ];

        if ($this->defaultValueSet) {
            $json['default'] = $this->default;
        }

        if (isset($this->options)) {
            $json['options'] = $this->options;
        }

        if (isset($this->optionsRequestCallback)) {
            $api = $this->container->get(Api::class);
            $request = $this->container->create(function (ApiRequest $request) use ($api) {
                $request->api($api);
                ($this->optionsRequestCallback)($request);
            });
            $json['options_request'] = $request->toSchemaJson();
        }

        return $json;
    }

    protected function setup(): void
    {
    }
}
