<?php

namespace Afeefa\ApiResources\Tests\Api\Schema;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Api\ApiRequest;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\StringAttribute;
use Afeefa\ApiResources\Model\Model;
use Afeefa\ApiResources\Model\ModelInterface;
use Afeefa\ApiResources\Resolver\QueryActionResolver;
use Afeefa\ApiResources\Resolver\QueryAttributeResolver;
use Afeefa\ApiResources\Test\ApiResourcesTest;
use function Afeefa\ApiResources\Test\T;

use Closure;

class RequestAttributeTest extends ApiResourcesTest
{
    private TestWatcher $testWatcher;

    protected function setUp(): void
    {
        parent::setup();

        $this->testWatcher = new TestWatcher();
    }

    public function test_request_with_attribute()
    {
        $api = $this->apiBuilder()->api('API', function (Closure $addResource, Closure $addType) {
            $addType('TYPE', function (FieldBag $fields) {
                $fields
                    ->attribute('name', StringAttribute::class)

                    ->attribute('source', StringAttribute::class)

                    ->attribute('dependent', function (StringAttribute $attribute) {
                        $attribute->resolve(function (QueryAttributeResolver $r) {
                            $r->select('source');
                        });
                    })

                    ->attribute('resolved', function (StringAttribute $attribute) {
                        $attribute->resolve(function (QueryAttributeResolver $r) {
                            $this->testWatcher->attributeResolvers[] = $r;

                            $r->get(function (array $owners) {
                                /** @var ModelInterface[] $owners */
                                foreach ($owners as $owner) {
                                    $owner->apiResourcesSetAttribute('resolved', 'test_dependency');
                                }
                                return [];
                            });
                        });
                    });
            });

            $addResource('RES', function (Closure $addAction) {
                $addAction('ACT', T('TYPE'), function (Action $action) {
                    $action
                        ->resolve(function (QueryActionResolver $r) {
                            $this->testWatcher->actionResolvers[] = $r;

                            $r->get(function (ApiRequest $request, Closure $getSelectFields, Closure $getRequestedFields) {
                                $selectFields = $getSelectFields();

                                $attributes = [];
                                foreach ($getSelectFields() as $fieldName) {
                                    $attributes[$fieldName] = $fieldName;
                                }

                                $model = Model::fromSingle('TYPE', $attributes);

                                if (in_array('dependent', $getRequestedFields())) {
                                    $model->apiResourcesSetAttribute('dependent', 'source');
                                }

                                return $model;
                            });
                        });
                });
            });
        })->get();

        $result = $api->request(function (ApiRequest $request) {
            $request
                ->fromInput([
                    'resource' => 'RES',
                    'action' => 'ACT',
                    'fields' => [
                        'name' => true,
                        'dependent' => true,
                        'resolved' => true
                    ]
                ]);
        });

        $model = $result['data'];

        $this->assertEquals(Model::class, $model::class);

        $data = ($result['data'])->jsonSerialize();

        $expectedData = [
            'type' => 'TYPE',
            'id' => 'id',
            'name' => 'name',
            'dependent' => 'source',
            'resolved' => 'test_dependency'
        ];

        $this->assertEquals($expectedData, $data);

        $this->assertCount(1, $this->testWatcher->attributeResolvers);
        $this->assertCount(1, $this->testWatcher->actionResolvers);
    }
}

// attribute
// attribute not defined
// id type are always sent
// attribute with depedencies
// attribute with custom resolver
// attribute with default value

class TestWatcher
{
    public array $attributeResolvers = [];
    public array $actionResolvers = [];
}
