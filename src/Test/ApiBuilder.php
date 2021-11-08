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
    private $useTestContainer = false;

    public function api(
        ?string $type = null,
        ?Closure $resourcesCallback = null
    ): ApiBuilder {
        // creating unique anonymous class is difficult
        // https://stackoverflow.com/questions/40833199/static-properties-in-php7-anonymous-classes
        // https://www.php.net/language.oop5.anonymous#121839
        $code = file_get_contents(Path::join(__DIR__, 'class-templates', 'api.php'));
        $code = preg_replace("/<\?php/", '', $code);

        if ($type) {
            $code = preg_replace('/Test.Api/', $type, $code);
        } else {
            // remove type information for no type given tests
            $code = preg_replace('/protected static string \$type.+/', '', $code);
        }

        /** @var TestApi */
        $api = eval($code); // eval is not always evil

        $api::$resourcesCallback = $resourcesCallback;

        $this->api = $api;

        return $this;
    }

    /**
     * Enables ActionBag to create actions with pre-configured response and resolver.
     */
    public function useTestContainer(): ApiBuilder
    {
        $this->useTestContainer = true;
        return $this;
    }

    public function get(): Api
    {
        if ($this->useTestContainer) {
            $api = (new TestContainer())->create($this->api::class);
        } else {
            $api = (new Container())->create($this->api::class);
        }

        return $api;
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
