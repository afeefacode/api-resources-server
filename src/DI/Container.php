<?php

namespace Afeefa\ApiResources\DI;

use Afeefa\ApiResources\Exception\Exceptions\MissingCallbackArgumentException;
use Afeefa\ApiResources\Exception\Exceptions\MissingTypeHintException;
use Afeefa\ApiResources\Exception\Exceptions\NotACallbackException;
use Afeefa\ApiResources\Exception\Exceptions\NotATypeOrCallbackException;
use Afeefa\ApiResources\Exception\Exceptions\TooManyCallbackArgumentsException;
use Closure;
use Psr\Container\ContainerInterface;
use ReflectionFunction;
use ReflectionNamedType;

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
        [$TypeClass, $callback] = $this->classOrCallback($classOrCallback);
        if ($TypeClass) {
            $Types = [$TypeClass];
        } else {
            $Types = $this->getCallbackArgumentTypes($callback);
            if (!count($Types)) {
                throw new MissingCallbackArgumentException('Get callback does not provide arguments.');
            }
        }

        $arguments = [];
        foreach ($Types as $TypeClass) {
            $instance = null;
            if (!$this->has($TypeClass)) {
                $definition = $this->config[$TypeClass] ?? null;
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
        $TypeClasses = $this->getCallbackArgumentTypes($callback);
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
        [$TypeClass, $callback] = $this->classOrCallback($classOrCallback);
        if ($callback) {
            $TypeClasses = $this->getCallbackArgumentTypes($classOrCallback);
            if (!count($TypeClasses)) {
                throw new MissingCallbackArgumentException('Create callback does not provide an argument.');
            } elseif (count($TypeClasses) > 1) {
                throw new TooManyCallbackArgumentsException('Create callback may only provide 1 argument.');
            }
            $TypeClass = $TypeClasses[0];
        }

        $definition = $this->config[$TypeClass] ?? null;
        if ($definition instanceof FactoryDefinition) {
            $instance = $definition();
        } elseif ($definition instanceof $TypeClass) {
            $instance = $definition;
        } else {
            $instance = new $TypeClass();
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

    private function classOrCallback($classOrCallback): array
    {
        if ($classOrCallback instanceof Closure) {
            return [null, $classOrCallback];
        } elseif (is_callable($classOrCallback)) {
            return [null, Closure::fromCallable($classOrCallback)];
        }

        if (!is_string($classOrCallback) || !class_exists($classOrCallback)) {
            throw new NotATypeOrCallbackException('Argument is not a type nor a valid callback.');
        }

        return [$classOrCallback, null];
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
        $TypeClasses = $this->getCallbackArgumentTypes($callback);
        return (count($TypeClasses) === 1 && $TypeClasses[0] === DependencyResolver::class);
    }

    private function register(string $TypeClass, object $instance)
    {
        if (!isset($this->entries[$TypeClass])) {
            $this->entries[$TypeClass] = $instance;
        }
    }

    private function getCallbackArgumentTypes(Closure $callback): array
    {
        $argumentTypes = [];

        $f = new ReflectionFunction($callback);
        $params = $f->getParameters();

        foreach ($params as $param) {
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType) {
                $argumentTypes[] = $type->getName();
                continue;
            }
            throw new MissingTypeHintException("Callback variable \${$param->getName()} does provide a type hint.");
        }

        return $argumentTypes;
    }
}
