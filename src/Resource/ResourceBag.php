<?php

namespace Afeefa\ApiResources\Resource;

use Afeefa\ApiResources\Bag\Bag;
use Afeefa\ApiResources\Bag\BagEntryInterface;

use function Afeefa\ApiResources\DI\classOrCallback;
use function Afeefa\ApiResources\DI\getCallbackArgumentType;

/**
 * @method Resource get(string $name, Closure $callback)
 * @method Resource[] getEntries()
 */
class ResourceBag extends Bag
{
    public function add($classOrCallback): ResourceBag
    {
        [$ResourceClass, $callback] = classOrCallback($classOrCallback);
        if ($callback) { // callback and no resource class given
            $ResourceClass = getCallbackArgumentType($callback);
        }

        $this->setDefinition($ResourceClass::type(), $classOrCallback);
        return $this;
    }

    /**
     * disabled
     */
    public function set(string $name, BagEntryInterface $value): Bag
    {
        return $this;
    }
}
