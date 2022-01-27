<?php

namespace Afeefa\ApiResources\Tests\Field;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Api\ApiRequest;
use Afeefa\ApiResources\Exception\Exceptions\MissingTypeException;
use Afeefa\ApiResources\Test\ApiResourcesTest;
use function Afeefa\ApiResources\Test\createApiWithSingleType;
use Afeefa\ApiResources\Test\FieldBuilder;
use function Afeefa\ApiResources\Test\T;
use Closure;
use Error;

class FieldTest extends ApiResourcesTest
{
    public function test_defaults()
    {
        $field = (new FieldBuilder())->field('Field')->get()
            ->name('title');

        $this->assertEquals('Field', $field::type());
        $this->assertEquals('title', $field->getName());
        $this->assertFalse($field->isRequired());
        $this->assertFalse($field->hasOptions());
        $this->assertSame([], $field->getOptions());
        $this->assertFalse($field->hasOptionsRequest());
        $this->assertNull($field->getOptionsRequest());
        $this->assertFalse($field->hasResolver());
        $this->assertFalse($field->hasSaveResolver());
        $this->assertFalse($field->hasSaveResolver());
        $this->assertFalse($field->hasResolveParam('something'));
        $this->assertSame([], $field->getResolveParams());
    }

    public function test_options()
    {
        $field = (new FieldBuilder())->field('Field')->get()
            ->name('title')
            ->options(['great', 'cool']);

        $this->assertSame(['great', 'cool'], $field->getOptions());
    }

    public function test_options_request()
    {
        createApiWithSingleType();

        $field = $this->fieldBuilder()->field('Field')->get()
            ->optionsRequest(function (ApiRequest $request) {
                $request
                    ->resourceType('Test.Resource')
                    ->actionName('test_action')
                    ->fields(['name' => true]);
            });

        $request = $field->getOptionsRequest();

        $this->assertInstanceOf(ApiRequest::class, $request);

        $this->assertEquals('Test.Api', $request->getApi()::type());
        $this->assertEquals('Test.Resource', $request->getResource()::type());
        $this->assertEquals('test_action', $request->getAction()->getName());
    }

    public function test_clone()
    {
        $originalField = (new FieldBuilder())->field('Field')->get()
            ->name('title');
        $field = $originalField->clone();

        $this->assertEquals('Field', $field::type());
        $this->assertEquals('title', $field->getName());
        $this->assertFalse($field->isRequired());
        $this->assertFalse($field->hasOptions());
        $this->assertSame([], $field->getOptions());
        $this->assertFalse($field->hasOptionsRequest());
        $this->assertNull($field->getOptionsRequest());
        $this->assertFalse($field->hasResolver());
        $this->assertFalse($field->hasSaveResolver());
        $this->assertFalse($field->hasSaveResolver());
        $this->assertFalse($field->hasResolveParam('something'));
        $this->assertSame([], $field->getResolveParams());
    }

    public function test_clone_required()
    {
        $originalField = (new FieldBuilder())->field('Field')->get()
            ->name('title')
            ->required();
        $this->assertTrue($originalField->isRequired());

        $field = $originalField->clone();

        $this->assertTrue($field->isRequired());
    }

    public function test_clone_options()
    {
        $originalField = (new FieldBuilder())->field('Field')->get()
            ->name('title')
            ->options(['great', 'cool']);
        $this->assertSame(['great', 'cool'], $originalField->getOptions());

        $field = $originalField->clone();

        $this->assertSame(['great', 'cool'], $field->getOptions());

        $originalField->options(['bad', 'sad']);
        $this->assertSame(['bad', 'sad'], $originalField->getOptions());
        $this->assertSame(['great', 'cool'], $field->getOptions());
    }

    public function test_clone_options_request()
    {
        $this->apiBuilder()->api('API', function (Closure $addResource) {
            $addResource('RES', function (Closure $addAction) {
                $addAction('ACT', function (Action $action) {
                    $action
                        ->input(T('TYPE'))
                        ->response(T('TYPE'))
                        ->resolve(function () {
                        });
                });
            });
            $addResource('RES2', function (Closure $addAction) {
                $addAction('ACT2', function (Action $action) {
                    $action
                        ->input(T('TYPE'))
                        ->response(T('TYPE'))
                        ->resolve(function () {
                        });
                });
            });
        })->get();

        $originalField = $this->fieldBuilder()->field('FIELD')->get()
            ->name('test_field')
            ->optionsRequest(function (ApiRequest $request) {
                $request
                    ->resourceType('RES')
                    ->actionName('ACT');
            });

        // original
        $originalRequest = $originalField->getOptionsRequest();
        $this->assertEquals('API', $originalRequest->getApi()::type());
        $this->assertEquals('RES', $originalRequest->getResource()::type());
        $this->assertEquals('ACT', $originalRequest->getAction()->getName());

        // clone
        $field = $originalField->clone();

        // request same as original
        $request = $field->getOptionsRequest();
        $this->assertInstanceOf(ApiRequest::class, $request);
        $this->assertEquals('API', $request->getApi()::type());
        $this->assertEquals('RES', $request->getResource()::type());
        $this->assertEquals('ACT', $request->getAction()->getName());

        // update original
        $originalField->optionsRequest(function (ApiRequest $request) {
            $request
                ->resourceType('RES2')
                ->actionName('ACT2');
        });
        $originalRequest = $originalField->getOptionsRequest();
        $this->assertEquals('API', $originalRequest->getApi()::type());
        $this->assertEquals('RES2', $originalRequest->getResource()::type());
        $this->assertEquals('ACT2', $originalRequest->getAction()->getName());

        // request not changed after original update
        $request = $field->getOptionsRequest();
        $this->assertEquals('API', $request->getApi()::type());
        $this->assertEquals('RES', $request->getResource()::type());
        $this->assertEquals('ACT', $request->getAction()->getName());

        // clone again
        $field = $originalField->clone();

        // request again same as original
        $request = $field->getOptionsRequest();
        $this->assertInstanceOf(ApiRequest::class, $request);
        $this->assertEquals('API', $request->getApi()::type());
        $this->assertEquals('RES2', $request->getResource()::type());
        $this->assertEquals('ACT2', $request->getAction()->getName());
    }

    public function test_get_type_with_missing_type()
    {
        $this->expectException(MissingTypeException::class);
        $this->expectExceptionMessageMatches('/^Missing type for class Afeefa\\\ApiResources\\\Test\\\TestField@anonymous/');

        $field = (new FieldBuilder())->field()->get();

        $field->type();
    }

    public function test_get_name_missing_name()
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Typed property Afeefa\ApiResources\Field\Field::$name must not be accessed before initialization');

        $field = (new FieldBuilder())->field()->get();

        $field->getName();
    }
}
