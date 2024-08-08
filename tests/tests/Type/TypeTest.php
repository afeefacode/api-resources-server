<?php

namespace Afeefa\ApiResources\Tests\Type;

use Afeefa\ApiResources\Exception\Exceptions\MissingTypeException;
use Afeefa\ApiResources\Exception\Exceptions\NotATypeOrCallbackException;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\StringAttribute;
use Afeefa\ApiResources\Test\ApiResourcesTest;

use function Afeefa\ApiResources\Test\T;

class TypeTest extends ApiResourcesTest
{
    public function test_type()
    {
        $type = $this->typeBuilder()->type('Test.Type')->get();

        $this->assertEquals('Test.Type', $type::type());

        $this->assertEquals(0, $type->getFields()->numEntries());
        $this->assertEquals(0, $type->getUpdateFields()->numEntries());
        $this->assertEquals(0, $type->getCreateFields()->numEntries());
    }

    public function test_wrong_attribute_type()
    {
        $this->expectException(NotATypeOrCallbackException::class);
        $this->expectExceptionMessage('Argument is not a known class or interface: Hoho');

        $this->typeBuilder()->type('Test.Type', function (FieldBag $fields) {
            $fields->attribute('name', 'Hoho');
        })->get();
    }

    public function test_type_with_fields()
    {
        $type = $this->typeBuilder()->type('Test.Type', function (FieldBag $fields) {
            $fields->attribute('name', StringAttribute::class);
        })->get();

        $this->assertEquals(1, $type->getFields()->numEntries());
        $this->assertEquals(0, $type->getUpdateFields()->numEntries());
        $this->assertEquals(0, $type->getCreateFields()->numEntries());

        $field = $type->getField('name');
        $this->assertEquals($type, $field->getOwner());
    }

    public function test_get_type_with_missing_type()
    {
        $this->expectException(MissingTypeException::class);
        $this->expectExceptionMessageMatches('/^Missing type for class Afeefa\\\ApiResources\\\Test\\\TestType@anonymous/');

        $type = $this->typeBuilder()->type()->get();

        $type::type();
    }

    public function test_get_all_related_type_classes()
    {
        $type = $this->typeBuilder()->type(
            'Test.Type',
            function (FieldBag $fields) {
                $fields->relation('type1', T('Test.Type1'));
            },
            function (FieldBag $updateFields) {
                $updateFields->relation('type2', T('Test.Type2'));
            },
            function (FieldBag $createFields) {
                $createFields->relation('type3', T('Test.Type3'));
            }
        )->get();

        $TypeClasses = [
            T('Test.Type1'),
            T('Test.Type2'),
            T('Test.Type3')
        ];

        $this->assertEquals($TypeClasses, $type->getAllRelatedTypeClasses());
    }
}
