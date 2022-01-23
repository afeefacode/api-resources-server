<?php

namespace Afeefa\ApiResources\Tests\Resolver;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Api\ApiRequest;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Exception\Exceptions\MissingCallbackException;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\VarcharAttribute;
use Afeefa\ApiResources\Model\Model;
use Afeefa\ApiResources\Resolver\QueryActionResolver;
use Afeefa\ApiResources\Test\QueryTest;

use function Afeefa\ApiResources\Test\T;

use Afeefa\ApiResources\Type\Type;

use stdClass;

class QueryActionResolverTest extends QueryTest
{
    public function test_calls()
    {
        $api = $this->createApiWithAction(function (Action $action) {
            $action
                ->response(T('TYPE'))
                ->resolve(function (QueryActionResolver $r) {
                    $this->testWatcher->called();

                    $r->load(function () {
                        $this->testWatcher->called();
                        return Model::fromSingle('TYPE', []);
                    });
                });
        });

        $this->assertEquals(0, $this->testWatcher->countCalls);

        $this->request($api);

        $this->assertEquals(2, $this->testWatcher->countCalls);
    }

    public function test_calls_requested_fields()
    {
        $api = $this->createApiWithAction(function (Action $action) {
            $action
                ->response(T('TYPE'))
                ->resolve(function (TestActionResolver $r) {
                    $r->load(function () use ($r) {
                        $this->testWatcher->info($r->countCalculateCalls);

                        $r->getRequestedFields();
                        $r->fieldIsRequested('test');
                        $r->fieldIsRequested('test2');
                        $r->fieldIsRequested('test3');
                        $r->getRequestedFieldNames();

                        $this->testWatcher->info($r->countCalculateCalls);
                    });
                });
        });

        $this->request($api);

        $this->assertEquals([0, 1], $this->testWatcher->info);
    }

    public function test_missing_action_resolver()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Resolve callback for action ACT on resource RES must receive an ActionResolver as argument.');

        $api = $this->createApiWithAction(function (Action $action) {
            $action
                ->response(T('TYPE'))
                ->resolve(function () {
                });
        });

        $this->request($api);
    }

    public function test_missing_load_callback()
    {
        $this->expectException(MissingCallbackException::class);
        $this->expectExceptionMessage('Action resolver for action ACT on resource RES must provide a load callback.');

        $api = $this->createApiWithAction(function (Action $action) {
            $action
                ->response(T('TYPE'))
                ->resolve(function (QueryActionResolver $r) {
                });
        });

        $this->request($api);
    }

    public function test_returns_single()
    {
        $api = $this->createApiWithAction(function (Action $action) {
            $action
                ->response(T('TYPE'))
                ->resolve(function (QueryActionResolver $r) {
                    $r->load(function () {
                        return TestModel::fromSingle('TYPE', []);
                    });
                });
        });

        $result = $this->request($api);

        /** @var TestModel */
        $model = $result['data'];

        $this->assertInstanceOf(TestModel::class, $model);
        $this->assertEquals('TYPE', $model->type);
        $this->assertFalse(isset($model->id));
        $this->assertEquals(['id', 'type'], $model->getVisibleFields());
        $this->assertEquals([
            'type' => 'TYPE'
        ], $model->jsonSerialize());
    }

    /**
     * @dataProvider nullDataProvider
     */
    public function test_returns_single_null($null)
    {
        $api = $this->createApiWithAction(function (Action $action) use ($null) {
            $action
                ->response(T('TYPE'))
                ->resolve(function (QueryActionResolver $r) use ($null) {
                    $r->load(function () use ($null) {
                        if ($null !== 'NOTHING') {
                            return null;
                        }
                    });
                });
        });

        $result = $this->request($api);

        $this->assertNull($result['data']);
    }

    public function nullDataProvider()
    {
        return [
            'return null' => [true],
            'return nothing' => ['NOTHING']
        ];
    }

    public function test_returns_single_wrong_type()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Load callback of action resolver for action ACT on resource RES must return a ModelInterface object of type [TYPE].');

        $api = $this->createApiWithAction(function (Action $action) {
            $action
                ->response(T('TYPE'))
                ->resolve(function (QueryActionResolver $r) {
                    $r->load(function () {
                        return TestModel::fromSingle('TYPE_WRONG', []);
                    });
                });
        });

        $this->request($api);
    }

    public function test_returns_single_with_union()
    {
        $api = $this->createApiWithAction(function (Action $action) {
            $action
                ->response([T('TYPE'), T('TYPE2')])
                ->resolve(function (QueryActionResolver $r) {
                    $r->load(function () {
                        return TestModel::fromSingle('TYPE2', []);
                    });
                });
        });

        $result = $this->request($api);

        /** @var TestModel */
        $model = $result['data'];

        $this->assertInstanceOf(TestModel::class, $model);
        $this->assertEquals('TYPE2', $model->type);
        $this->assertFalse(isset($model->id));
        $this->assertEquals(['id', 'type'], $model->getVisibleFields());
        $this->assertEquals([
            'type' => 'TYPE2'
        ], $model->jsonSerialize());
    }

    public function test_returns_single_wrong_type_with_union()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Load callback of action resolver for action ACT on resource RES must return a ModelInterface object of type [TYPE,TYPE2].');

        $api = $this->createApiWithAction(function (Action $action) {
            $action
                ->response([T('TYPE'), T('TYPE2')])
                ->resolve(function (QueryActionResolver $r) {
                    $r->load(function () {
                        return TestModel::fromSingle('TYPE_WRONG', []);
                    });
                });
        });

        $this->request($api);
    }

    /**
     * @dataProvider wrongModelDataProvider
     */
    public function test_returns_single_wrong_model($wrongModel)
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Load callback of action resolver for action ACT on resource RES must return a ModelInterface object.');

        $api = $this->createApiWithAction(function (Action $action) use ($wrongModel) {
            $action
                ->response(T('TYPE'))
                ->resolve(function (QueryActionResolver $r) use ($wrongModel) {
                    $r->load(function () use ($wrongModel) {
                        return $wrongModel;
                    });
                });
        });

        $this->request($api);
    }

    public function wrongModelDataProvider()
    {
        return [
            'array' => [[]],
            'string' => ['string'],
            'object' => [new stdClass()]
        ];
    }

    public function test_returns_list()
    {
        $api = $this->createApiWithAction(function (Action $action) {
            $action
                ->response(Type::list(T('TYPE')))
                ->resolve(function (QueryActionResolver $r) {
                    $r->load(function () {
                        return TestModel::fromList('TYPE', [[], []]);
                    });
                });
        });

        $result = $this->request($api);

        /** @var TestModel[] */
        $models = $result['data'];

        $this->assertIsArray($models);
        $this->assertCount(2, $models);

        foreach ($models as $model) {
            $this->assertInstanceOf(TestModel::class, $model);
            $this->assertEquals('TYPE', $model->type);
            $this->assertFalse(isset($model->id));
            $this->assertEquals(['id', 'type'], $model->getVisibleFields());
            $this->assertEquals([
                'type' => 'TYPE'
            ], $model->jsonSerialize());
        }
    }

    public function test_returns_list_with_union()
    {
        $api = $this->createApiWithAction(function (Action $action) {
            $action
                ->response(Type::list([T('TYPE'), T('TYPE2')]))
                ->resolve(function (QueryActionResolver $r) {
                    $r->load(function () {
                        return TestModel::fromList('TYPE2', [[], []]);
                    });
                });
        });

        $result = $this->request($api);

        /** @var TestModel[] */
        $models = $result['data'];

        $this->assertIsArray($models);
        $this->assertCount(2, $models);

        foreach ($models as $model) {
            $this->assertInstanceOf(TestModel::class, $model);
            $this->assertEquals('TYPE2', $model->type);
            $this->assertFalse(isset($model->id));
            $this->assertEquals(['id', 'type'], $model->getVisibleFields());
            $this->assertEquals([
                'type' => 'TYPE2'
            ], $model->jsonSerialize());
        }
    }

    public function test_returns_list_empty()
    {
        $api = $this->createApiWithAction(function (Action $action) {
            $action
                ->response(Type::list(T('TYPE')))
                ->resolve(function (QueryActionResolver $r) {
                    $r->load(function () {
                        return Model::fromList('TYPE', []);
                    });
                });
        });

        $result = $this->request($api);

        $models = $result['data'];
        $this->assertIsArray($models);
        $this->assertCount(0, $models);
    }

    /**
     * @dataProvider wrongListReturnDataProvider
     */
    public function test_returns_list_no_list($wrongList)
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Load callback of action resolver for action ACT on resource RES must return an array of ModelInterface objects.');

        $api = $this->createApiWithAction(function (Action $action) use ($wrongList) {
            $action
                ->response(Type::list(T('TYPE')))
                ->resolve(function (QueryActionResolver $r) use ($wrongList) {
                    $r->load(function () use ($wrongList) {
                        return $wrongList;
                    });
                });
        });

        $this->request($api);
    }

    public function wrongListReturnDataProvider()
    {
        return [
            'null' => [null],
            'string' => ['string'],
            'array' => [['a']],
            'object' => [new stdClass()]
        ];
    }

    /**
     * @dataProvider wrongListItemReturnDataProvider
     */
    public function test_returns_list_with_wrong_model($wrongModel)
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Load callback of action resolver for action ACT on resource RES must return an array of ModelInterface objects.');

        $api = $this->createApiWithAction(function (Action $action) use ($wrongModel) {
            $action
                ->response(Type::list(T('TYPE')))
                ->resolve(function (QueryActionResolver $r) use ($wrongModel) {
                    $r->load(function () use ($wrongModel) {
                        return [
                            Model::fromSingle('TYPE', []),
                            $wrongModel
                        ];
                    });
                });
        });

        $this->request($api);
    }

    public function wrongListItemReturnDataProvider()
    {
        return [
            'null' => [null],
            'array' => [[]],
            'string' => ['string'],
            'object' => [new stdClass()]
        ];
    }

    /**
     * @dataProvider requestFieldsDataProvider
     */
    public function test_requested_fields($fields, $expectedFields)
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('name', VarcharAttribute::class)
                    ->attribute('title', VarcharAttribute::class);
            },
            function (Action $action) {
                $action
                    ->response(T('TYPE'))
                    ->resolve(function (QueryActionResolver $r) {
                        $r->load(function () use ($r) {
                            $this->testWatcher->requestedFields($r->getRequestedFieldNames());
                        });
                    });
            }
        );

        $this->request($api, $fields);

        $this->assertEquals([$expectedFields], $this->testWatcher->requestedFields);
    }

    /**
     * @dataProvider requestFieldsDataProvider
     */
    public function test_select_fields($fields, $expectedFields)
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('name', VarcharAttribute::class)
                    ->attribute('title', VarcharAttribute::class);
            },
            function (Action $action) {
                $action
                    ->response(T('TYPE'))
                    ->resolve(function (QueryActionResolver $r) {
                        $r->load(function () use ($r) {
                            $this->testWatcher->selectFields($r->getSelectFields());
                        });
                    });
            }
        );

        $this->request($api, $fields);

        $expectedFieldsWithId = ['id', ...$expectedFields];

        $this->assertEquals([$expectedFieldsWithId], $this->testWatcher->selectFields);
    }

    public function test_requested_fields_union_missing_type()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('You need to pass a type name to getRequestedFields() in the resolver of action ACT on resource RES since the action returns an union type.');

        $api = $this->createApiWithAction(
            function (Action $action) {
                $action
                    ->response([T('TYPE'), T('TYPE2')])
                    ->resolve(function (QueryActionResolver $r) {
                        $r->load(function () use ($r) {
                            $this->testWatcher->requestedFields($r->getRequestedFields());
                        });
                    });
            }
        );

        $this->request($api);
    }

    /**
     * @dataProvider wrongTypeNameToSelectFieldsDataProvider
     */
    public function test_requested_fields_wrong_type($single)
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The type name passed to getRequestedFields() in the resolver of action ACT on resource RES is not supported by the action.');

        $api = $this->createApiWithAction(
            function (Action $action) use ($single) {
                $action
                    ->response($single ? T('TYPE') : [T('TYPE'), T('TYPE2')])
                    ->resolve(function (QueryActionResolver $r) {
                        $r->load(function () use ($r) {
                            $this->testWatcher->requestedFields($r->getRequestedFields('TYPE3'));
                        });
                    });
            }
        );

        $this->request($api);
    }

    public function test_select_fields_union_missing_type()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('You need to pass a type name to getSelectFields() in the resolver of action ACT on resource RES since the action returns an union type.');

        $api = $this->createApiWithAction(
            function (Action $action) {
                $action
                    ->response([T('TYPE'), T('TYPE2')])
                    ->resolve(function (QueryActionResolver $r) {
                        $r->load(function () use ($r) {
                            $this->testWatcher->selectFields($r->getSelectFields());
                        });
                    });
            }
        );

        $this->request($api);
    }

    /**
     * @dataProvider wrongTypeNameToSelectFieldsDataProvider
     */
    public function test_select_fields_wrong_type($single)
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The type name passed to getSelectFields() in the resolver of action ACT on resource RES is not supported by the action.');

        $api = $this->createApiWithAction(
            function (Action $action) use ($single) {
                $action
                    ->response($single ? T('TYPE') : [T('TYPE'), T('TYPE2')])
                    ->resolve(function (QueryActionResolver $r) {
                        $r->load(function () use ($r) {
                            $this->testWatcher->selectFields($r->getSelectFields('TYPE3'));
                        });
                    });
            }
        );

        $this->request($api);
    }

    public function wrongTypeNameToSelectFieldsDataProvider()
    {
        return [
            'single' => [true],
            'list' => [false]
        ];
    }

    /**
     * @dataProvider requestFieldsDataProvider
     */
    public function test_visible_fields($fields, $expectedFields)
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('name', VarcharAttribute::class)
                    ->attribute('title', VarcharAttribute::class);
            },
            function (Action $action) {
                $action
                    ->response(T('TYPE'))
                    ->resolve(function (QueryActionResolver $r) {
                        $r->load(function () {
                            return TestModel::fromSingle('TYPE', []);
                        });
                    });
            }
        );

        $result = $this->request($api, $fields);

        /** @var TestModel */
        $model = $result['data'];

        $expectedVisibleFields = ['id', 'type', ...$expectedFields];

        $this->assertEquals($expectedVisibleFields, $model->getVisibleFields());
    }

    public function requestFieldsDataProvider()
    {
        // [request fields, calculated fields]
        return [
            'name' => [['name' => true], ['name']],

            'title' => [['title' => true], ['title']],

            'name+title' => [[
                'name' => true,
                'title' => true
            ], ['name', 'title']],

            'name+title+unknown' => [[
                'name' => true,
                'title' => true,
                'unknown' => true
            ], ['name', 'title']],

            'nothing' => [null, []],

            'empty' => [[], []],

            'unknown_relation' => [[
                'relation' => [
                    'field' => true
                ]
            ], []]
        ];
    }

    public function test_request()
    {
        $api = $this->createApiWithAction(function (Action $action) {
            $action
                ->response(T('TYPE'))
                ->resolve(function (QueryActionResolver $r) {
                    $r->load(function () use ($r) {
                        $this->testWatcher->request($r->getRequest());
                    });
                });
        });

        $this->request(
            $api,
            params: [
                'a' => 1,
                'b' => true,
                'c' => 'value'
            ],
            filters: [
                'a' => 1,
                'b' => true,
                'c' => 'value'
            ]
        );

        $request = $this->testWatcher->request;

        $expectedParams = [
            'a' => 1,
            'b' => true,
            'c' => 'value'
        ];

        $this->assertEquals($expectedParams, $request->getParams());

        $this->assertTrue($request->hasParam('a'));
        $this->assertTrue($request->hasParam('b'));
        $this->assertTrue($request->hasParam('c'));
        $this->assertFalse($request->hasParam('d'));

        $expectedFilters = [
            'a' => 1,
            'b' => true,
            'c' => 'value'
        ];

        $this->assertEquals($expectedFilters, $request->getFilters());
    }
}

/**
 * @method static TestModel fromSingle
 */
class TestModel extends Model
{
    public array $selectFields = [];

    public function selectFields(array $selectFields): TestModel
    {
        $this->selectFields = $selectFields;
        return $this;
    }

    public function getVisibleFields(): array
    {
        return $this->visibleFields;
    }
}

class TestWatcher
{
    public int $countCalls = 0;
    public array $selectFields = [];
    public array $saveFields = [];
    public array $requestedFields = [];
    public array $info2 = [];
    public array $info = [];
    public ApiRequest $request;

    public function called()
    {
        $this->countCalls++;
    }

    public function info($info)
    {
        $this->info[] = $info;
    }

    public function info2($info)
    {
        $this->info2[] = $info;
    }

    public function selectFields(array $selectFields)
    {
        $this->selectFields[] = $selectFields;
    }

    public function saveFields(array $saveFields)
    {
        $this->saveFields[] = $saveFields;
    }

    public function request(ApiRequest $request)
    {
        $this->request = $request;
    }

    public function requestedFields(array $requestedFields)
    {
        $this->requestedFields[] = $requestedFields;
    }
}

class TestActionResolver extends QueryActionResolver
{
    public int $countCalculateCalls = 0;

    protected function calculateRequestedFields(?string $typeName = null): array
    {
        $this->countCalculateCalls++;
        return parent::calculateRequestedFields($typeName);
    }
}
