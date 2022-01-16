<?php

namespace Afeefa\ApiResources\Test;

use Afeefa\ApiResources\DB\TypeClassMap;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Type\Type;
use Closure;
use Webmozart\PathUtil\Path;

class TypeBuilder extends Builder
{
    public Type $type;

    public function type(
        ?string $typeName = null,
        ?Closure $fieldsCallback = null,
        ?Closure $updateFieldsCallback = null,
        ?Closure $createFieldsCallback = null
    ): TypeBuilder {
        // creating unique anonymous class is difficult
        // https://stackoverflow.com/questions/40833199/static-properties-in-php7-anonymous-classes
        // https://www.php.net/language.oop5.anonymous#121839
        $code = file_get_contents(Path::join(__DIR__, 'class-templates', 'type.php'));
        $code = preg_replace("/<\?php/", '', $code);

        if ($typeName) {
            $code = preg_replace('/Test.Type/', $typeName, $code);
        } else {
            // remove type information for no type given tests
            $code = preg_replace('/protected static string \$type.+/', '', $code);
        }

        /** @var TestType */
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
}

class TestType extends Type
{
    public static ?Closure $fieldsCallback;
    public static ?Closure $updateFieldsCallback;
    public static ?Closure $createFieldsCallback;

    protected function fields(FieldBag $fields): void
    {
        if (static::$fieldsCallback) {
            (static::$fieldsCallback)($fields);
        }
    }

    protected function updateFields(FieldBag $fields): void
    {
        if (static::$updateFieldsCallback) {
            (static::$updateFieldsCallback)($fields);
        }
    }

    protected function createFields(FieldBag $fields): void
    {
        if (static::$createFieldsCallback) {
            (static::$createFieldsCallback)($fields);
        }
    }
}
