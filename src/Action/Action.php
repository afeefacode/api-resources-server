<?php

namespace Afeefa\ApiResources\Action;

use Afeefa\ApiResources\Bag\BagEntry;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Exception\Exceptions\NotACallbackException;
use Afeefa\ApiResources\Exception\Exceptions\NotATypeException;
use Afeefa\ApiResources\Filter\Filter;
use Afeefa\ApiResources\Filter\FilterBag;
use Closure;

class Action extends BagEntry
{
    protected string $name;

    protected ActionParams $params;

    protected ActionInput $input;

    protected FilterBag $filters;

    protected ActionResponse $response;

    protected Closure $executor;

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

    public function input(string $TypeClass, Closure $callback = null): Action
    {
        if (!class_exists($TypeClass)) {
            throw new NotATypeException('Value for input $TypeClass is not a type.');
        }

        $this->input = $this->container->create(ActionInput::class);

        $this->input->typeClass($TypeClass);

        if ($callback) {
            $callback($this->input);
        }

        return $this;
    }

    public function getInput(): ActionInput
    {
        return $this->input;
    }

    public function filters(Closure $callback): Action
    {
        $this->container->create($callback, function (FilterBag $filters) {
            $this->filters = $filters;
        });
        return $this;
    }

    public function getFilter(string $name): Filter
    {
        return $this->filters->get($name);
    }

    public function response($TypeClassOrClasses, Closure $callback = null): Action
    {
        $this->response = $this->container->create(ActionResponse::class);

        if (is_array($TypeClassOrClasses)) {
            foreach ($TypeClassOrClasses as $TypeClass) {
                if (!class_exists($TypeClass)) {
                    throw new NotATypeException('Value for response $TypeClassOrClasses is not a list of types.');
                }
            }
            $this->response->types($TypeClassOrClasses);
        } elseif (is_string($TypeClassOrClasses)) {
            if (!class_exists($TypeClassOrClasses)) {
                throw new NotATypeException('Value for response $TypeClassOrClasses is not a type.');
            }
            $this->response->typeClass($TypeClassOrClasses);
        } else {
            throw new NotATypeException('Value for response $TypeClassOrClasses is not a type or a list of type.');
        }

        if ($callback) {
            $callback($this->response);
        }

        return $this;
    }

    public function getResponse(): ActionResponse
    {
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

    public function getResolve(): ?Closure
    {
        $callback = $this->resolveCallback;

        if (!$callback) {
            throw new InvalidConfigurationException("Action {$this->name} does not have a field resolver.");
        }

        if (is_array($callback) && is_string($callback[0])) { // static class -> create instance
            $callback[0] = $this->container->create($callback[0]);
        }

        if (is_callable($callback)) {
            return Closure::fromCallable($callback);
        } elseif ($callback instanceof Closure) {
            return $callback;
        }

        throw new NotACallbackException("Resolve callback for action {$this->name} is not callable.");
    }

    public function toSchemaJson(): array
    {
        $json = [
            // 'name' => $this->name
        ];

        if (isset($this->params)) {
            $json['params'] = $this->params->toSchemaJson();
        }

        if (isset($this->input)) {
            $json['input'] = $this->input->toSchemaJson();
        }

        if (isset($this->filters)) {
            $json['filters'] = $this->filters->toSchemaJson();
        }

        if (isset($this->response)) {
            $json['response'] = $this->response->toSchemaJson();
        }

        return $json;
    }
}
