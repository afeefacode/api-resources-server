<?php

namespace Afeefa\ApiResources\Test;

use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Type\Type;
use Closure;
use Webmozart\PathUtil\Path;

class TypeBuilder
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

        if ($typeName) { // do not register in missing type test
            TypeRegistry::register($type);
        }

        $this->type = $type;

        return $this;
    }

    public function get(): Type
    {
        return $this->type;
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
            (static::$fieldsCallback)->call($this, $fields);
        }
    }

    protected function updateFields(FieldBag $fields): void
    {
        if (static::$updateFieldsCallback) {
            (static::$updateFieldsCallback)->call($this, $fields);
        }
    }

    protected function createFields(FieldBag $fields): void
    {
        if (static::$createFieldsCallback) {
            (static::$createFieldsCallback)->call($this, $fields);
        }
    }
}
