<?php

namespace Afeefa\ApiResources\Test;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Api\ApiRequest;
use Afeefa\ApiResources\Model\Model;
use Afeefa\ApiResources\Model\ModelInterface;
use Afeefa\ApiResources\Resolver\MutationActionModelResolver;
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

    protected function createApiWithUpdateType(Closure $fieldsCallback): Api
    {
        return $this->createApiWithType(null, $fieldsCallback, $fieldsCallback);
    }

    protected function createApiWithType(?Closure $fieldsCallback, ?Closure $updateFieldsCallback, ?Closure $createFieldsCallback): Api
    {
        return $this->apiBuilder()->api('API', function (Closure $addResource, Closure $addType) use ($fieldsCallback, $updateFieldsCallback, $createFieldsCallback) {
            $addType('TYPE', $fieldsCallback, $updateFieldsCallback, $createFieldsCallback);
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
                                    return Model::fromSingle('TYPE', ['id' => '111333']);
                                })
                                ->delete(fn () => null);
                        });
                });
            });
        })->get();
    }

    protected function createApiWithUpdateTypeAndAction(Closure $updateFieldsCallback, Closure $actionCallback): Api
    {
        return $this->createApiWithTypeAndAction(null, $updateFieldsCallback, $updateFieldsCallback, $actionCallback);
    }

    protected function createApiWithTypeAndAction(?Closure $fieldsCallback, ?Closure $updateFieldsCallback, ?Closure $createFieldsCallback, Closure $actionCallback): Api
    {
        return $this->apiBuilder()->api('API', function (Closure $addResource, Closure $addType) use ($fieldsCallback, $updateFieldsCallback, $createFieldsCallback, $actionCallback) {
            $addType('TYPE', $fieldsCallback, $updateFieldsCallback, $createFieldsCallback);
            $addResource('RES', function (Closure $addAction) use ($actionCallback) {
                $addAction('ACT', $actionCallback);
            });
        })->get();
    }

    protected function createApiWithAction(Closure $actionCallback): Api
    {
        return $this->apiBuilder()->api('API', function (Closure $addResource) use ($actionCallback) {
            $addResource('RES', function (Closure $addAction) use ($actionCallback) {
                $addAction('ACT', $actionCallback);
            });
        })->get();
    }

    protected function request(Api $api, $data = 'unset', $params = []): array
    {
        return $api->request(function (ApiRequest $request) use ($data, $params) {
            $request
                ->resourceType('RES')
                ->actionName('ACT')
                ->params($params);

            if ($data !== 'unset') {
                $request->fieldsToSave($data);
            }
        });
    }
}
