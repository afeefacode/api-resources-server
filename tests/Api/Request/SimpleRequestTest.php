<?php

namespace Afeefa\ApiResources\Tests\Api\Schema;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Api\ApiRequest;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\StringAttribute;
use Afeefa\ApiResources\Model\Model;
use Afeefa\ApiResources\Resolver\QueryActionResolver;
use Afeefa\ApiResources\Test\ApiResourcesTest;
use function Afeefa\ApiResources\Test\T;

use Closure;

class SimpleRequestTest extends ApiResourcesTest
{
    public function test_request()
    {
        $api = $this->apiBuilder()->api('API', function (Closure $addResource) {
            $addResource('RES', function (Closure $addAction) {
                $addAction('ACT', function (Action $action) {
                    $action
                        ->response(T('TYPE'))
                        ->resolve(function (QueryActionResolver $resolver) {
                            $resolver->load(function () {
                                return Model::fromSingle('TYPE', [
                                    'id' => '123',
                                    'name' => 'test'
                                ]);
                            });
                        });
                });
            });
        })->get();

        // request via php interface

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

        // request via input

        $result = $api->request(function (ApiRequest $request) {
            $request
                ->fromInput([
                    'resource' => 'RES',
                    'action' => 'ACT',
                    'fields' => [
                        'name' => true
                    ]
                ]);
        });

        $data = ($result['data'])->jsonSerialize();

        $expectedData = [
            'type' => 'TYPE',
            'id' => '123'
        ];

        $this->assertEquals($expectedData, $data);
    }

    public function test_request_with_attribute()
    {
        $api = $this->apiBuilder()->api('API', function (Closure $addResource, Closure $addType) {
            $addType('TYPE', function (FieldBag $fields) {
                $fields->attribute('name', StringAttribute::class);
            });

            $addResource('RES', function (Closure $addAction) {
                $addAction('ACT', function (Action $action) {
                    $action
                        ->response(T('TYPE'))
                        ->resolve(function (QueryActionResolver $resolver) {
                            $resolver->load(function () {
                                return Model::fromSingle('TYPE', [
                                    'id' => '123',
                                    'name' => 'test'
                                ]);
                            });
                        });
                });
            });
        })->get();

        // request via php interface

        $result = $api->request(function (ApiRequest $request) {
            $request
                ->resourceType('RES')
                ->actionName('ACT')
                ->fields(['name' => true]);
        });

        $data = ($result['data'])->jsonSerialize();

        $expectedData = [
            'type' => 'TYPE',
            'id' => '123',
            'name' => 'test'
        ];

        $this->assertEquals($expectedData, $data);

        // request via input

        $result = $api->request(function (ApiRequest $request) {
            $request
                ->fromInput([
                    'resource' => 'RES',
                    'action' => 'ACT',
                    'fields' => [
                        'name' => true
                    ]
                ]);
        });

        $data = ($result['data'])->jsonSerialize();

        $expectedData = [
            'type' => 'TYPE',
            'id' => '123',
            'name' => 'test'
        ];

        $this->assertEquals($expectedData, $data);
    }
}
