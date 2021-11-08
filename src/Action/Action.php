<?php

namespace Afeefa\ApiResources\Action;

use Afeefa\ApiResources\Bag\BagEntry;
use Afeefa\ApiResources\DB\TypeClassMap;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Exception\Exceptions\NotACallbackException;
use Afeefa\ApiResources\Exception\Exceptions\NotATypeException;
use Afeefa\ApiResources\Field\Attribute;
use Afeefa\ApiResources\Filter\Filter;
use Afeefa\ApiResources\Filter\FilterBag;
use Afeefa\ApiResources\Type\TypeMeta;
use Closure;

class Action extends BagEntry
{
    protected string $name;

    protected ActionParams $params;

    protected ActionInput $input;

    protected FilterBag $filters;

    protected ActionResponse $response;

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
        $this->input = $this->container->create(ActionInput::class);
        $this->initInputOrResponse($this->input, $TypeClassOrClassesOrMeta, $callback);
        return $this;
    }

    public function hasInput(): bool
    {
        return isset($this->input);
    }

    public function getInput(): ?ActionInput
    {
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
        $this->response = $this->container->create(ActionResponse::class);
        $this->initInputOrResponse($this->response, $TypeClassOrClassesOrMeta, $callback);
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

        if (is_callable($callback)) {
            return Closure::fromCallable($callback);
        } elseif ($callback instanceof Closure) {
            return $callback;
        }

        throw new NotACallbackException("Resolve callback for action {$this->name} is not callable.");
    }

    public function toSchemaJson(): array
    {
        if (!isset($this->response)) {
            throw new InvalidConfigurationException("Action {$this->name} does not have a response type.");
        }

        if (!isset($this->resolveCallback)) {
            throw new InvalidConfigurationException("Action {$this->name} does not have a resolver.");
        }

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

        $json['response'] = $this->response->toSchemaJson();

        return $json;
    }

    protected function initInputOrResponse(ActionResponse $inputOrResponse, $TypeClassOrClassesOrMeta, Closure $callback = null)
    {
        $valueFor = $inputOrResponse instanceof ActionInput ? 'input' : 'response';

        if ($TypeClassOrClassesOrMeta instanceof TypeMeta) {
            $typeMeta = $TypeClassOrClassesOrMeta;
            $TypeClassOrClasses = $typeMeta->TypeClassOrClasses;

            $inputOrResponse->list($typeMeta->list);

            if ($inputOrResponse instanceof ActionInput) {
                $inputOrResponse
                    ->create($typeMeta->create)
                    ->update($typeMeta->update);
            }
        } else {
            $TypeClassOrClasses = $TypeClassOrClassesOrMeta;
        }

        if (is_array($TypeClassOrClasses)) {
            foreach ($TypeClassOrClasses as $TypeClass) {
                if (!class_exists($TypeClass)) {
                    throw new NotATypeException("Value for {$valueFor} \$TypeClassOrClasses is not a list of types.");
                }
            }
            $inputOrResponse->typeClasses($TypeClassOrClasses);
        } elseif (is_string($TypeClassOrClasses)) {
            if (!class_exists($TypeClassOrClasses)) {
                throw new NotATypeException("Value for {$valueFor} \$TypeClassOrClasses is not a type.");
            }
            $inputOrResponse->typeClass($TypeClassOrClasses);
        } else {
            throw new NotATypeException("Value for {$valueFor} \$TypeClassOrClasses is not a type or a list of types.");
        }

        if ($callback) {
            $callback($inputOrResponse);
        }

        $TypeClasses = is_array($TypeClassOrClasses) ? $TypeClassOrClasses : [$TypeClassOrClasses];
        $this->container->get(function (TypeClassMap $typeClassMap) use ($TypeClasses) {
            foreach ($TypeClasses as $TypeClass) {
                $typeClassMap->add($TypeClass::type(), $TypeClass);
            }
        });
    }
}
