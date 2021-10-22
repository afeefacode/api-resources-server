<?php

namespace Afeefa\ApiResources\Test;

use Afeefa\ApiResources\Action\ActionBag;
use Afeefa\ApiResources\Resource\Resource;
use Closure;
use Webmozart\PathUtil\Path;

class ResourceBuilder
{
    public Resource $resource;

    public function resource(
        string $type,
        ?Closure $actionsCallback = null
    ): ResourceBuilder {
        // creating unique anonymous class is difficult
        // https://stackoverflow.com/questions/40833199/static-properties-in-php7-anonymous-classes
        // https://www.php.net/language.oop5.anonymous#121839
        $code = file_get_contents(Path::join(__DIR__, 'uniqueresourceclass.php'));
        $code = preg_replace("/<\?php/", '', $code);

        /** @var TestResource */
        $resource = eval($code); // eval is not always evil

        $resource::$type = $type;
        $resource::$actionsCallback = $actionsCallback;

        $this->resource = $resource;

        return $this;
    }

    public function get(): Resource
    {
        return $this->resource;
    }
}

class TestResource extends Resource
{
    public static ?Closure $actionsCallback;

    protected function actions(ActionBag $actions): void
    {
        if (static::$actionsCallback) {
            (static::$actionsCallback)->call($this, $actions);
        }
    }
}
