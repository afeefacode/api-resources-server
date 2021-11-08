<?php

namespace Afeefa\ApiResources\Test;

use Afeefa\ApiResources\DI\Container;
use Afeefa\ApiResources\Filter\Filter;
use Closure;
use Webmozart\PathUtil\Path;

class FilterBuilder
{
    public Filter $filter;

    public function filter(
        ?string $type = null,
        ?Closure $setupCallback = null
    ): FilterBuilder {
        // creating unique anonymous class is difficult
        // https://stackoverflow.com/questions/40833199/static-properties-in-php7-anonymous-classes
        // https://www.php.net/language.oop5.anonymous#121839
        $code = file_get_contents(Path::join(__DIR__, 'class-templates', 'filter.php'));
        $code = preg_replace("/<\?php/", '', $code);

        if ($type) {
            $code = preg_replace('/Test.Filter/', $type, $code);
        } else {
            // remove type information for no type given tests
            $code = preg_replace('/protected static string \$type.+/', '', $code);
        }

        /** @var TestFilter */
        $filter = eval($code); // eval is not always evil

        $filter::$setupCallback = $setupCallback;

        $this->filter = $filter;

        return $this;
    }

    public function get(): Filter
    {
        return $this->filter;
    }

    public function createInContainer(): Filter
    {
        return (new Container())->create($this->filter::class);
    }
}

class TestFilter extends Filter
{
    public static ?Closure $setupCallback;

    protected function setup(): void
    {
        if (static::$setupCallback) {
            (static::$setupCallback)->call($this);
        }
    }
}
