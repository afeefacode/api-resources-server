<?php

namespace Afeefa\ApiResources\DI;

use Afeefa\ApiResources\Exception\Exceptions\NotATypeException;
use Closure;
use Psr\Container\ContainerInterface;

class Container implements ContainerInterface
{
    private array $entries = [];

    private array $config = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->register(static::class, $this);
    }

    /**
     * Returns a container entry and creates and adds it, if it not exists
     */
    public function get(string $TypeClass): object
    {
        if ($this->has($TypeClass)) {
            return $this->entries[$TypeClass];
        }

        $definition = $this->config[$TypeClass] ?? null;

        // if a single instance is set via config, register and return this instance
        if ($definition instanceof $TypeClass) {
            $this->register($TypeClass, $definition);
            return $definition;
        }

        return $this->createInstance($TypeClass, null, true);
    }

    public function has(string $TypeClass): bool
    {
        return isset($this->entries[$TypeClass]);
    }

    /**
     * Creates a class but does not add it to the container
     */
    public function create($classOrCallback, ?Closure $resolveCallback = null): object
    {
        return $this->createInstance($classOrCallback, $resolveCallback);
    }

    /**
     * Gets singletons from the container by callback argument types, calls the callback, and returns its result.
     */
    public function call(callable $callback): mixed
    {
        if (!($callback instanceof Closure)) {
            $callback = Closure::fromCallable($callback);
        }

        $Types = getCallbackArgumentTypes($callback, 1); // min 1 max *

        $arguments = [];
        foreach ($Types as $TypeClass) {
            $arguments[] = $this->get($TypeClass);
        }

        return $callback(...$arguments);
    }

    /**
     * Returns all container entries
     */
    public function entries(): array
    {
        return $this->entries;
    }

    public function registerAlias(object $instance, string $alias): void
    {
        $this->register($alias, $instance);
    }

    public function dumpEntries($sort = false)
    {
        $dump = array_column(
            array_map(function ($key, $entry) {
                return [$key, get_class($entry)];
            }, array_keys($this->entries), $this->entries),
            1,
            0
        );

        if ($sort) {
            sort($dump);
        }

        debug_dump($dump);
    }

    private function createInstance($classOrCallback, ?Closure $resolveCallback = null, $register = false): object
    {
        [$TypeClass, $callback] = classOrCallback($classOrCallback);
        if ($callback) { // callback and no type class given
            $TypeClasses = getCallbackArgumentTypes($classOrCallback, 1, 1); // min 1 max 1
            $TypeClass = $TypeClasses[0];
        }

        if (!class_exists($TypeClass)) { // possibly interface
            throw new NotATypeException("{$TypeClass} can not be instantiated to create a new instance.");
        }
        $instance = new $TypeClass();

        if ($instance instanceof ContainerAwareInterface) {
            $instance->container($this);
        }

        if ($register) {
            $this->register($TypeClass, $instance);
        }

        if ($instance instanceof ContainerAwareInterface) {
            $instance->created();
        }

        if ($callback) {
            $callback($instance);
        }

        if ($resolveCallback) {
            $resolveCallback($instance);
        }

        return $instance;
    }

    private function register(string $TypeClass, object $instance)
    {
        if (!isset($this->entries[$TypeClass])) {
            $this->entries[$TypeClass] = $instance;
        }
    }
}
