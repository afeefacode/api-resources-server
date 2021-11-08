<?php

namespace Afeefa\ApiResources\Tests\Api;

use Afeefa\ApiResources\Exception\Exceptions\MissingTypeException;
use Afeefa\ApiResources\Field\FieldBag;

use Afeefa\ApiResources\Field\Fields\VarcharAttribute;
use function Afeefa\ApiResources\Test\createApiWithSingleType;

use Afeefa\ApiResources\Test\TestValidator;

use Afeefa\ApiResources\Test\TypeRegistry;
use Afeefa\ApiResources\Test\ValidatorBuilder;
use Afeefa\ApiResources\Validator\Rule\RuleBag;
use PHPUnit\Framework\TestCase;

class SchemaValidatorTest extends TestCase
{
    protected function setUp(): void
    {
        TypeRegistry::reset();
    }

    public function test_simple()
    {
        /** @var TestValidator */
        $validator = (new ValidatorBuilder())
            ->validator(
                'Test.Validator',
                function (RuleBag $rules) {
                    $rules
                        ->add('min')
                        ->message('{{ fieldLabel }} should be greater than {{ param }}.');

                    $rules
                        ->add('max')
                        ->message('{{ fieldLabel }} should be lesser than {{ param }}.');
                }
            )
            ->get();

        $api = createApiWithSingleType(
            'Test.Type',
            function (FieldBag $fields) use ($validator) {
                $fields
                    ->attribute('title', function (VarcharAttribute $attribute) use ($validator) {
                        $attribute->validate($validator->min(4)->max(14));
                    });
            }
        );

        $schema = $api->toSchemaJson();

        $expectedTypesSchema = [
            'Test.Type' => [
                'translations' => [],
                'fields' => [
                    'title' => [
                        'type' => 'Afeefa.VarcharAttribute',
                        'validator' => [
                            'type' => 'Test.Validator',
                            'params' => [
                                'min' => 4,
                                'max' => 14
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->assertEquals($expectedTypesSchema, $schema['types']);

        $expectedValidatorsSchema = [
            'Test.Validator' => [
                'rules' => [
                    'min' => [
                        'message' => '{{ fieldLabel }} should be greater than {{ param }}.'
                    ],
                    'max' => [
                        'message' => '{{ fieldLabel }} should be lesser than {{ param }}.'
                    ]
                ]
            ]
        ];

        $this->assertEquals($expectedValidatorsSchema, $schema['validators']);
    }

    public function test_get_type_with_missing_type()
    {
        $this->expectException(MissingTypeException::class);
        $this->expectExceptionMessageMatches('/^Missing type for class Afeefa\\\ApiResources\\\Test\\\TestValidator@anonymous/');

        $validator = (new ValidatorBuilder())
            ->validator()
            ->get();

        $validator->type();
    }
}
