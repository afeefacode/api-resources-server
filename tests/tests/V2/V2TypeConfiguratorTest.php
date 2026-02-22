<?php

namespace Afeefa\ApiResources\TestsV2;

use Afeefa\ApiResources\TestV2\V2TestCase;
use Afeefa\ApiResources\V2\FieldBag;
use Afeefa\ApiResources\V2\TypeConfigurator;
use Afeefa\ApiResources\Validator\Validators\StringValidator;

use const Afeefa\ApiResources\V2\CREATE;
use const Afeefa\ApiResources\V2\READ;
use const Afeefa\ApiResources\V2\UPDATE;

class V2TypeConfiguratorTest extends V2TestCase
{
    // ===== only() =====

    public function test_only_filters_read_fields()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('title')->on(READ)
                ->string('name')->on(READ)
                ->string('note')->on(READ);
        })->get();

        $this->assertEquals(3, $type->getFields()->numEntries());

        (new TypeConfigurator())->only(['title', 'name'])->apply($type);

        $this->assertEquals(2, $type->getFields()->numEntries());
        $this->assertTrue($type->hasField('title'));
        $this->assertTrue($type->hasField('name'));
        $this->assertFalse($type->hasField('note'));
    }

    public function test_only_filters_mutation_fields()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('title')->on(READ, UPDATE, CREATE)
                ->string('name')->on(READ, UPDATE, CREATE)
                ->string('note')->on(READ, UPDATE, CREATE);
        })->get();

        $this->assertEquals(3, $type->getUpdateFields()->numEntries());
        $this->assertEquals(3, $type->getCreateFields()->numEntries());

        (new TypeConfigurator())->only(['title'])->apply($type);

        $this->assertEquals(1, $type->getFields()->numEntries());
        $this->assertEquals(1, $type->getUpdateFields()->numEntries());
        $this->assertEquals(1, $type->getCreateFields()->numEntries());
        $this->assertTrue($type->hasField('title'));
        $this->assertFalse($type->hasField('name'));
        $this->assertFalse($type->hasField('note'));
    }

    // ===== readOnly() =====

    public function test_readonly_clears_mutations()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('title')->on(READ, UPDATE, CREATE)
                ->string('name')->on(READ, UPDATE, CREATE);
        })->get();

        $this->assertEquals(2, $type->getFields()->numEntries());
        $this->assertEquals(2, $type->getUpdateFields()->numEntries());
        $this->assertEquals(2, $type->getCreateFields()->numEntries());

        (new TypeConfigurator())->readOnly()->apply($type);

        $this->assertEquals(2, $type->getFields()->numEntries());
        $this->assertEquals(0, $type->getUpdateFields()->numEntries());
        $this->assertEquals(0, $type->getCreateFields()->numEntries());
    }

    // ===== field()->onMutation(required: true) =====

    public function test_field_required_on_mutation()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields->string('name')->on(READ, UPDATE, CREATE);
        })->get();

        $this->assertFalse($type->getUpdateField('name')->isRequired());
        $this->assertFalse($type->getCreateField('name')->isRequired());

        (new TypeConfigurator())->field('name')->onMutation(required: true);

        // TypeConfigurator muss separat angewendet werden
        $configurator = new TypeConfigurator();
        $configurator->field('name')->onMutation(required: true);
        $configurator->apply($type);

        $this->assertTrue($type->getUpdateField('name')->isRequired());
        $this->assertTrue($type->getCreateField('name')->isRequired());
        $this->assertFalse($type->getField('name')->isRequired()); // READ unverändert
    }

    public function test_field_required_only_on_create()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields->string('name')->on(UPDATE, CREATE);
        })->get();

        $configurator = new TypeConfigurator();
        $configurator->field('name')->onCreate(required: true);
        $configurator->apply($type);

        $this->assertFalse($type->getUpdateField('name')->isRequired());
        $this->assertTrue($type->getCreateField('name')->isRequired());
    }

    public function test_field_required_only_on_update()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields->string('name')->on(UPDATE, CREATE);
        })->get();

        $configurator = new TypeConfigurator();
        $configurator->field('name')->onUpdate(required: true);
        $configurator->apply($type);

        $this->assertTrue($type->getUpdateField('name')->isRequired());
        $this->assertFalse($type->getCreateField('name')->isRequired());
    }

    // ===== field()->onMutation(validate: ...) =====

    public function test_field_validate_on_mutation()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields->string('name')->on(UPDATE, CREATE);
        })->get();

        $this->assertFalse($type->getUpdateField('name')->hasValidator());
        $this->assertFalse($type->getCreateField('name')->hasValidator());

        $configurator = new TypeConfigurator();
        $configurator->field('name')->onMutation(validate: fn(StringValidator $v) => $v->max(100));
        $configurator->apply($type);

        $this->assertTrue($type->getUpdateField('name')->hasValidator());
        $this->assertTrue($type->getCreateField('name')->hasValidator());
    }

    // ===== Kombination =====

    public function test_only_and_readonly_combined()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('title')->on(READ, UPDATE, CREATE)
                ->string('name')->on(READ, UPDATE, CREATE)
                ->string('note')->on(READ, UPDATE, CREATE);
        })->get();

        $configurator = new TypeConfigurator();
        $configurator->only(['title', 'name'])->readOnly();
        $configurator->apply($type);

        $this->assertEquals(2, $type->getFields()->numEntries());
        $this->assertEquals(0, $type->getUpdateFields()->numEntries());
        $this->assertEquals(0, $type->getCreateFields()->numEntries());
    }

    // ===== Idempotenz: mehrfaches Anwenden ändert nichts =====

    public function test_apply_is_idempotent()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('title')->on(READ, UPDATE, CREATE)
                ->string('name')->on(READ, UPDATE, CREATE);
        })->get();

        $configurator = new TypeConfigurator();
        $configurator->only(['title'])->apply($type);
        $configurator->only(['title'])->apply($type); // zweites Mal: kein Fehler

        $this->assertEquals(1, $type->getFields()->numEntries());
    }
}
