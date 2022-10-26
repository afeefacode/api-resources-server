<?php

namespace Afeefa\ApiResources\Tests\Eloquent;

use Afeefa\ApiResources\Eloquent\ModelRelationResolver;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Relation;
use Afeefa\ApiResources\Test\Eloquent\ApiResourcesEloquentTest;
use function Afeefa\ApiResources\Test\T;

use Afeefa\ApiResources\Type\Type;

use ReflectionFunction;

class ModelTypeTest extends ApiResourcesEloquentTest
{
    public function test_model_type()
    {
        $type = $this->modelTypeBuilder()->modelType(
            'Test.Type',
            Model::class
        )->get();

        $this->assertEquals(Model::class, $type::$ModelClass);
        $this->assertEquals('Test.Type', $type::type());
    }

    public function test_model_type_creates_resolvers_automatically()
    {
        $type = $this->modelTypeBuilder()->modelType(
            'Test.Type',
            Model::class,
            function (FieldBag $fields) {
                $fields->relation('type', T('OtherType'));
                $fields->relation('types', Type::list(T('OtherType')));
            },
            function (FieldBag $fields) {
                $fields->relation('type2', T('OtherType'));
                $fields->relation('types2', Type::list(T('OtherType')));
                $fields->relation('linked_type2', Type::link(T('OtherType')));
                $fields->relation('linked_types2', Type::list(Type::link(T('OtherType'))));
            },
            function (FieldBag $fields) {
                $fields->relation('type3', T('OtherType'));
                $fields->relation('types3', Type::list(T('OtherType')));
                $fields->relation('linked_type3', Type::link(T('OtherType')));
                $fields->relation('linked_types3', Type::list(Type::link(T('OtherType'))));
            }
        )->get();

        $this->assertEquals(Model::class, $type::$ModelClass);
        $this->assertEquals('Test.Type', $type::type());

        $assertResolver = function (Relation $relation, string $action) {
            $this->assertTrue($relation->hasResolver());
            $resolve = $relation->getResolve();
            $info = new ReflectionFunction($resolve);
            $this->assertEquals(ModelRelationResolver::class, $info->getClosureThis()::class);
            $this->assertEquals($action, $info->getName());
        };

        // get

        $assertResolver($type->getRelation('type'), 'get_relation');
        $assertResolver($type->getRelation('types'), 'get_relation');

        // update

        $assertResolver($type->getUpdateRelation('type2'), 'save_has_one_relation');
        $assertResolver($type->getUpdateRelation('types2'), 'save_has_many_relation');
        $assertResolver($type->getUpdateRelation('linked_type2'), 'save_link_one_relation');
        $assertResolver($type->getUpdateRelation('linked_types2'), 'save_link_many_relation');

        // create

        $assertResolver($type->getCreateRelation('type3'), 'save_has_one_relation');
        $assertResolver($type->getCreateRelation('types3'), 'save_has_many_relation');
        $assertResolver($type->getCreateRelation('linked_type3'), 'save_link_one_relation');
        $assertResolver($type->getCreateRelation('linked_types3'), 'save_link_many_relation');
    }

    public function test_model_type_missing_model_class()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/^Missing Eloquent model in class Afeefa\\\ApiResources\\\Test\\\Eloquent\\\TestModelType@anonymous/');

        $this->modelTypeBuilder()->type(
            'Test.Type'
        )->get();
    }
}
