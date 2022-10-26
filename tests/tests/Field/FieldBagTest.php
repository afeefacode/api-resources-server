<?php

namespace Afeefa\ApiResources\Tests\Field;

use Afeefa\ApiResources\Bag\BagEntry;
use Afeefa\ApiResources\Bag\NotABagEntryException;
use Afeefa\ApiResources\Exception\Exceptions\NotATypeException;
use Afeefa\ApiResources\Exception\Exceptions\NotATypeOrCallbackException;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\StringAttribute;
use Afeefa\ApiResources\Test\ApiResourcesTest;
use function Afeefa\ApiResources\Test\T;

use Afeefa\ApiResources\Validator\Validators\StringValidator;

class FieldBagTest extends ApiResourcesTest
{
    public function test_field_bag()
    {
        $fields = $this->container->create(FieldBag::class);

        $this->assertEquals(0, $fields->numEntries());

        $fields->attribute('name', StringAttribute::class);

        $this->assertEquals(1, $fields->numEntries());
    }

    public function test_wrong_attribute_type()
    {
        $this->expectException(NotATypeOrCallbackException::class);
        $this->expectExceptionMessage('Argument is not a known class or interface: Hoho');

        $fields = $this->container->create(FieldBag::class);
        $fields->attribute('name', 'Hoho');
    }

    public function test_relation()
    {
        /** @var FieldBag */
        $fields = $this->container->create(FieldBag::class);
        $fields->relation('name', T('Test.Type'));

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
        $fields->relation('name', 'Hoho');
    }

    public function test_wrong_relation_type()
    {
        $this->expectException(NotATypeOrCallbackException::class);
        $this->expectExceptionMessage('Argument is not a known class or interface: RelationClass');

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

        $fields->attribute('name', StringAttribute::class);

        $this->assertTrue($fields->has('name'));
        $this->assertTrue($fields->has('name', true));
    }

    public function test_from()
    {
        $fields = $this->container->create(FieldBag::class);
        $fields->attribute('name', StringAttribute::class);

        $fields2 = $this->container->create(FieldBag::class);
        $fields2->from($fields, 'name');

        $this->assertTrue($fields->has('name'));
        $this->assertTrue($fields->has('name', true));
    }

    public function test_from_clones()
    {
        /** @var FieldBag */
        $fields = $this->container->create(FieldBag::class);
        $fields->attribute('name', function (StringAttribute $attribute) {
            $attribute
                ->required()
                ->validate(function (StringValidator $v) {
                    $v->min(10);
                })
                ->options([1])
                ->resolve(fn () => null);
        });

        /** @var FieldBag */
        $fields2 = $this->container->create(FieldBag::class);
        $fields2->from($fields, 'name');

        $this->assertTrue($fields->has('name'));
        $this->assertTrue($fields->get('name')->isRequired());

        $field = $fields->get('name');
        $field2 = $fields2->get('name');
        $this->assertNotSame($field, $field2);

        $this->assertSame($field->getOptions(), $field2->getOptions());
        $this->assertSame($field->getResolve(), $field2->getResolve());
        $this->assertNotSame($field->getValidator(), $field2->getValidator());

        $this->assertSame($field->getValidator()->getParams(), $field2->getValidator()->getParams());

        /** @var StringValidator */
        $validator = $field2->getValidator();
        $validator->max(5);
        $this->assertNotSame($field->getValidator()->getParams(), $field2->getValidator()->getParams());
    }

    public function test_from_unknown_field()
    {
        $this->expectException(NotABagEntryException::class);
        $this->expectExceptionMessage('hoho is not a known Bag entry.');

        $fields = $this->container->create(FieldBag::class);
        $fields->from($fields, 'hoho');
    }
}
