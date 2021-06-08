<?php

namespace Afeefa\ApiResources\Resource;

use Afeefa\ApiResources\Bag\Bag;
use function Afeefa\ApiResources\DI\classOrCallback;
use function Afeefa\ApiResources\DI\getCallbackArgumentType;

/**
 * @method Resource get(string $name)
 * @method Resource[] entries()
 */
class ResourceBag extends Bag
{
    protected array $definitions = [];

    public function add($classOrCallback): ResourceBag
    {
        [$ResourceClass, $callback] = classOrCallback($classOrCallback);
        if ($callback) {
            $ResourceClass = getCallbackArgumentType($callback);
        }

        $this->setDefinition($ResourceClass::$type, $classOrCallback);
        return $this;
    }
}
