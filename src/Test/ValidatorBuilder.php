<?php

namespace Afeefa\ApiResources\Test;

use Afeefa\ApiResources\Validator\Rule\RuleBag;
use Afeefa\ApiResources\Validator\Validator;
use Closure;
use Exception;
use Webmozart\PathUtil\Path;

class ValidatorBuilder
{
    public Validator $validator;

    public function validator(
        string $typeName,
        ?Closure $rulesCallback = null
    ): ValidatorBuilder {
        // creating unique anonymous class is difficult
        // https://stackoverflow.com/questions/40833199/static-properties-in-php7-anonymous-classes
        // https://www.php.net/language.oop5.anonymous#121839
        $code = file_get_contents(Path::join(__DIR__, 'uniquevalidatorclass.php'));
        $code = preg_replace("/<\?php/", '', $code);

        /** @var TestValidator */
        $validator = eval($code); // eval is not always evil

        $validator::$type = $typeName;
        $validator::$rulesCallback = $rulesCallback;

        // create a new validator to apply rulesCallback
        // which otherwise wouldn't be applied to the current instance
        $this->validator = new $validator();

        return $this;
    }

    public function get(): Validator
    {
        return $this->validator;
    }
}

class TestValidator extends Validator
{
    public static ?Closure $rulesCallback;

    public function __call($name, $arguments): static
    {
        if ($this->rules->has($name)) {
            return $this->param($name, $arguments[0]);
        }

        throw new Exception("Rule {$name} not defined.");
    }

    protected function rules(RuleBag $rules): void
    {
        if (isset(static::$rulesCallback)) {
            (static::$rulesCallback)->call($this, $rules);
        }
    }
}
