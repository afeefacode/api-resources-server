<?php

namespace Afeefa\ApiResources\Validator\Rule;

use Afeefa\ApiResources\Bag\Bag;

/**
 * @method Rule get(string $name)
 * @method Rule[] entries()
 */
class RuleBag extends Bag
{
    public function add(string $name): Rule
    {
        $rule = new Rule();
        $this->set($name, $rule);
        return $rule;
    }
}
