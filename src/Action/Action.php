<?php

namespace Afeefa\ApiResources\Action;

use Afeefa\ApiResources\Bag\BagEntry;
use Afeefa\ApiResources\DB\TypeClassMap;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Exception\Exceptions\NotACallbackException;
use Afeefa\ApiResources\Exception\Exceptions\NotATypeException;
use Afeefa\ApiResources\Filter\Filter;
use Afeefa\ApiResources\Filter\FilterBag;
use Afeefa\ApiResources\Type\TypeMeta;
use Closure;

class Action extends BagEntry
{
    protected string $name;

    protected ActionParams $params;

    protected ActionInput $input;

    protected FilterBag $scopes;

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

    public function input($TypeClassOrMeta, Closure $callback = null): Action
    {
        $this->input = $this->container->create(ActionInput::class);

        if ($TypeClassOrMeta instanceof TypeMeta) {
            $typeMeta = $TypeClassOrMeta;
            $TypeClass = $TypeClassOrMeta->TypeClass;

            $this->input
                ->list($typeMeta->list)
                ->create($typeMeta->create)
                ->update($typeMeta->update);
        } else {
            $TypeClass = $TypeClassOrMeta;
        }

        if (!class_exists($TypeClass)) {
            throw new NotATypeException('Value for input $TypeClass is not a type.');
        }

        $this->input->typeClass($TypeClass);

        if ($callback) {
            $callback($this->input);
        }

        $this->container->get(function (TypeClassMap $typeClassMap) use ($TypeClass) {
            $typeClassMap->add($TypeClass::$type, $TypeClass);
        });

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

    public function hasFilter(string $name): bool
    {
        return $this->filters->has($name);
    }

    public function getFilter(string $name): Filter
    {
        return $this->filters->get($name);
    }

    public function scopes(Closure $callback): Action
    {
        $this->container->create($callback, function (FilterBag $scopes) {
            $this->scopes = $scopes;
        });
        return $this;
    }

    public function hasScope(string $name): bool
    {
        return $this->scopes->has($name);
    }

    public function getScope(string $name): Filter
    {
        return $this->scopes->get($name);
    }

    public function response($TypeClassOrClassesOrMeta, Closure $callback = null): Action
    {
        $this->response = $this->container->create(ActionResponse::class);

        if ($TypeClassOrClassesOrMeta instanceof TypeMeta) {
            $typeMeta = $TypeClassOrClassesOrMeta;
            $TypeClassOrClasses = $typeMeta->TypeClass;

            $this->response->list($typeMeta->list);
        } else {
            $TypeClassOrClasses = $TypeClassOrClassesOrMeta;
        }

        if (is_array($TypeClassOrClasses)) {
            foreach ($TypeClassOrClasses as $TypeClass) {
                if (!class_exists($TypeClass)) {
                    throw new NotATypeException('Value for response $TypeClassOrClasses is not a list of types.');
                }
            }
            $this->response->typeClasses($TypeClassOrClasses);
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

        $TypeClasses = is_array($TypeClassOrClasses) ? $TypeClassOrClasses : [$TypeClassOrClasses];
        $this->container->get(function (TypeClassMap $typeClassMap) use ($TypeClasses) {
            foreach ($TypeClasses as $TypeClass) {
                $typeClassMap->add($TypeClass::$type, $TypeClass);
            }
        });

        return $this;
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

        if (isset($this->scopes)) {
            $json['scopes'] = $this->scopes->toSchemaJson();
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
