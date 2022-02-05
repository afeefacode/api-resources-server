<?php

namespace Afeefa\ApiResources\Test;

use Afeefa\ApiResources\Action\ActionBag;
use Afeefa\ApiResources\Resource\Resource;
use Closure;
use Webmozart\PathUtil\Path;

class ResourceBuilder extends Builder
{
    public Resource $resource;

    public function resource(
        ?string $type = null,
        ?Closure $addActionCallback = null
    ): ResourceBuilder {
        // creating unique anonymous class is difficult
        // https://stackoverflow.com/questions/40833199/static-properties-in-php7-anonymous-classes
        // https://www.php.net/language.oop5.anonymous#121839
        $code = file_get_contents(Path::join(__DIR__, 'class-templates', 'resource.php'));
        $code = preg_replace("/<\?php/", '', $code);

        if ($type) {
            $code = preg_replace('/Test.Resource/', $type, $code);
        } else {
            // remove type information for no type given tests
            $code = preg_replace('/protected static string \$type.+/', '', $code);
        }

        /** @var TestResource */
        $resource = eval($code); // eval is not always evil

        $resource::$addActionCallback = $addActionCallback;

        $this->resource = $resource;

        return $this;
    }

    public function get(): Resource
    {
        return $this->container->create($this->resource::class);
    }
}

class TestResource extends Resource
{
    public static ?Closure $addActionCallback;

    protected function actions(ActionBag $actions): void
    {
        if (static::$addActionCallback) {
            $addAction = function (string $name, $TypeClassOrClassesOrMeta, Closure $actionCallback) use ($actions): void {
                if ($TypeClassOrClassesOrMeta instanceof Closure) {
                    $TypeClassOrClassesOrMeta = $TypeClassOrClassesOrMeta();
                }
                $actions->query($name, $TypeClassOrClassesOrMeta, $actionCallback);
            };
            $addMutation = function (string $name, $TypeClassOrClassesOrMeta, Closure $actionCallback) use ($actions): void {
                if ($TypeClassOrClassesOrMeta instanceof Closure) {
                    $TypeClassOrClassesOrMeta = $TypeClassOrClassesOrMeta();
                }
                $actions->mutation($name, $TypeClassOrClassesOrMeta, $actionCallback);
            };
            (static::$addActionCallback)($addAction, $addMutation);
        }
    }
}
