<?php

namespace Afeefa\ApiResources\Action;

use Afeefa\ApiResources\Bag\BagEntry;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Exception\Exceptions\NotACallbackException;
use Afeefa\ApiResources\Field\Attribute;
use Afeefa\ApiResources\Filter\Filter;
use Afeefa\ApiResources\Filter\FilterBag;
use Closure;

class Action extends BagEntry
{
    protected string $name;

    protected ActionParams $params;

    protected FilterBag $filters;

    protected ?ActionInput $input = null;

    protected ?ActionResponse $response = null;

    /**
     * @var string|callable|Closure
     */
    protected $resolveCallback;

    public function name(string $name): Action
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function params(Closure $callback): Action
    {
        $this->container->create($callback, function (ActionParams $params) {
            $this->params = $params;
        });
        return $this;
    }

    public function hasParam(string $name): bool
    {
        return isset($this->params) && $this->params->has($name);
    }

    public function getParam(string $name): Attribute
    {
        return $this->params->get($name);
    }

    public function input($TypeClassOrClassesOrMeta, Closure $callback = null): Action
    {
        $this->input = $this->container->create(ActionInput::class)
            ->initFromArgument($TypeClassOrClassesOrMeta, $callback);
        return $this;
    }

    public function hasInput(): bool
    {
        return isset($this->input);
    }

    public function getInput(): ?ActionInput
    {
        if (!isset($this->input)) {
            throw new InvalidConfigurationException("Mutation action {$this->name} does not have an input type.");
        }
        return $this->input ?? null;
    }

    public function filters(Closure $callback): Action
    {
        $this->container->create($callback, function (FilterBag $filters) {
            $this->filters = $filters;
        });
        return $this;
    }

    public function hasFilter(string $name): bool
    {
        return $this->filters->has($name);
    }

    public function getFilter(string $name): Filter
    {
        return $this->filters->get($name);
    }

    public function getFilters(): FilterBag
    {
        return $this->filters;
    }

    public function response($TypeClassOrClassesOrMeta, Closure $callback = null): Action
    {
        $this->response = $this->container->create(ActionResponse::class)
            ->initFromArgument($TypeClassOrClassesOrMeta, $callback);
        return $this;
    }

    /**
     * Useful for testing purposes
     */
    public function hasResponse(): bool
    {
        return isset($this->response);
    }

    public function getResponse(): ActionResponse
    {
        if (!isset($this->response)) {
            throw new InvalidConfigurationException("Action {$this->name} does not have a response type.");
        }
        return $this->response;
    }

    /**
     * @param string|callable|Closure $classOrCallback
     */
    public function resolve($classOrCallback): Action
    {
        $this->resolveCallback = $classOrCallback;
        return $this;
    }

    /**
     * Useful for testing purposes
     */
    public function hasResolver(): bool
    {
        return isset($this->resolveCallback);
    }

    public function getResolve(): ?Closure
    {
        $callback = $this->resolveCallback ?? null;

        if (!$callback) {
            throw new InvalidConfigurationException("Action {$this->name} does not have a resolver.");
        }

        if (is_array($callback) && is_string($callback[0])) { // static class -> create instance
            $callback[0] = $this->container->create($callback[0]);
        }

        if ($callback instanceof Closure) {
            return $callback;
        } elseif (is_callable($callback)) {
            return Closure::fromCallable($callback);
        }

        throw new NotACallbackException("Resolve callback for action {$this->name} is not callable.");
    }

    public function toSchemaJson(): array
    {
        if (!isset($this->resolveCallback)) {
            throw new InvalidConfigurationException("Action {$this->name} does not have a resolver.");
        }

        $json = [];

        if (isset($this->params)) {
            $json['params'] = $this->params->toSchemaJson();
        }

        if ($this->hasInput()) {
            $json['input'] = $this->input->toSchemaJson();
        }

        if (isset($this->filters)) {
            $json['filters'] = $this->filters->toSchemaJson();
        }

        if ($this->hasResponse()) {
            $json['response'] = $this->response->toSchemaJson();
        }

        return $json;
    }
}
