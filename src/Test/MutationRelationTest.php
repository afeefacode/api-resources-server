<?php

namespace Afeefa\ApiResources\Test;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Api\ApiRequest;
use Afeefa\ApiResources\Model\Model;
use Afeefa\ApiResources\Model\ModelInterface;
use Afeefa\ApiResources\Resolver\MutationActionModelResolver;
use Afeefa\ApiResources\Tests\Resolver\TestModel;
use Afeefa\ApiResources\Tests\Resolver\TestWatcher;
use Closure;

class MutationRelationTest extends ApiResourcesTest
{
    protected TestWatcher $testWatcher;

    protected function setUp(): void
    {
        parent::setup();

        $this->testWatcher = new TestWatcher();
    }

    protected function createApiWithType(Closure $fieldsCallback): Api
    {
        return $this->apiBuilder()->api('API', function (Closure $addResource, Closure $addType) use ($fieldsCallback) {
            $addType('TYPE', $fieldsCallback);
            $addResource('RES', function (Closure $addAction) {
                $addAction('ACT', function (Action $action) {
                    $action
                        ->input(T('TYPE'))
                        ->response(T('TYPE'))
                        ->resolve(function (MutationActionModelResolver $r) {
                            $r
                                ->get(function (string $id, string $typeName) {
                                    return Model::fromSingle($typeName, ['id' => $id]);
                                })
                                ->update(function (ModelInterface $model) {
                                    return $model;
                                })
                                ->add(function () {
                                    return TestModel::fromSingle('TYPE', ['id' => '111333']);
                                })
                                ->delete(fn () => null);
                        });
                });
            });
        })->get();
    }

    protected function request(Api $api, ?array $data = null, $params = []): array
    {
        return $api->request(function (ApiRequest $request) use ($data, $params) {
            $request
                ->resourceType('RES')
                ->actionName('ACT')
                ->params($params);

            if ($data !== null) {
                $request->fieldsToSave($data);
            }
        });
    }
}
