<?php

namespace Afeefa\ApiResources\Test;

use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Resource\ResourceBag;
use Afeefa\ApiResources\Type\Type;
use Closure;

use Webmozart\PathUtil\Path;

class ApiBuilder extends Builder
{
    public Api $api;

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

    public function get(): Api
    {
        return $this->container->get($this->api::class); // create and register single instance
    }
}

class TestApi extends Api
{
    public static ?Closure $resourcesCallback;

    protected function resources(ResourceBag $resources): void
    {
        if (static::$resourcesCallback) {
            $addResource = function (string $type = null, ?Closure $actionsCallback = null) use ($resources): void {
                $resource = (new ResourceBuilder($this->container))
                    ->resource($type, $actionsCallback)
                    ->get()::class;
                $resources->add($resource);
            };

            $addType = function (
                string $typeName = null,
                ?Closure $fieldsCallback = null,
                ?Closure $updateFieldsCallback = null,
                ?Closure $createFieldsCallback = null
            ): Type {
                return (new TypeBuilder($this->container))
                    ->type($typeName, $fieldsCallback, $updateFieldsCallback, $createFieldsCallback)
                    ->get();
            };
            (static::$resourcesCallback)($addResource, $addType);
        }
    }
}
