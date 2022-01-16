<?php

namespace Afeefa\ApiResources\Tests\Resolver\Mutation;

use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\HasOneRelation;
use Afeefa\ApiResources\Field\Fields\VarcharAttribute;
use Afeefa\ApiResources\Resolver\Mutation\MutationRelationResolver;
use Afeefa\ApiResources\Resolver\Mutation\MutationResolveContext;
use Afeefa\ApiResources\Test\ApiResourcesTest;
use function Afeefa\ApiResources\Test\T;

use Afeefa\ApiResources\Tests\Resolver\TestWatcher;
use Afeefa\ApiResources\Type\Type;
use Afeefa\ApiResources\Validator\ValidationFailedException;
use Closure;

class MutationResolveContextTest extends ApiResourcesTest
{
    private TestWatcher $testWatcher;

    protected function setUp(): void
    {
        parent::setup();

        $this->testWatcher = new TestWatcher();
    }

    public function test_simple()
    {
        $type = $this->createType(function (FieldBag $fields) {
            $fields->attribute('name', VarcharAttribute::class);
            $fields->relation('some_relation', T('TEST'), $this->createRelationResolver());
        });

        $resolveContext = $this->createResolveContext($type, [
            'name' => 'name1', // attribute exists
            'title' => 'title1', // attribute not exists
            'some_relation' => [ // relation exists, but no owner fields to be saved
                'name' => 'name2'
            ],
            'other_relation' => [ // relation not exists
                'name' => 'name3'
            ]
        ]);

        $expectedFields = [
            'name' => 'name1'
        ];

        $this->assertEquals($expectedFields, $resolveContext->getSaveFields());
    }

    public function test_create_relation_resolvers()
    {
        $type = $this->createType(function (FieldBag $fields) {
            $fields->relation('some_relation', T('TEST'), $this->createRelationResolver(function (MutationRelationResolver $r) {
                $this->testWatcher->called();
            }));
            $fields->relation('other_relation', T('TEST'), $this->createRelationResolver(function (MutationRelationResolver $r) {
                $this->testWatcher->called();
            }));
        });

        $resolveContext = $this->createResolveContext($type, [
            'some_relation' => [
                'name' => 'name2'
            ]
        ]);

        $this->assertEquals(0, $this->testWatcher->countCalls);

        $resolveContext->getRelationResolvers();
        $resolveContext->getRelationResolvers();
        $relationResolvers = $resolveContext->getRelationResolvers();

        $this->assertEquals(1, $this->testWatcher->countCalls);

        $this->assertCount(1, $relationResolvers);

        $this->assertEquals(['some_relation'], array_keys($relationResolvers));
    }

    public function test_create_relation_resolvers_two_relations()
    {
        $type = $this->createType(function (FieldBag $fields) {
            $fields->relation('some_relation', T('TEST'), $this->createRelationResolver(function (MutationRelationResolver $r) {
                $this->testWatcher->called();
            }));
            $fields->relation('other_relation', T('TEST'), $this->createRelationResolver(function (MutationRelationResolver $r) {
                $this->testWatcher->called();
            }));
        });

        $resolveContext = $this->createResolveContext($type, [
            'some_relation' => [],
            'other_relation' => [],
        ]);

        $this->assertEquals(0, $this->testWatcher->countCalls);

        $resolveContext->getRelationResolvers();
        $resolveContext->getRelationResolvers();
        $relationResolvers = $resolveContext->getRelationResolvers();

        $this->assertEquals(2, $this->testWatcher->countCalls);

        $this->assertCount(2, $relationResolvers);
        $this->assertEquals(['some_relation', 'other_relation'], array_keys($relationResolvers));
    }

    public function test_create_only_one_relation_resolver_for_each_relation()
    {
        $type = $this->createType(function (FieldBag $fields) {
            $fields->attribute('name', VarcharAttribute::class);
            $fields->relation('some_relation', Type::list(T('TEST')), $this->createRelationResolver(function (MutationRelationResolver $r) {
                $this->testWatcher->called();
            }));
        });

        $resolveContext = $this->createResolveContext($type, [
            'some_relation' => [
                ['name' => 'related1'],
                ['name' => 'related2']
            ]
        ]);

        $this->assertEquals(0, $this->testWatcher->countCalls);

        $relationResolvers = $resolveContext->getRelationResolvers();

        $this->assertEquals(1, $this->testWatcher->countCalls);
        $this->assertCount(1, $relationResolvers);
    }

    public function test_save_fields()
    {
        $type = $this->createType(function (FieldBag $fields) {
            $fields->attribute('name', VarcharAttribute::class);
        });

        $resolveContext = $this->createResolveContext($type, [
            'name' => 'name1'
        ]);

        $this->assertEquals([
            'name' => 'name1'
        ], $resolveContext->getSaveFields());

        $this->assertEquals([
            'name' => 'name1',
            'a' => 'b'
        ], $resolveContext->getSaveFields([
            'a' => 'b'
        ]));

        $this->assertEquals([
            'name' => 'name1'
        ], $resolveContext->getSaveFields());

        $this->assertEquals([
            'name' => 'name1',
            'a2' => 'b2'
        ], $resolveContext->getSaveFields([
            'a2' => 'b2'
        ]));
    }

    /**
     * @dataProvider wrongValueSingleDataProvider
     */
    public function test_wrong_value_single($value)
    {
        $this->expectException(ValidationFailedException::class);
        $this->expectExceptionMessage('Value passed to the singular relation some_relation must be null or an array.');

        $type = $this->createType(function (FieldBag $fields) {
            $fields->attribute('name', VarcharAttribute::class);
            $fields->relation('some_relation', T('TEST'), $this->createRelationResolver(function (MutationRelationResolver $r) {
            }));
        });

        $resolveContext = $this->createResolveContext($type, [
            'some_relation' => $value
        ]);

        $resolveContext->getRelationResolvers();
    }

    public function wrongValueSingleDataProvider()
    {
        return [
            'string' => ['wrong'],
            'number' => [1]
        ];
    }

    /**
     * @dataProvider wrongValueManyDataProvider
     */
    public function test_wrong_value_many($value)
    {
        $this->expectException(ValidationFailedException::class);
        $this->expectExceptionMessage('Value passed to the many relation some_relation must be an array.');

        $type = $this->createType(function (FieldBag $fields) {
            $fields->attribute('name', VarcharAttribute::class);
            $fields->relation('some_relation', Type::list(T('TEST')), $this->createRelationResolver(function (MutationRelationResolver $r) {
            }));
        });

        $resolveContext = $this->createResolveContext($type, [
            'some_relation' => $value
        ]);

        $resolveContext->getRelationResolvers();
    }

    public function wrongValueManyDataProvider()
    {
        return [
            'string' => ['wrong'],
            'number' => [1],
            'null' => [null]
        ];
    }

    private function createType(?Closure $fieldsCallback = null, ?string $typeName = null): Type
    {
        $typeName ??= 'TEST';
        return $this->typeBuilder()->type($typeName, function (FieldBag $fields) use ($fieldsCallback) {
            if ($fieldsCallback) {
                $fieldsCallback($fields);
            }
        })->get(true);
    }

    private function createRelationResolver(?Closure $callback = null): Closure
    {
        return function (HasOneRelation $relation) use ($callback) {
            $relation->resolveSave(function (MutationRelationResolver $r) use ($callback) {
                if ($callback) {
                    $callback($r);
                }
            });
        };
    }

    private function createResolveContext(Type $type, array $fields): MutationResolveContext
    {
        return $this->container->create(MutationResolveContext::class)
            ->type($type)
            ->fieldsToSave($fields);
    }
}
