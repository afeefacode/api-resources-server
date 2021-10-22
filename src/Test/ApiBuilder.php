<?php

namespace Afeefa\ApiResources\Test;

use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\DI\Container;
use Afeefa\ApiResources\Resource\ResourceBag;
use Closure;
use Webmozart\PathUtil\Path;

class ApiBuilder
{
    public Container $container;
    public Api $api;

    public function api(string $type, ?Closure $resourcesCallback = null): ApiBuilder
    {
        // creating unique anonymous class is difficult
        // https://stackoverflow.com/questions/40833199/static-properties-in-php7-anonymous-classes
        // https://www.php.net/language.oop5.anonymous#121839
        $code = file_get_contents(Path::join(__DIR__, 'uniqueapiclass.php'));
        $code = preg_replace("/<\?php/", '', $code);

        /** @var TestApi */
        $api = eval($code); // eval is not always evil

        $api::$type = $type;
        $api::$resourcesCallback = $resourcesCallback;

        $this->api = (new Container())->create($api::class);

        return $this;
    }

    public function get(): Api
    {
        return $this->api;
    }
}

class TestApi extends Api
{
    public static ?Closure $resourcesCallback;

    protected function resources(ResourceBag $resources): void
    {
        if (static::$resourcesCallback) {
            (static::$resourcesCallback)->call($this, $resources);
        }
    }
}
