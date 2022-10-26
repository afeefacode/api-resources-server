<?php

namespace Afeefa\ApiResources\Test;

use Afeefa\ApiResources\Type\Type;
use Afeefa\ApiResources\Type\TypeClassMap;
use Closure;
use Symfony\Component\Filesystem\Path;

class TypeBuilder extends Builder
{
    public Type $type;

    public function type(
        ?string $typeName = null,
        ?Closure $fieldsCallback = null,
        ?Closure $updateFieldsCallback = null,
        ?Closure $createFieldsCallback = null
    ): static {
        // creating unique anonymous class is difficult
        // https://stackoverflow.com/questions/40833199/static-properties-in-php7-anonymous-classes
        // https://www.php.net/language.oop5.anonymous#121839
        $code = file_get_contents($this->getClassTemplateName());
        $code = preg_replace("/<\?php/", '', $code);

        if ($typeName) {
            $code = preg_replace('/Test.Type/', $typeName, $code);
        } else {
            // remove type information for no type given tests
            $code = preg_replace('/protected static string \$type.+/', '', $code);
        }

        $code = $this->handleClassCode($code);

        /* @var TestType */
        $type = eval($code); // eval is not always evil

        $type::$fieldsCallback = $fieldsCallback;
        $type::$updateFieldsCallback = $updateFieldsCallback;
        $type::$createFieldsCallback = $createFieldsCallback;

        $this->type = $type;

        return $this;
    }

    public function get(bool $register = false): Type
    {
        $type = $this->container->get($this->type::class); // create and register single instance
        if ($register) {
            $this->container->get(TypeClassMap::class)->add($type::class);
        }
        return $type;
    }

    protected function getClassTemplateName(): string
    {
        return Path::join(__DIR__, 'class-templates', 'type.php');
    }

    protected function handleClassCode(string $code): string
    {
        return $code;
    }
}

class TestType extends Type
{
    use TypeBuilderTrait;
}
