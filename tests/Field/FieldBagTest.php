<?php

namespace Afeefa\ApiResources\Tests\Field;

use Afeefa\ApiResources\Bag\BagEntry;
use Afeefa\ApiResources\Bag\NotABagEntryException;
use Afeefa\ApiResources\Exception\Exceptions\NotATypeException;
use Afeefa\ApiResources\Exception\Exceptions\NotATypeOrCallbackException;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\HasOneRelation;
use Afeefa\ApiResources\Field\Fields\VarcharAttribute;
use Afeefa\ApiResources\Test\ApiResourcesTest;
use function Afeefa\ApiResources\Test\T;

class FieldBagTest extends ApiResourcesTest
{
    public function test_field_bag()
    {
        $fields = $this->container->create(FieldBag::class);

        $this->assertNull($fields->getOriginal());

        $this->assertEquals(0, $fields->numEntries());

        $fields->attribute('name', VarcharAttribute::class);

        $this->assertEquals(1, $fields->numEntries());
    }

    public function test_wrong_attribute_type()
    {
        $this->expectException(NotATypeOrCallbackException::class);
        $this->expectExceptionMessage('Argument is not a known class: Hoho');

        $fields = $this->container->create(FieldBag::class);
        $fields->attribute('name', 'Hoho');
    }

    public function test_relation()
    {
        /** @var FieldBag */
        $fields = $this->container->create(FieldBag::class);
        $fields->relation('name', T('Test.Type'), HasOneRelation::class);

        $relation = $fields->getRelation('name');
        $relation2 = $fields->get('name');

        $this->assertSame($relation, $relation2);
        $this->assertEquals(T('Test.Type'), $relation->getRelatedType()->getTypeClass());
    }

    public function test_wrong_relation_related_type()
    {
        $this->expectException(NotATypeException::class);
        $this->expectExceptionMessage('Value for relation name is not a type.');

        $fields = $this->container->create(FieldBag::class);
        $fields->relation('name', 'Hoho', HasOneRelation::class);
    }

    public function test_wrong_relation_type()
    {
        $this->expectException(NotATypeOrCallbackException::class);
        $this->expectExceptionMessage('Argument is not a known class: RelationClass');

        $fields = $this->container->create(FieldBag::class);
        $fields->relation('name', T('Test.Type'), 'RelationClass');
    }

    public function test_set_disabled()
    {
        $fields = $this->container->create(FieldBag::class);
        $fields->set('name', new BagEntry());

        $this->assertEquals(0, $fields->numEntries());
    }

    public function test_has()
    {
        $fields = $this->container->create(FieldBag::class);

        $this->assertFalse($fields->has('name'));
        $this->assertFalse($fields->has('name', true));

        $fields->attribute('name', VarcharAttribute::class);

        $this->assertTrue($fields->has('name'));
        $this->assertTrue($fields->has('name', true));
    }

    public function test_field_by_default_allowed()
    {
        $fields = $this->container->create(FieldBag::class);
        $fields->attribute('name', VarcharAttribute::class);

        $this->assertTrue($fields->get('name')->isAllowed());
    }

    public function test_clone()
    {
        $originalFields = $this->container->create(FieldBag::class);
        $originalFields->attribute('name', VarcharAttribute::class);

        $this->assertTrue($originalFields->has('name'));
        $this->assertTrue($originalFields->has('name', true));

        $originalNameField = $originalFields->get('name');

        $fields = $originalFields->clone();

        $this->assertNotSame($originalFields, $fields);

        $this->assertTrue($fields->has('name'));
        $this->assertFalse($fields->has('name', true));

        $nameField = $fields->get('name');

        $this->assertNotSame($nameField, $originalNameField);

        $this->assertSame($originalFields, $fields->getOriginal());
    }

    public function test_cloned_field_by_default_not_allowed()
    {
        $originalFields = $this->container->create(FieldBag::class);
        $originalFields->attribute('name', VarcharAttribute::class);

        $fields = $originalFields->clone();

        $this->assertFalse($fields->get('name')->isAllowed()); // default not allowed

        $fields->allow(['name']);

        $this->assertTrue($fields->get('name')->isAllowed());
    }

    public function test_allow()
    {
        $fields = $this->container->create(FieldBag::class);
        $fields->attribute('name', VarcharAttribute::class);

        $this->assertTrue($fields->get('name')->isAllowed());

        $fields->allow(['name']);

        $this->assertTrue($fields->get('name')->isAllowed());
    }

    public function test_allow_unknown_field()
    {
        $this->expectException(NotABagEntryException::class);
        $this->expectExceptionMessage('hoho is not a known Bag entry.');

        $fields = $this->container->create(FieldBag::class);
        $fields->allow(['hoho']);
    }
}
