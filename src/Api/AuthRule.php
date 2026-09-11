<?php

namespace Afeefa\ApiResources\Api;

use Afeefa\ApiResources\DI\Container;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Closure;

use function Afeefa\ApiResources\DI\getCallbackArgumentTypes;

/**
 * A single registered authorization closure.
 *
 * @internal Consumers register rules via Api::authorize() and apply them via
 * Authorizator::applyAuthorize().
 */
class AuthRule
{
    public function __construct(
        protected Closure $closure
    ) {
    }

    public function getClosure(): Closure
    {
        return $this->closure;
    }

    /**
     * Runs the closure with the given context as first argument; every further
     * parameter is taken from the container.
     *
     * The context deliberately does not travel through the container:
     * Container::register() keeps the first instance registered for a class, so
     * a second path within the same request would receive the context of the
     * first one. Container::call() builds all arguments itself and is therefore
     * not usable either.
     */
    public function call(AuthContext $context, Container $container): void
    {
        $TypeClasses = getCallbackArgumentTypes($this->closure);

        if (count($TypeClasses) === 0) { // rule does not care about the context
            ($this->closure)();
            return;
        }

        $ContextClass = $TypeClasses[0];

        // Compare types before invoking: asking the container for the context
        // class instead would silently hand out an empty instance of it.
        if (!$context instanceof $ContextClass) {
            throw new InvalidConfigurationException(
                'Authorize closure expects a context of type ' . $ContextClass
                . ', but the current path provides a ' . $context::class . '.'
            );
        }

        $arguments = [$context];
        foreach (array_slice($TypeClasses, 1) as $TypeClass) {
            $arguments[] = $container->get($TypeClass);
        }

        ($this->closure)(...$arguments);
    }
}
