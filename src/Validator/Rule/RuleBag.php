<?php

namespace Afeefa\ApiResources\Validator\Rule;

use Afeefa\ApiResources\Bag\Bag;
use Afeefa\ApiResources\Bag\BagEntryInterface;

/**
 * @method Rule get(string $name, Closure $callback)
 * @method Rule[] getEntries()
 */
class RuleBag extends Bag
{
    public function add(string $name): Rule
    {
        $rule = new Rule();
        $this->setInternal($name, $rule);
        return $rule;
    }

    /**
     * disabled
     */
    public function set(string $name, BagEntryInterface $value): Bag
    {
        return $this;
    }
}
