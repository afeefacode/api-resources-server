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

class MutationTest extends ApiResourcesTest
{
    protected TestWatcher $testWatcher;

    protected function setUp(): void
    {
        parent::setup();

        $this->testWatcher = new TestWatcher();
    }

    protected function createApiWithUpdateType(Closure $fieldsCallback): Api
    {
        return $this->apiBuilder()->api('API', function (Closure $addResource, Closure $addType) use ($fieldsCallback) {
            $addType('TYPE', $fieldsCallback, $fieldsCallback, $fieldsCallback);
            $addResource('RES', function (Closure $addAction, Closure $addMutation) {
                $addMutation('ACT', T('TYPE'), function (Action $action) {
                    $action
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

    protected function createApiWithUpdateTypeAndMutation(Closure $updateFieldsCallback, $TypeClassOrClassesOrMeta, Closure $actionCallback): Api
    {
        return $this->apiBuilder()->api('API', function (Closure $addResource, Closure $addType) use ($updateFieldsCallback, $TypeClassOrClassesOrMeta, $actionCallback) {
            $addType('TYPE', null, $updateFieldsCallback, $updateFieldsCallback);
            $addResource('RES', function (Closure $addAction, Closure $addMutation) use ($TypeClassOrClassesOrMeta, $actionCallback) {
                $addMutation('ACT', $TypeClassOrClassesOrMeta, $actionCallback);
            });
        })->get();
    }

    protected function createApiWithMutation($TypeClassOrClassesOrMeta, Closure $actionCallback): Api
    {
        return $this->apiBuilder()->api('API', function (Closure $addResource) use ($TypeClassOrClassesOrMeta, $actionCallback) {
            $addResource('RES', function (Closure $addAction, Closure $addMutation) use ($TypeClassOrClassesOrMeta, $actionCallback) {
                $addMutation('ACT', $TypeClassOrClassesOrMeta, $actionCallback);
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
