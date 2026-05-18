<?php

namespace Afeefa\ApiResources\TestsV2;

use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\TestV2\V2TestCase;
use Afeefa\ApiResources\V2\FieldBag;
use Afeefa\ApiResources\V2\TypeConfigurator;
use Afeefa\ApiResources\Validator\Validators\StringValidator;

class V2TypeConfiguratorTest extends V2TestCase
{
    // ===== only() =====

    public function test_only_filters_read_fields()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('title')->write(false)
                ->string('name')->write(false)
                ->string('note')->write(false);
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
                ->string('title')
                ->string('name')
                ->string('note');
        })->get();

        (new TypeConfigurator())->only(['title'])->apply($type);

        $this->assertEquals(1, $type->getFields()->numEntries());
        $this->assertEquals(1, $type->getUpdateFields()->numEntries());
        $this->assertEquals(1, $type->getCreateFields()->numEntries());
        $this->assertTrue($type->hasField('title'));
        $this->assertFalse($type->hasField('name'));
        $this->assertFalse($type->hasField('note'));
    }

    // ===== readOnly() =====

    public function test_readOnly_without_args_makes_all_fields_read_only()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('title')
                ->string('name')
                ->string('note');
        })->get();

        (new TypeConfigurator())->readOnly()->apply($type);

        $this->assertEquals(3, $type->getFields()->numEntries());
        $this->assertEquals(0, $type->getUpdateFields()->numEntries());
        $this->assertEquals(0, $type->getCreateFields()->numEntries());
    }

    public function test_readOnly_with_subset_only_affects_listed_fields()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('title')
                ->string('name');
        })->get();

        (new TypeConfigurator())->readOnly(['title'])->apply($type);

        // title raus aus UPDATE+CREATE, name unangetastet
        $this->assertFalse($type->getUpdateFields()->has('title'));
        $this->assertFalse($type->getCreateFields()->has('title'));
        $this->assertTrue($type->getUpdateFields()->has('name'));
        $this->assertTrue($type->getCreateFields()->has('name'));
    }

    public function test_readOnly_throws_on_unknown_field()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields->string('title');
        })->get();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("TypeConfigurator readOnly(): unknown field(s) [headlne]");

        (new TypeConfigurator())->readOnly(['headlne'])->apply($type);
    }

    public function test_readOnly_combined_with_only_drops_to_read_only_subset()
    {
        // Portal-Use-Case: only() schraenkt auf Subset ein, readOnly() macht den Subset read-only.
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('title')
                ->string('name')
                ->string('note');
        })->get();

        (new TypeConfigurator())->only(['title', 'name'])->readOnly()->apply($type);

        $this->assertEquals(2, $type->getFields()->numEntries());
        $this->assertEquals(0, $type->getUpdateFields()->numEntries());
        $this->assertEquals(0, $type->getCreateFields()->numEntries());
    }

    public function test_readOnly_after_only_removed_field_throws()
    {
        // only() entfernt 'note' aus allen Bags. readOnly(['note']) referenziert dann ein Phantom.
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('title')
                ->string('note');
        })->get();

        $configurator = new TypeConfigurator();
        $configurator->only(['title'])->readOnly(['note']);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("readOnly(): unknown field(s) [note]");

        $configurator->apply($type);
    }

    public function test_only_throws_on_empty_array()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('TypeConfigurator only(): requires at least one field name.');

        (new TypeConfigurator())->only([]);
    }

    public function test_readOnly_throws_on_empty_array()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('TypeConfigurator readOnly(): requires at least one field name');

        (new TypeConfigurator())->readOnly([]);
    }

    public function test_only_throws_on_unknown_field()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields->string('title');
        })->get();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("TypeConfigurator only(): unknown field(s) [headlne]");

        (new TypeConfigurator())->only(['title', 'headlne'])->apply($type);
    }

    // ===== field()->write/update/create =====

    public function test_field_required_on_write()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields->string('name');
        })->get();

        $configurator = new TypeConfigurator();
        $configurator->field('name')->write(required: true);
        $configurator->apply($type);

        $this->assertTrue($type->getUpdateField('name')->isRequired());
        $this->assertTrue($type->getCreateField('name')->isRequired());
        $this->assertFalse($type->getField('name')->isRequired()); // READ unveraendert
    }

    public function test_field_required_only_on_create()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields->string('name')->read(false);
        })->get();

        $configurator = new TypeConfigurator();
        $configurator->field('name')->create(required: true);
        $configurator->apply($type);

        $this->assertFalse($type->getUpdateField('name')->isRequired());
        $this->assertTrue($type->getCreateField('name')->isRequired());
    }

    public function test_field_required_only_on_update()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields->string('name')->read(false);
        })->get();

        $configurator = new TypeConfigurator();
        $configurator->field('name')->update(required: true);
        $configurator->apply($type);

        $this->assertTrue($type->getUpdateField('name')->isRequired());
        $this->assertFalse($type->getCreateField('name')->isRequired());
    }

    public function test_field_validate_on_write()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields->string('name')->read(false);
        })->get();

        $configurator = new TypeConfigurator();
        $configurator->field('name')->write(validate: fn (StringValidator $v) => $v->max(100));
        $configurator->apply($type);

        $this->assertTrue($type->getUpdateField('name')->hasValidator());
        $this->assertTrue($type->getCreateField('name')->hasValidator());
    }

    public function test_field_default_null_overrides_type_default()
    {
        // Use-Case: Type setzt default 'hallo', Configurator setzt default: null zurueck.
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields->string('language')->write(default: 'de');
        })->get();

        $this->assertEquals('de', $type->getUpdateField('language')->getDefaultValue());

        $configurator = new TypeConfigurator();
        $configurator->field('language')->write(default: null);
        $configurator->apply($type);

        $this->assertNull($type->getUpdateField('language')->getDefaultValue());
        $this->assertFalse($type->getUpdateField('language')->hasDefaultValue());
    }

    public function test_field_default_on_write()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields->string('language')->read(false);
        })->get();

        $configurator = new TypeConfigurator();
        $configurator->field('language')->write(default: 'de');
        $configurator->apply($type);

        $this->assertEquals('de', $type->getUpdateField('language')->getDefaultValue());
        $this->assertEquals('de', $type->getCreateField('language')->getDefaultValue());
    }

    public function test_field_write_closure_with_nested_op_overrides()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields->string('name')->read(false);
        })->get();

        $configurator = new TypeConfigurator();
        $configurator->field('name')->write(function ($w) {
            $w->update(required: false);
            $w->create(required: true);
        });
        $configurator->apply($type);

        $this->assertFalse($type->getUpdateField('name')->isRequired());
        $this->assertTrue($type->getCreateField('name')->isRequired());
    }

    public function test_field_read_false_removes_from_read_bag()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('title')
                ->string('secret');
        })->get();

        $configurator = new TypeConfigurator();
        $configurator->field('secret')->read(false);
        $configurator->apply($type);

        $this->assertTrue($type->getFields()->has('title'));
        $this->assertFalse($type->getFields()->has('secret'));
        $this->assertTrue($type->getUpdateFields()->has('secret'));
        $this->assertTrue($type->getCreateFields()->has('secret'));
    }

    public function test_field_write_false_makes_read_only()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields->string('title');
        })->get();

        $configurator = new TypeConfigurator();
        $configurator->field('title')->write(false);
        $configurator->apply($type);

        $this->assertTrue($type->getFields()->has('title'));
        $this->assertFalse($type->getUpdateFields()->has('title'));
        $this->assertFalse($type->getCreateFields()->has('title'));
    }

    // ===== Validierung beim apply() — Punkt 3 =====

    public function test_field_throws_on_unknown_name()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields->string('title');
        })->get();

        $configurator = new TypeConfigurator();
        $configurator->field('headlne')->write(required: true);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("unknown field(s) [headlne]");

        $configurator->apply($type);
    }

    public function test_field_throws_when_referenced_after_only_removed_it()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('title')
                ->string('note');
        })->get();

        $configurator = new TypeConfigurator();
        $configurator
            ->only(['title'])
            ->field('note')->write(required: true);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("unknown field(s) [note]");

        $configurator->apply($type);
    }

    public function test_field_configure_in_bag_throws_when_field_not_in_target_bag()
    {
        // 'avatar' ist Type-seitig per write(false) aus UPDATE/CREATE raus.
        // FieldConfigurator versucht required:true auf UPDATE/CREATE zu setzen → wirft.
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields->string('avatar')->write(false);
        })->get();

        $configurator = new TypeConfigurator();
        $configurator->field('avatar')->write(required: true);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("cannot configure 'avatar' for update");

        $configurator->apply($type);
    }

    // ===== Reihenfolge-Pin (Punkt 8d) =====

    public function test_apply_executes_operations_in_chain_order()
    {
        // only(['title']) zuerst → entfernt 'note' aus allen Bags.
        // Danach koennen wir nur noch field('title') referenzieren.
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('title')
                ->string('note');
        })->get();

        $configurator = new TypeConfigurator();
        $configurator
            ->only(['title'])
            ->field('title')->write(required: true);
        $configurator->apply($type);

        $this->assertEquals(1, $type->getFields()->numEntries());
        $this->assertTrue($type->getUpdateField('title')->isRequired());
    }

    public function test_second_field_call_after_only_removed_it_throws()
    {
        // Punkt 3 (strikt): JEDER field()-Aufruf wird zur Apply-Zeit validiert.
        // Ein zweiter field('note') nach only(['title']) findet 'note' nicht mehr → wirft.
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('title')
                ->string('note');
        })->get();

        $configurator = new TypeConfigurator();
        $configurator->field('note')->write(required: true);   // ok (note existiert noch)
        $configurator->only(['title']);                         // entfernt note
        $configurator->field('note')->update(validate: fn (StringValidator $v) => $v->filled());

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("unknown field(s) [note]");

        $configurator->apply($type);
    }

    public function test_apply_executes_field_before_only_when_called_so()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('title')
                ->string('note');
        })->get();

        // Reihenfolge der Statements bestimmt apply-Order:
        // field('note') wird zuerst registriert (note existiert noch), dann only(['title']) entfernt note.
        $configurator = new TypeConfigurator();
        $configurator->field('note')->write(required: true);
        $configurator->only(['title']);
        $configurator->apply($type);

        $this->assertEquals(1, $type->getFields()->numEntries());
        $this->assertFalse($type->hasField('note'));
    }

    // ===== Kombination =====

    public function test_read_only_via_field_write_false_chain()
    {
        // Ersatz fuer den alten readOnly(): jedes Feld einzeln per field(name)->write(false).
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('title')
                ->string('name')
                ->string('note');
        })->get();

        $configurator = new TypeConfigurator();
        $configurator
            ->only(['title', 'name'])
            ->field('title')->write(false);
        $configurator->field('name')->write(false);
        $configurator->apply($type);

        $this->assertEquals(2, $type->getFields()->numEntries());
        $this->assertEquals(0, $type->getUpdateFields()->numEntries());
        $this->assertEquals(0, $type->getCreateFields()->numEntries());
    }

    // ===== Re-Aktivierung am FieldConfigurator wirft beim Build (analog Field) =====

    public function test_field_write_false_then_update_kwarg_throws_at_build()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('FieldConfigurator: cannot call update() after update was explicitly excluded via (false).');

        $c = new TypeConfigurator();
        $c->field('avatar')->write(false)->update(required: true);
    }

    public function test_field_update_false_then_update_no_args_throws_at_build()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('FieldConfigurator: cannot call update() after update was explicitly excluded via (false).');

        $c = new TypeConfigurator();
        $c->field('foo')->update(false)->update();
    }

    public function test_field_double_write_false_is_ok()
    {
        $c = new TypeConfigurator();
        $c->field('foo')->write(false)->write(false);   // kein Throw
        $this->assertTrue(true);
    }

    public function test_field_update_false_does_not_block_create_kwarg()
    {
        // analog Field: update(false) excluded NUR UPDATE; create(required:true) ist davon unberuehrt.
        $c = new TypeConfigurator();
        $c->field('foo')->update(false)->create(required: true);   // kein Throw
        $this->assertTrue(true);
    }

    // ===== Idempotenz =====

    public function test_apply_is_idempotent()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('title')
                ->string('name');
        })->get();

        $configurator = new TypeConfigurator();
        $configurator->only(['title'])->apply($type);
        $configurator->only(['title'])->apply($type); // zweites Mal: kein Fehler

        $this->assertEquals(1, $type->getFields()->numEntries());
    }
}
