<?php

namespace Afeefa\ApiResources\Test;

use Afeefa\ApiResources\Field\Field;
use Closure;
use Webmozart\PathUtil\Path;

class FieldBuilder extends Builder
{
    public Field $field;

    public function field(
        ?string $type = null,
        ?Closure $setupCallback = null
    ): FieldBuilder {
        // creating unique anonymous class is difficult
        // https://stackoverflow.com/questions/40833199/static-properties-in-php7-anonymous-classes
        // https://www.php.net/language.oop5.anonymous#121839
        $code = file_get_contents(Path::join(__DIR__, 'class-templates', 'field.php'));
        $code = preg_replace("/<\?php/", '', $code);

        if ($type) {
            $code = preg_replace('/Test.Field/', $type, $code);
        } else {
            // remove type information for no type given tests
            $code = preg_replace('/protected static string \$type.+/', '', $code);
        }

        /** @var TestField */
        $field = eval($code); // eval is not always evil

        $field::$setupCallback = $setupCallback;

        $this->field = $field;

        return $this;
    }

    public function get(): Field
    {
        return $this->container->create($this->field::class);
    }
}

class TestField extends Field
{
    public static ?Closure $setupCallback;

    protected function setup(): void
    {
        if (static::$setupCallback) {
            (static::$setupCallback)();
        }
    }
}
