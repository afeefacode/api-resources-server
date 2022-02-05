<?php

namespace Afeefa\ApiResources\Tests\Api\Schema;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Api\ApiRequest;
use Afeefa\ApiResources\Model\Model;
use Afeefa\ApiResources\Resolver\MutationActionSimpleResolver;
use Afeefa\ApiResources\Resolver\QueryActionResolver;
use Afeefa\ApiResources\Test\ApiResourcesTest;
use function Afeefa\ApiResources\Test\T;

use Closure;

class RequestActionTest extends ApiResourcesTest
{
    public function test_query()
    {
        $api = $this->apiBuilder()->api('API', function (Closure $addResource) {
            $addResource('RES', function (Closure $addAction) {
                $addAction('ACT', T('TYPE'), function (Action $action) {
                    $action
                        ->resolve(function (QueryActionResolver $resolver) {
                            $resolver->get(function () {
                                return Model::fromSingle('TYPE', [
                                    'id' => '123',
                                    'name' => 'test'
                                ]);
                            });
                        });
                });
            });
        })->get();

        $result = $api->request(function (ApiRequest $request) {
            $request
                ->resourceType('RES')
                ->actionName('ACT')
                ->fields([
                    'name' => true
                ]);
        });

        $data = ($result['data'])->jsonSerialize();

        $expectedData = [
            'type' => 'TYPE',
            'id' => '123'
        ];

        $this->assertEquals($expectedData, $data);
    }

    public function test_mutation_returns_null()
    {
        $api = $this->apiBuilder()->api('API', function (Closure $addResource) {
            $addResource('RES', function (Closure $addAction, Closure $addMutation) {
                $addMutation('ACT', T('TYPE'), function (Action $action) {
                    $action
                        ->resolve(function (MutationActionSimpleResolver $r) {
                            $r->save(fn () => null);
                        });
                });
            });
        })->get();

        $result = $api->request(function (ApiRequest $request) {
            $request
                ->resourceType('RES')
                ->actionName('ACT')
                ->fields([
                    'name' => true
                ])
                ->fieldsToSave([
                    'name' => 'jens'
                ]);
        });

        $this->assertNull($result['data']);
    }
}
