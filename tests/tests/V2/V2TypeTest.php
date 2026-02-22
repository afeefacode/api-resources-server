<?php

namespace Afeefa\ApiResources\TestsV2;

use Afeefa\ApiResources\Field\Attribute;
use Afeefa\ApiResources\Field\Fields\DateAttribute;
use Afeefa\ApiResources\Field\Fields\StringAttribute;
use Afeefa\ApiResources\Field\Relation as V1Relation;
use Afeefa\ApiResources\TestV2\V2TestCase;
use Afeefa\ApiResources\V2\FieldBag;
use Afeefa\ApiResources\Validator\Validators\LinkOneValidator;
use Afeefa\ApiResources\Validator\Validators\StringValidator;

use function Afeefa\ApiResources\Test\T;

use const Afeefa\ApiResources\V2\CREATE;
use const Afeefa\ApiResources\V2\READ;
use const Afeefa\ApiResources\V2\UPDATE;

class V2TypeTest extends V2TestCase
{
    public function test_v2_type_empty()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type')->get();

        $this->assertEquals('Test.V2Type', $type::type());
        $this->assertEquals(0, $type->getFields()->numEntries());
        $this->assertEquals(0, $type->getUpdateFields()->numEntries());
        $this->assertEquals(0, $type->getCreateFields()->numEntries());
    }

    public function test_v2_type_read_only_field()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('title')->on(READ);
        })->get();

        $this->assertEquals(1, $type->getFields()->numEntries());
        $this->assertEquals(0, $type->getUpdateFields()->numEntries());
        $this->assertEquals(0, $type->getCreateFields()->numEntries());

        $field = $type->getField('title');
        $this->assertInstanceOf(Attribute::class, $field);
        $this->assertEquals('title', $field->getName());
        $this->assertEquals($type, $field->getOwner());
    }

    public function test_v2_type_mutation_field()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('password')->on(UPDATE, CREATE);
        })->get();

        $this->assertEquals(0, $type->getFields()->numEntries());
        $this->assertEquals(1, $type->getUpdateFields()->numEntries());
        $this->assertEquals(1, $type->getCreateFields()->numEntries());

        $this->assertEquals('password', $type->getUpdateField('password')->getName());
        $this->assertEquals('password', $type->getCreateField('password')->getName());
    }

    public function test_v2_type_all_operations()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('first_name')->on(READ, UPDATE, CREATE);
        })->get();

        $this->assertEquals(1, $type->getFields()->numEntries());
        $this->assertEquals(1, $type->getUpdateFields()->numEntries());
        $this->assertEquals(1, $type->getCreateFields()->numEntries());

        $this->assertEquals('first_name', $type->getField('first_name')->getName());
        $this->assertEquals('first_name', $type->getUpdateField('first_name')->getName());
        $this->assertEquals('first_name', $type->getCreateField('first_name')->getName());
    }

    public function test_v2_type_multiple_fields()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('first_name')->on(READ, UPDATE, CREATE)
                ->string('last_name')->on(READ, UPDATE, CREATE)
                ->date('date_birth')->on(READ, UPDATE);
        })->get();

        $this->assertEquals(3, $type->getFields()->numEntries());
        $this->assertEquals(3, $type->getUpdateFields()->numEntries());
        $this->assertEquals(2, $type->getCreateFields()->numEntries());

        $this->assertInstanceOf(StringAttribute::class, $type->getField('first_name'));
        $this->assertInstanceOf(StringAttribute::class, $type->getField('last_name'));
        $this->assertInstanceOf(DateAttribute::class, $type->getField('date_birth'));
    }

    public function test_v2_type_per_operation_required()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('name')->on(READ, UPDATE, CREATE)
                    ->onCreate(required: true);
        })->get();

        $this->assertFalse($type->getField('name')->isRequired());
        $this->assertFalse($type->getUpdateField('name')->isRequired());
        $this->assertTrue($type->getCreateField('name')->isRequired());
    }

    public function test_v2_type_onMutation_shorthand()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('name')->on(READ, UPDATE, CREATE)
                    ->onMutation(required: true);
        })->get();

        $this->assertFalse($type->getField('name')->isRequired());
        $this->assertTrue($type->getUpdateField('name')->isRequired());
        $this->assertTrue($type->getCreateField('name')->isRequired());
    }

    public function test_v2_type_per_operation_validate()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('name')->on(READ, UPDATE, CREATE)
                    ->onUpdate(validate: function (StringValidator $v) {
                        $v->filled();
                    });
        })->get();

        $this->assertFalse($type->getField('name')->hasValidator());
        $this->assertTrue($type->getUpdateField('name')->hasValidator());
        $this->assertFalse($type->getCreateField('name')->hasValidator());
    }

    public function test_v2_type_relation_read()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->hasOne('author', T('Test.Author'))->on(READ);
        })->get();

        $this->assertEquals(1, $type->getFields()->numEntries());
        $this->assertEquals(0, $type->getUpdateFields()->numEntries());

        $relation = $type->getRelation('author');
        $this->assertInstanceOf(V1Relation::class, $relation);
        $this->assertFalse($relation->getRelatedType()->isLink());
        $this->assertFalse($relation->getRelatedType()->isList());
    }

    public function test_v2_type_relation_mode_link()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->hasOne('gender', T('Test.Category'))->on(READ, UPDATE, CREATE)
                    ->onMutation(mode: 'link');
        })->get();

        // READ: normal relation (not link)
        $readRelation = $type->getRelation('gender');
        $this->assertFalse($readRelation->getRelatedType()->isLink());

        // UPDATE: link relation
        $updateRelation = $type->getUpdateRelation('gender');
        $this->assertTrue($updateRelation->getRelatedType()->isLink());

        // CREATE: link relation
        $createRelation = $type->getCreateRelation('gender');
        $this->assertTrue($createRelation->getRelatedType()->isLink());
    }

    public function test_v2_type_relation_mode_link_with_validate_and_required()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->hasOne('cancelation_reason', T('Test.Category'))->on(READ, UPDATE, CREATE)
                    ->onMutation(mode: 'link', validate: fn(LinkOneValidator $v) => $v->filled(), required: true);
        })->get();

        // READ: normal relation, no link, no validator, not required
        $readRelation = $type->getRelation('cancelation_reason');
        $this->assertFalse($readRelation->getRelatedType()->isLink());
        $this->assertFalse($readRelation->hasValidator());
        $this->assertFalse($readRelation->isRequired());

        // UPDATE: link + validator + required
        $updateRelation = $type->getUpdateRelation('cancelation_reason');
        $this->assertTrue($updateRelation->getRelatedType()->isLink());
        $this->assertTrue($updateRelation->hasValidator());
        $this->assertTrue($updateRelation->isRequired());

        // CREATE: link + validator + required
        $createRelation = $type->getCreateRelation('cancelation_reason');
        $this->assertTrue($createRelation->getRelatedType()->isLink());
        $this->assertTrue($createRelation->hasValidator());
        $this->assertTrue($createRelation->isRequired());
    }

    public function test_v2_type_relation_mode_per_operation()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->hasOne('customer', T('Test.Customer'))->on(READ, UPDATE, CREATE)
                    ->onUpdate(mode: 'link')
                    ->onCreate(mode: 'link_or_save');
        })->get();

        // READ: normal
        $this->assertFalse($type->getRelation('customer')->getRelatedType()->isLink());

        // UPDATE: link
        $this->assertTrue($type->getUpdateRelation('customer')->getRelatedType()->isLink());

        // CREATE: link_or_save → isLink=true (for v1 compatibility)
        $this->assertTrue($type->getCreateRelation('customer')->getRelatedType()->isLink());
    }

    public function test_v2_type_has_many()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->hasMany('tags', T('Test.Tag'))->on(READ);
        })->get();

        $relation = $type->getRelation('tags');
        $this->assertTrue($relation->getRelatedType()->isList());
        $this->assertFalse($relation->getRelatedType()->isLink());
    }

    public function test_v2_type_attribute_options_request()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('status')->on(READ, UPDATE, CREATE)
                    ->optionsRequest(function () {
                        return ['active', 'inactive'];
                    });
        })->get();

        $this->assertTrue($type->getField('status')->hasOptionsRequest());
        $this->assertTrue($type->getUpdateField('status')->hasOptionsRequest());
        $this->assertTrue($type->getCreateField('status')->hasOptionsRequest());
    }

    public function test_v2_type_relation_options_request()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->hasOne('category', T('Test.Category'))->on(READ)
                    ->optionsRequest(function () {
                        return [];
                    });
        })->get();

        $this->assertTrue($type->getRelation('category')->hasOptionsRequest());
    }

    public function test_v2_type_relation_link_with_options_request()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->hasOne('country', T('Test.Country'))->on(READ, UPDATE, CREATE)
                    ->onMutation(mode: 'link')
                    ->optionsRequest(function () {
                        return [];
                    });
        })->get();

        // READ: not a link, but has optionsRequest
        $readRelation = $type->getRelation('country');
        $this->assertFalse($readRelation->getRelatedType()->isLink());
        $this->assertTrue($readRelation->hasOptionsRequest());

        // UPDATE: link + optionsRequest
        $updateRelation = $type->getUpdateRelation('country');
        $this->assertTrue($updateRelation->getRelatedType()->isLink());
        $this->assertTrue($updateRelation->hasOptionsRequest());

        // CREATE: link + optionsRequest
        $createRelation = $type->getCreateRelation('country');
        $this->assertTrue($createRelation->getRelatedType()->isLink());
        $this->assertTrue($createRelation->hasOptionsRequest());
    }

    public function test_v2_type_resolver_preserved()
    {
        $resolveCallback = function () {
            return 'resolved';
        };

        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) use ($resolveCallback) {
            $fields
                ->string('computed')->on(READ)
                    ->resolve($resolveCallback);
        })->get();

        $field = $type->getField('computed');
        $this->assertTrue($field->hasResolver());
    }

    public function test_v2_type_default_value()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('status')->on(READ, UPDATE, CREATE)
                    ->default('active');
        })->get();

        $this->assertEquals('active', $type->getField('status')->getDefaultValue());
        $this->assertEquals('active', $type->getUpdateField('status')->getDefaultValue());
        $this->assertEquals('active', $type->getCreateField('status')->getDefaultValue());
    }

    public function test_v2_type_field_owner()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('name')->on(READ, UPDATE);
        })->get();

        $this->assertEquals($type, $type->getField('name')->getOwner());
        $this->assertEquals($type, $type->getUpdateField('name')->getOwner());
    }
}
