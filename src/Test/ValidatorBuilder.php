<?php

namespace Afeefa\ApiResources\Test;

use Afeefa\ApiResources\Validator\Rule\RuleBag;
use Afeefa\ApiResources\Validator\Validator;
use Closure;
use Exception;
use Webmozart\PathUtil\Path;

class ValidatorBuilder extends Builder
{
    public Validator $validator;

    public function validator(
        ?string $typeName = null,
        ?Closure $rulesCallback = null
    ): ValidatorBuilder {
        // creating unique anonymous class is difficult
        // https://stackoverflow.com/questions/40833199/static-properties-in-php7-anonymous-classes
        // https://www.php.net/language.oop5.anonymous#121839
        $code = file_get_contents(Path::join(__DIR__, 'class-templates', 'validator.php'));
        $code = preg_replace("/<\?php/", '', $code);

        if ($typeName) {
            $code = preg_replace('/Test.Validator/', $typeName, $code);
        } else {
            // remove type information for no type given tests
            $code = preg_replace('/protected static string \$type.+/', '', $code);
        }

        /** @var TestValidator */
        $validator = eval($code); // eval is not always evil

        $validator::$rulesCallback = $rulesCallback;

        $this->validator = $validator;

        return $this;
    }

    public function get(): Validator
    {
        return $this->container->create($this->validator::class);
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
            (static::$rulesCallback)($rules);
        }
    }
}
