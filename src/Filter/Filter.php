<?php

namespace Afeefa\ApiResources\Filter;

use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Api\ApiRequest;
use Afeefa\ApiResources\Bag\BagEntry;
use Afeefa\ApiResources\Utils\HasStaticTypeTrait;
use Closure;

class Filter extends BagEntry
{
    use HasStaticTypeTrait;

    protected string $name;

    protected array $options;

    protected Closure $optionsRequestCallback;

    protected $default;

    protected bool $defaultValueSet = false;

    public function created(): void
    {
        $this->setup();
    }

    public function name(string $name): Filter
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function default($default): Filter
    {
        $this->default = $default;
        $this->defaultValueSet = $default !== null;
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

    public function hasOption($option): bool
    {
        return isset($this->options) && in_array($option, $this->options, true);
    }

    public function getOptions(): array
    {
        return $this->options ?? [];
    }

    public function optionsRequest(Closure $callback): Filter
    {
        $this->optionsRequestCallback = $callback;
        return $this;
    }

    public function hasOptionsRequest(): bool
    {
        return isset($this->optionsRequestCallback);
    }

    public function getOptionsRequest(): ?ApiRequest
    {
        if (isset($this->optionsRequestCallback)) {
            return $this->container->create(function (ApiRequest $request) {
                $request->api($this->container->get(Api::class)); // default api
                ($this->optionsRequestCallback)($request);
            });
        }
        return null;
    }

    public function toSchemaJson(): array
    {
        $json = [
            'type' => $this::type()
        ];

        if ($this->defaultValueSet) {
            $json['default'] = $this->default;
        }

        if (isset($this->optionsRequestCallback)) {
            $request = $this->getOptionsRequest();
            $json['options_request'] = $request->toSchemaJson();
        }

        if (isset($this->options)) {
            $json['options'] = $this->options;
        }

        return $json;
    }

    protected function setup(): void
    {
    }
}
