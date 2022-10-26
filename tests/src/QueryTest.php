<?php

namespace Afeefa\ApiResources\Test;

use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Api\ApiRequest;
use Afeefa\ApiResources\Tests\Resolver\TestWatcher;
use Closure;

class QueryTest extends ApiResourcesTest
{
    protected TestWatcher $testWatcher;

    protected function setUp(): void
    {
        parent::setup();

        $this->testWatcher = new TestWatcher();
    }

    protected function createApiWithTypeAndAction(Closure $fieldsCallback, $TypeClassOrClassesOrMeta, Closure $actionCallback): Api
    {
        return $this->apiBuilder()->api('API', function (Closure $addResource, Closure $addType) use ($fieldsCallback, $TypeClassOrClassesOrMeta, $actionCallback) {
            $addType('TYPE', $fieldsCallback);
            $addResource('RES', function (Closure $addAction) use ($TypeClassOrClassesOrMeta, $actionCallback) {
                $addAction('ACT', $TypeClassOrClassesOrMeta, $actionCallback);
            });
        })->get();
    }

    protected function createApiWithAction($TypeClassOrClassesOrMeta, Closure $actionCallback): Api
    {
        return $this->apiBuilder()->api('API', function (Closure $addResource) use ($TypeClassOrClassesOrMeta, $actionCallback) {
            $addResource('RES', function (Closure $addAction) use ($TypeClassOrClassesOrMeta, $actionCallback) {
                $addAction('ACT', $TypeClassOrClassesOrMeta, $actionCallback);
            });
        })->get();
    }

    protected function request(Api $api, ?array $fields = null, ?array $params = null, ?array $filters = null)
    {
        return $api->request(function (ApiRequest $request) use ($fields, $params, $filters) {
            $request
                ->resourceType('RES')
                ->actionName('ACT');

            if ($fields) {
                $request->fields($fields);
            }

            if ($params) {
                $request->params($params);
            }

            if ($filters) {
                $request->filters($filters);
            }
        });
    }
}
