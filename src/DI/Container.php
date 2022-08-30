<?php

namespace Afeefa\ApiResources\DI;

use Afeefa\ApiResources\Exception\Exceptions\NotACallbackException;
use Afeefa\ApiResources\Exception\Exceptions\NotATypeException;
use Closure;
use Psr\Container\ContainerInterface;

class Container implements ContainerInterface
{
    private array $entries = [];

    private array $config = [];

    public function __construct(array $config = [])
    {
        foreach ($config as $key => $value) {
            if ($value instanceof Closure) {
                $config[$key] = factory($value);
            }
        }

        $this->config = $config;
        $this->register(static::class, $this);
    }

    /**
     * Returns a container entry and creates and adds it, if it not exists
     *
     * @param mixed $classOrCallback
     */
    public function get($classOrCallback, Closure $resolveCallback = null): object
    {
        [$TypeClass, $callback] = classOrCallback($classOrCallback);
        if ($TypeClass) {
            $Types = [$TypeClass];
        } else {
            $Types = getCallbackArgumentTypes($callback, 1); // min 1 max *
        }

        $arguments = [];
        foreach ($Types as $TypeClass) {
            $instance = null;
            if (!$this->has($TypeClass)) {
                $definition = $this->config[$TypeClass] ?? null;

                // if a single instance is set via config, register and return this instance
                if ($definition instanceof $TypeClass) {
                    $this->register($TypeClass, $definition);
                    return $definition;
                }

                // if get should always create a new instance, then do not register
                $register = !($definition instanceof CreateDefinition);
                $instance = $this->createInstance($TypeClass, null, $register);
            } else {
                $instance = $this->entries[$TypeClass];
            }
            $arguments[] = $instance;
        }

        if ($callback) {
            $callback(...$arguments);
        }

        if ($resolveCallback) {
            $resolveCallback(...$arguments);
        }

        return $arguments[0];
    }

    public function has(string $TypeClass): bool
    {
        return isset($this->entries[$TypeClass]);
    }

    /**
     * Creates a class but does not add it to the container
     */
    public function create($classOrCallback, Closure $resolveCallback = null): object
    {
        return $this->createInstance($classOrCallback, $resolveCallback);
    }

    /**
     * Calls a function while injecting dependencies
     */
    public function call($callback, Closure $resolveCallback = null, Closure $resolveCallback2 = null)
    {
        $callback = $this->callback($callback);
        $TypeClasses = getCallbackArgumentTypes($callback); // min 0 max *
        $resolveCallbackExpectsResolver = $resolveCallback && $this->argumentIsResolver($resolveCallback);

        $argumentsMap = array_column(
            array_map(function ($TypeClass, $index) use ($resolveCallback, $resolveCallbackExpectsResolver) {
                $instance = null;

                if ($resolveCallbackExpectsResolver) {
                    $resolver = $this->resolver()
                        ->typeClass($TypeClass)
                        ->index($index);

                    if ($resolveCallback) {
                        $resolveCallback($resolver);
                    }

                    if ($resolver->getFix()) { // fix value
                        $instance = $resolver->getFix();
                    } elseif ($resolver->shouldCreate()) { // create instance
                        $instance = $this->createInstance($TypeClass);
                        $resolver->initInstance($instance);
                    }
                }

                if (!$instance) {
                    $instance = $this->get($TypeClass);
                }

                return [$TypeClass, $instance];
            }, $TypeClasses, array_keys($TypeClasses)),
            1,
            0
        );

        $arguments = array_values($argumentsMap);

        $result = $callback(...$arguments);

        if ($resolveCallback && !$resolveCallbackExpectsResolver) {
            $resolveCallback(...$arguments);
        }

        if ($resolveCallback2) {
            $resolveCallback2(...$arguments);
        }

        return $result;
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

    private function createInstance($classOrCallback, Closure $resolveCallback = null, $register = false): object
    {
        [$TypeClass, $callback] = classOrCallback($classOrCallback);
        if ($callback) { // callback and no type class given
            $TypeClasses = getCallbackArgumentTypes($classOrCallback, 1, 1); // min 1 max 1
            $TypeClass = $TypeClasses[0];
        }

        $instance = null;

        $definition = $this->config[$TypeClass] ?? null;

        if ($definition instanceof FactoryDefinition) { // call a factory
            $instance = $definition();
        }

        if (!$instance) {
            if (!class_exists($TypeClass)) { // possibly interface
                throw new NotATypeException("{$TypeClass} can not be instantiated to create a new instance.");
            }
            $instance = new $TypeClass(); // create new instance from class
        }

        if ($definition instanceof CreateDefinition) {
            $initFunction = $definition->getInitFunction();
            if ($initFunction) {
                $this->call([$instance, $initFunction]);
            }
        }

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

    private function resolver(): DependencyResolver
    {
        return new DependencyResolver();
    }

    private function callback($callback): Closure
    {
        if ($callback instanceof Closure) {
            return $callback;
        } elseif (is_callable($callback)) {
            return Closure::fromCallable($callback);
        }
        throw new NotACallbackException('Argument is not a callback.');
    }

    private function argumentIsResolver(Closure $callback): bool
    {
        $TypeClasses = getCallbackArgumentTypes($callback); // min 0 max *
        return (count($TypeClasses) === 1 && $TypeClasses[0] === DependencyResolver::class);
    }

    private function register(string $TypeClass, object $instance)
    {
        if (!isset($this->entries[$TypeClass])) {
            $this->entries[$TypeClass] = $instance;
        }
    }
}
