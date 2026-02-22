<?php

namespace Afeefa\ApiResources\TestV2;

use Afeefa\ApiResources\Test\Builder;
use Afeefa\ApiResources\Type\TypeClassMap;
use Afeefa\ApiResources\V2\Type;
use Closure;

class TypeBuilder extends Builder
{
    public Type $type;

    public function type(
        ?string $typeName = null,
        ?Closure $fieldsCallback = null
    ): static {
        $code = file_get_contents($this->getClassTemplateName());
        $code = preg_replace("/<\?php/", '', $code);

        if ($typeName) {
            $code = preg_replace('/Test.Type/', $typeName, $code);
        } else {
            $code = preg_replace('/protected static string \$type.+/', '', $code);
        }

        /* @var TestType */
        $type = eval($code); // eval is not always evil

        $type::$fieldsCallback = $fieldsCallback;

        $this->type = $type;

        return $this;
    }

    public function get(bool $register = false): Type
    {
        $type = $this->container->get($this->type::class);
        if ($register) {
            $this->container->get(TypeClassMap::class)->add($type::class);
        }
        return $type;
    }

    protected function getClassTemplateName(): string
    {
        return __DIR__ . '/class-templates/type.php';
    }
}

class TestType extends Type
{
    use TypeBuilderTrait;
}
