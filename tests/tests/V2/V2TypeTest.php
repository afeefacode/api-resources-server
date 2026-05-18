<?php

namespace Afeefa\ApiResources\TestsV2;

use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Field\Attribute;
use Afeefa\ApiResources\Field\Fields\DateAttribute;
use Afeefa\ApiResources\Field\Fields\StringAttribute;
use Afeefa\ApiResources\Field\Relation as V1Relation;
use Afeefa\ApiResources\TestV2\V2TestCase;
use Afeefa\ApiResources\V2\FieldBag;
use Afeefa\ApiResources\Validator\Validators\LinkOneValidator;
use Afeefa\ApiResources\Validator\Validators\StringValidator;

use function Afeefa\ApiResources\Test\T;

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

    public function test_v2_type_default_membership_all_three()
    {
        // Ohne weitere Konfiguration ist ein Field in allen drei Bags.
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields->string('name');
        })->get();

        $this->assertEquals(1, $type->getFields()->numEntries());
        $this->assertEquals(1, $type->getUpdateFields()->numEntries());
        $this->assertEquals(1, $type->getCreateFields()->numEntries());

        $field = $type->getField('name');
        $this->assertInstanceOf(Attribute::class, $field);
        $this->assertEquals('name', $field->getName());
        $this->assertEquals($type, $field->getOwner());
    }

    public function test_v2_type_read_only_via_write_false()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('title')->write(false);
        })->get();

        $this->assertEquals(1, $type->getFields()->numEntries());
        $this->assertEquals(0, $type->getUpdateFields()->numEntries());
        $this->assertEquals(0, $type->getCreateFields()->numEntries());

        $field = $type->getField('title');
        $this->assertInstanceOf(Attribute::class, $field);
    }

    public function test_v2_type_write_only_via_read_false()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('password')->read(false);
        })->get();

        $this->assertEquals(0, $type->getFields()->numEntries());
        $this->assertEquals(1, $type->getUpdateFields()->numEntries());
        $this->assertEquals(1, $type->getCreateFields()->numEntries());
    }

    public function test_v2_type_update_only_via_create_false()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('internal_note')->read(false)->create(false);
        })->get();

        $this->assertEquals(0, $type->getFields()->numEntries());
        $this->assertEquals(1, $type->getUpdateFields()->numEntries());
        $this->assertEquals(0, $type->getCreateFields()->numEntries());
    }

    public function test_v2_type_create_only_via_kwargs_and_exclusions()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->date('date_start')->read(false)->update(false);
        })->get();

        $this->assertEquals(0, $type->getFields()->numEntries());
        $this->assertEquals(0, $type->getUpdateFields()->numEntries());
        $this->assertEquals(1, $type->getCreateFields()->numEntries());
    }

    public function test_v2_type_fully_hidden_field()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('removed')->read(false)->write(false);
        })->get();

        $this->assertEquals(0, $type->getFields()->numEntries());
        $this->assertEquals(0, $type->getUpdateFields()->numEntries());
        $this->assertEquals(0, $type->getCreateFields()->numEntries());
    }

    public function test_v2_type_multiple_fields()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('first_name')
                ->string('last_name')
                ->date('date_birth')->create(false);
        })->get();

        $this->assertEquals(3, $type->getFields()->numEntries());
        $this->assertEquals(3, $type->getUpdateFields()->numEntries());
        $this->assertEquals(2, $type->getCreateFields()->numEntries());

        $this->assertInstanceOf(StringAttribute::class, $type->getField('first_name'));
        $this->assertInstanceOf(StringAttribute::class, $type->getField('last_name'));
        $this->assertInstanceOf(DateAttribute::class, $type->getField('date_birth'));
    }

    public function test_v2_type_create_required_via_kwarg()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('name')->create(required: true);
        })->get();

        $this->assertFalse($type->getField('name')->isRequired());
        $this->assertFalse($type->getUpdateField('name')->isRequired());
        $this->assertTrue($type->getCreateField('name')->isRequired());
    }

    public function test_v2_type_write_required_kwarg_covers_update_and_create()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('name')->write(required: true);
        })->get();

        $this->assertFalse($type->getField('name')->isRequired());
        $this->assertTrue($type->getUpdateField('name')->isRequired());
        $this->assertTrue($type->getCreateField('name')->isRequired());
    }

    public function test_v2_type_update_validate_kwarg()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('name')->update(validate: fn (StringValidator $v) => $v->filled());
        })->get();

        $this->assertFalse($type->getField('name')->hasValidator());
        $this->assertTrue($type->getUpdateField('name')->hasValidator());
        $this->assertFalse($type->getCreateField('name')->hasValidator());
    }

    public function test_v2_type_write_closure_with_nested_update_and_create()
    {
        // write-Closure: gemeinsame Save-Config + Op-spezifische Overrides.
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('password')->read(false)
                    ->write(function ($w) {
                        $w->validate(fn (StringValidator $v) => $v->min(8));
                        $w->update(required: false);
                        $w->create(required: true);
                    });
        })->get();

        $this->assertFalse($type->getUpdateField('password')->isRequired());
        $this->assertTrue($type->getCreateField('password')->isRequired());
        $this->assertTrue($type->getUpdateField('password')->hasValidator());
        $this->assertTrue($type->getCreateField('password')->hasValidator());
    }

    public function test_v2_type_last_write_wins_kwarg_after_write_block()
    {
        // Last-write-wins: write() setzt required:true global, update() ueberschreibt fuer UPDATE.
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('slug')->write(required: true)->update(required: false);
        })->get();

        $this->assertFalse($type->getUpdateField('slug')->isRequired());
        $this->assertTrue($type->getCreateField('slug')->isRequired());
    }

    public function test_v2_type_relation_read()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->hasOne('author', T('Test.Author'))->write(false);
        })->get();

        $this->assertEquals(1, $type->getFields()->numEntries());
        $this->assertEquals(0, $type->getUpdateFields()->numEntries());

        $relation = $type->getRelation('author');
        $this->assertInstanceOf(V1Relation::class, $relation);
        $this->assertFalse($relation->getRelatedType()->isLink());
        $this->assertFalse($relation->getRelatedType()->isList());
    }

    public function test_v2_type_relation_mode_link_on_write()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->hasOne('gender', T('Test.Category'))->write(mode: ['link']);
        })->get();

        // READ: keine Link-Relation.
        $this->assertFalse($type->getRelation('gender')->getRelatedType()->isLink());
        // UPDATE + CREATE: link.
        $this->assertTrue($type->getUpdateRelation('gender')->getRelatedType()->isLink());
        $this->assertTrue($type->getCreateRelation('gender')->getRelatedType()->isLink());
    }

    public function test_v2_type_relation_mode_link_with_validate_and_required()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->hasOne('cancelation_reason', T('Test.Category'))
                    ->write(
                        mode: ['link'],
                        validate: fn (LinkOneValidator $v) => $v->filled(),
                        required: true,
                    );
        })->get();

        $readRelation = $type->getRelation('cancelation_reason');
        $this->assertFalse($readRelation->getRelatedType()->isLink());
        $this->assertFalse($readRelation->hasValidator());
        $this->assertFalse($readRelation->isRequired());

        $updateRelation = $type->getUpdateRelation('cancelation_reason');
        $this->assertTrue($updateRelation->getRelatedType()->isLink());
        $this->assertTrue($updateRelation->hasValidator());
        $this->assertTrue($updateRelation->isRequired());

        $createRelation = $type->getCreateRelation('cancelation_reason');
        $this->assertTrue($createRelation->getRelatedType()->isLink());
        $this->assertTrue($createRelation->hasValidator());
        $this->assertTrue($createRelation->isRequired());
    }

    public function test_v2_type_relation_mode_per_operation_via_write_block()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->hasOne('lieferadresse', T('Test.Address'))
                    ->write(function ($w) {
                        $w->update(mode: ['link', 'update']);
                        $w->create(mode: ['link', 'create']);
                    });
        })->get();

        // READ: kein link.
        $this->assertFalse($type->getRelation('lieferadresse')->getRelatedType()->isLink());
        // UPDATE: mode enthaelt 'link' → isLink=true.
        $this->assertTrue($type->getUpdateRelation('lieferadresse')->getRelatedType()->isLink());
        // CREATE: mode enthaelt 'link' → isLink=true.
        $this->assertTrue($type->getCreateRelation('lieferadresse')->getRelatedType()->isLink());
    }

    public function test_v2_type_relation_mode_create_only_is_not_link()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->hasOne('akte', T('Test.Akte'))->write(mode: ['create']);
        })->get();

        $this->assertFalse($type->getUpdateRelation('akte')->getRelatedType()->isLink());
        $this->assertFalse($type->getCreateRelation('akte')->getRelatedType()->isLink());
    }

    public function test_v2_type_relation_mode_update_only_allowed_on_update()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->hasOne('settings', T('Test.Settings'))->update(mode: ['update'])->create(false);
        })->get();

        $this->assertFalse($type->getUpdateRelation('settings')->getRelatedType()->isLink());
        $this->assertEquals(0, $type->getCreateFields()->numEntries());
    }

    public function test_v2_type_relation_mode_update_rejected_on_create()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Relation gender: mode 'update' is not allowed when creating the parent.");

        $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->hasOne('gender', T('Test.Category'))->create(mode: ['update']);
        })->get();
    }

    public function test_v2_type_relation_mode_update_via_write_rejected_globally()
    {
        // write() betrifft UPDATE+CREATE; mit 'update' im Mode greift die CREATE-Verbot-Regel.
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Relation gender: mode 'update' is not allowed when creating the parent.");

        $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->hasOne('gender', T('Test.Category'))->write(mode: ['link', 'update']);
        })->get();
    }

    public function test_v2_type_relation_mode_update_via_write_closure_rejected()
    {
        // Derselbe Check greift auch im write()-Closure-Pfad via WriteContext->mode().
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Relation gender: mode 'update' is not allowed when creating the parent.");

        $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->hasOne('gender', T('Test.Category'))->write(function ($w) {
                    $w->mode(['link', 'update']);
                });
        })->get();
    }

    public function test_v2_type_relation_mode_invalid_value_rejected()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Relation gender: invalid mode value 'foo'");

        $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->hasOne('gender', T('Test.Category'))->write(mode: ['foo']);
        })->get();
    }

    public function test_v2_type_mode_on_attribute_rejected()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Field title: mode is only valid for relations.');

        $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('title')->write(mode: ['link']);
        })->get();
    }

    public function test_v2_type_has_many()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->hasMany('tags', T('Test.Tag'))->write(false);
        })->get();

        $relation = $type->getRelation('tags');
        $this->assertTrue($relation->getRelatedType()->isList());
        $this->assertFalse($relation->getRelatedType()->isLink());
    }

    public function test_v2_type_attribute_options_request()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('status')
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
                ->hasOne('category', T('Test.Category'))->write(false)
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
                ->hasOne('country', T('Test.Country'))
                    ->write(mode: ['link'])
                    ->optionsRequest(function () {
                        return [];
                    });
        })->get();

        $readRelation = $type->getRelation('country');
        $this->assertFalse($readRelation->getRelatedType()->isLink());
        $this->assertTrue($readRelation->hasOptionsRequest());

        $this->assertTrue($type->getUpdateRelation('country')->getRelatedType()->isLink());
        $this->assertTrue($type->getUpdateRelation('country')->hasOptionsRequest());

        $this->assertTrue($type->getCreateRelation('country')->getRelatedType()->isLink());
        $this->assertTrue($type->getCreateRelation('country')->hasOptionsRequest());
    }

    public function test_v2_type_read_resolver_via_closure()
    {
        $resolveCallback = function () {
            return 'resolved';
        };

        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) use ($resolveCallback) {
            $fields
                ->string('computed')->write(false)
                    ->read(function ($r) use ($resolveCallback) {
                        $r->resolve($resolveCallback);
                    });
        })->get();

        $this->assertTrue($type->getField('computed')->hasResolver());
    }

    public function test_v2_type_default_value_via_kwarg_on_write()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('status')->write(default: 'active');
        })->get();

        // default ist per-op gespeichert; write() schreibt es in UPDATE und CREATE.
        $this->assertEquals('active', $type->getUpdateField('status')->getDefaultValue());
        $this->assertEquals('active', $type->getCreateField('status')->getDefaultValue());
    }

    public function test_v2_type_field_owner_propagated()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('name')->create(false);
        })->get();

        $this->assertEquals($type, $type->getField('name')->getOwner());
        $this->assertEquals($type, $type->getUpdateField('name')->getOwner());
    }

    // === Re-Aktivierung nach (false)-Exclusion wirft (Review Punkt 1) ===

    public function test_v2_write_false_then_update_kwarg_throws()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Field avatar: cannot call update() after update was explicitly excluded via (false).");

        $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields->string('avatar')->write(false)->update(required: true);
        })->get();
    }

    public function test_v2_write_false_then_create_mode_throws()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Field rel: cannot call create() after create was explicitly excluded via (false).");

        $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields->hasOne('rel', T('Test.Other'))->write(false)->create(mode: ['link']);
        })->get();
    }

    public function test_v2_update_false_then_update_no_args_throws()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Field foo: cannot call update() after update was explicitly excluded via (false).");

        $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields->string('foo')->update(false)->update();
        })->get();
    }

    public function test_v2_read_false_then_read_no_args_throws()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Field foo: cannot call read() after read was explicitly excluded via (false).");

        $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields->string('foo')->read(false)->read();
        })->get();
    }

    public function test_v2_double_false_is_ok()
    {
        // Doppelte Ausschluss-Aussage ist kein Widerspruch, beide setzen dieselbe Membership.
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields->string('foo')->write(false)->write(false);
        })->get();

        $this->assertEquals(1, $type->getFields()->numEntries());
        $this->assertEquals(0, $type->getUpdateFields()->numEntries());
        $this->assertEquals(0, $type->getCreateFields()->numEntries());
    }

    public function test_v2_update_false_does_not_block_create_kwarg()
    {
        // update(false) excluded NUR UPDATE; create(required:true) ist davon unberuehrt.
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields->string('foo')->update(false)->create(required: true);
        })->get();

        $this->assertEquals(0, $type->getUpdateFields()->numEntries());
        $this->assertTrue($type->getCreateField('foo')->isRequired());
    }

    // === B8: $w->update(false) im write-Closure ist last-write-wins auf Membership ===

    public function test_v2_type_write_closure_update_false_removes_from_update()
    {
        // write() setzt inUpdate=true, dann Drill-Down $w->update(false) macht inUpdate=false.
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields
                ->string('foo')
                    ->write(function ($w) {
                        $w->update(false);
                    });
        })->get();

        $this->assertEquals(1, $type->getFields()->numEntries());
        $this->assertEquals(0, $type->getUpdateFields()->numEntries());
        $this->assertEquals(1, $type->getCreateFields()->numEntries());
    }

    // === B7: write-Closure mit save-resolver (Spec workitem91:22) ===

    public function test_v2_type_write_closure_with_resolve()
    {
        $saveResolver = function () {
            return 'saved';
        };

        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) use ($saveResolver) {
            $fields
                ->string('slug')->read(false)
                    ->write(function ($w) use ($saveResolver) {
                        $w->resolve($saveResolver);
                    });
        })->get();

        $this->assertTrue($type->getUpdateField('slug')->hasResolver());
        $this->assertTrue($type->getCreateField('slug')->hasResolver());
    }

    // === B10: mode: [] (leeres Array) wirft ===

    public function test_v2_type_relation_mode_empty_array_throws()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Relation gender: mode must contain at least one value.');

        $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields->hasOne('gender', T('Test.Category'))->write(mode: []);
        })->get();
    }

    // === B11: linkOne + Override des Implizit-mode ===

    public function test_v2_type_link_one_implicit_mode_overridable()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields->linkOne('gender', T('Test.Category'))->update(mode: ['create']);
        })->get();

        // UPDATE: override auf ['create'] → kein Link mehr
        $this->assertFalse($type->getUpdateRelation('gender')->getRelatedType()->isLink());
        // CREATE: noch auf linkOne-Default ['link']
        $this->assertTrue($type->getCreateRelation('gender')->getRelatedType()->isLink());
    }

    // === B1: FieldBag-Delegation setzt keinen Default wenn keiner übergeben wurde ===

    public function test_v2_fieldbag_write_without_default_does_not_set_one()
    {
        $type = $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields->string('status')->write(required: true);
        })->get();

        $this->assertFalse($type->getUpdateField('status')->hasDefaultValue());
        $this->assertFalse($type->getCreateField('status')->hasDefaultValue());
    }

    // === B3: FieldConfigurator::read() mit Closure wirft InvalidConfigurationException ===

    public function test_v2_field_configurator_read_closure_throws()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('FieldConfigurator::read() does not accept a Closure');

        $c = new \Afeefa\ApiResources\V2\TypeConfigurator();
        $c->field('name')->read(fn ($r) => $r);
    }

    // === restrictTo auf Attribute wirft (Review Runde 2, 8 Edge-Case 1) ===

    public function test_v2_restrictTo_on_attribute_via_read_closure_throws()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Field title: restrictTo is only valid for relations.');

        $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields->string('title')->read(function ($r) {
                $r->restrictTo('count');
            });
        })->get();
    }

    // === FieldBag-Delegation auf Non-Relation wirft (Review Runde 2, 8 Edge-Case 2) ===

    public function test_v2_skipSaveRelatedIf_on_attribute_throws()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Field foo: skipSaveRelatedIf() is only valid on relations, not on attributes.');

        $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields->string('foo')->skipSaveRelatedIf(fn () => true);
        })->get();
    }

    public function test_v2_setAdditionalSaveFields_on_attribute_throws()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Field foo: setAdditionalSaveFields() is only valid on relations, not on attributes.');

        $this->v2TypeBuilder()->type('Test.V2Type', function (FieldBag $fields) {
            $fields->string('foo')->setAdditionalSaveFields(fn () => []);
        })->get();
    }
}
