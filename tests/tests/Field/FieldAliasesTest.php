<?php

namespace Afeefa\ApiResources\Tests\Field;

use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\BooleanAttribute;
use Afeefa\ApiResources\Field\Fields\DateAttribute;
use Afeefa\ApiResources\Field\Fields\EnumAttribute;
use Afeefa\ApiResources\Field\Fields\IntAttribute;
use Afeefa\ApiResources\Field\Fields\NumberAttribute;
use Afeefa\ApiResources\Field\Fields\StringAttribute;
use Afeefa\ApiResources\Test\ApiResourcesTest;
use function Afeefa\ApiResources\Test\T;

use Afeefa\ApiResources\Type\Type;
use Afeefa\ApiResources\Validator\Validators\DateValidator;
use Afeefa\ApiResources\Validator\Validators\IntValidator;
use Afeefa\ApiResources\Validator\Validators\LinkOneValidator;
use Afeefa\ApiResources\Validator\Validators\NumberValidator;
use Afeefa\ApiResources\Validator\Validators\StringValidator;

use Closure;

class FieldAliasesTest extends ApiResourcesTest
{
    protected bool $callbackCalled = false;
    protected bool $validateCalled = false;
    protected bool $resolveCalled = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->callbackCalled = false;
        $this->validateCalled = false;
        $this->resolveCalled = false;
    }

    /**
     * @dataProvider attributesDataprovider
     */
    public function test_attributes($attributeType, $AttributeClass)
    {
        $type = $this->typeBuilder()->type('Test.Type', function (FieldBag $fields) use ($attributeType) {
            $fields->$attributeType($attributeType);
        })->get();

        $this->assertEquals($AttributeClass::type(), $type->getField($attributeType)->type());

        // same as above in traditional style:

        $type = $this->typeBuilder()->type('Test.Type', function (FieldBag $fields) use ($attributeType, $AttributeClass) {
            $fields->attribute($attributeType, $AttributeClass);
        })->get();

        $this->assertEquals($AttributeClass::type(), $type->getField($attributeType)->type());
    }

    /**
     * @dataProvider attributesDataprovider
     */
    public function test_attributes_with_callback($attributeType, $AttributeClass)
    {
        $type = $this->typeBuilder()->type('Test.Type', function (FieldBag $fields) use ($attributeType, $AttributeClass) {
            $callback = function ($a) use ($AttributeClass) {
                $this->assertTrue($a instanceof $AttributeClass);
                $this->callbackCalled = true;
            };
            $fields->$attributeType($attributeType, $callback);
        })->get();

        $this->assertEquals($AttributeClass::type(), $type->getField($attributeType)->type());

        $this->assertTrue($this->callbackCalled);
    }

    /**
     * @dataProvider attributesDataprovider
     */
    public function test_attributes_with_validate($attributeType, $AttributeClass)
    {
        $validate = $this->getValidate($attributeType);

        if (!$validate) {
            return $this->assertTrue(true);
        }

        $type = $this->typeBuilder()->type('Test.Type', function (FieldBag $fields) use ($attributeType, $validate) {
            $fields->$attributeType($attributeType, validate: $validate);
        })->get();

        $attribute = $type->getField($attributeType);
        $this->assertEquals($AttributeClass::type(), $attribute->type());

        $this->assertTrue($this->validateCalled);
    }

    public function attributesDataprovider()
    {
        return [
            'string' => [
                'string',
                StringAttribute::class
            ],
            'date' => [
                'date',
                DateAttribute::class
            ],
            'boolean' => [
                'boolean',
                BooleanAttribute::class
            ],
            'int' => [
                'int',
                IntAttribute::class
            ],
            'number' => [
                'number',
                NumberAttribute::class
            ],
            'enum' => [
                'enum',
                EnumAttribute::class
            ]
        ];
    }

    /**
     * @dataProvider relationsDataprovider
     */
    public function test_relations($relationType, $isList, $isLink)
    {
        $type = $this->typeBuilder()->type('Test.Type', function (FieldBag $fields) use ($relationType) {
            $fields->$relationType($relationType, T('T1'));
        })->get();

        $relation = $type->getRelation($relationType);
        $this->assertEquals($isList, $relation->getRelatedType()->isList());
        $this->assertEquals($isLink, $relation->getRelatedType()->isLink());
        $this->assertEquals(T('T1'), $relation->getRelatedType()->getTypeClass());

        // same as above in traditional style:

        $type = $this->typeBuilder()->type('Test.Type', function (FieldBag $fields) use ($relationType) {
            $TypeClassOrClassesOrMeta = T('T1');
            if (in_array($relationType, ['linkOne', 'linkMany'])) {
                $TypeClassOrClassesOrMeta = Type::link($TypeClassOrClassesOrMeta);
            }
            if (in_array($relationType, ['linkMany', 'hasMany'])) {
                $TypeClassOrClassesOrMeta = Type::list($TypeClassOrClassesOrMeta);
            }

            $fields->relation($relationType, $TypeClassOrClassesOrMeta);
        })->get();

        $relation = $type->getRelation($relationType);
        $this->assertEquals($isList, $relation->getRelatedType()->isList());
        $this->assertEquals($isLink, $relation->getRelatedType()->isLink());
        $this->assertEquals(T('T1'), $relation->getRelatedType()->getTypeClass());
    }

    /**
     * @dataProvider relationsDataprovider
     */
    public function test_relations_with_validate($relationType)
    {
        $validate = $this->getValidate($relationType);

        if (!$validate) {
            return $this->assertTrue(true);
        }

        $type = $this->typeBuilder()->type('Test.Type', function (FieldBag $fields) use ($relationType, $validate) {
            $fields->$relationType($relationType, T('T1'), validate: $validate);
        })->get();

        $relation = $type->getRelation($relationType);
        $this->assertEquals(T('T1'), $relation->getRelatedType()->getTypeClass());

        $this->assertTrue($this->validateCalled);
    }

    public function relationsDataprovider()
    {
        return [
            'hasOne' => [
                'hasOne',
                false,
                false
            ],
            'hasMany' => [
                'hasMany',
                true,
                false
            ],
            'linkOne' => [
                'linkOne',
                false,
                true
            ],
            'linkMany' => [
                'linkMany',
                true,
                true
            ]
        ];
    }

    protected function getValidate(string $attributeType): ?Closure
    {
        return match ($attributeType) {
            'string' => function (StringValidator $validator) {
                $this->assertInstanceOf(StringValidator::class, $validator);
                $this->validateCalled = true;
            },
            'date' => function (DateValidator $validator) {
                $this->assertInstanceOf(DateValidator::class, $validator);
                $this->validateCalled = true;
            },
            'int' => function (IntValidator $validator) {
                $this->assertInstanceOf(IntValidator::class, $validator);
                $this->validateCalled = true;
            },
            'number' => function (NumberValidator $validator) {
                $this->assertInstanceOf(NumberValidator::class, $validator);
                $this->validateCalled = true;
            },
            'linkOne' => function (LinkOneValidator $validator) {
                $this->assertInstanceOf(LinkOneValidator::class, $validator);
                $this->validateCalled = true;
            },
            default => null
        };
    }
}
