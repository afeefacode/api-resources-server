<?php

namespace Afeefa\ApiResources\Tests\Action;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Action\ActionInput;
use Afeefa\ApiResources\Action\ActionParams;
use Afeefa\ApiResources\Action\ActionResponse;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Exception\Exceptions\NotACallbackException;
use Afeefa\ApiResources\Exception\Exceptions\NotATypeException;
use Afeefa\ApiResources\Field\Fields\StringAttribute;
use Afeefa\ApiResources\Test\ActionBuilder;
use Afeefa\ApiResources\Test\ApiResourcesTest;

use function Afeefa\ApiResources\Test\T;
use Afeefa\ApiResources\Type\Type;
use Error;

class ActionTest extends ApiResourcesTest
{
    public function test_missing_name()
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Typed property Afeefa\ApiResources\Action\Action::$name must not be accessed before initialization');

        $action = new Action();

        $this->assertNull($action->getName());
    }

    public function test_name()
    {
        $action = new Action();

        $action->name('hans');
        $this->assertEquals('hans', $action->getName());
    }

    public function test_params()
    {
        $action = (new ActionBuilder())->get();

        $this->assertFalse($action->hasParam('my_param'));

        $action->params(function (ActionParams $params) {
            $params->attribute('my_param', StringAttribute::class);
        });

        $this->assertTrue($action->hasParam('my_param'));
        $this->assertInstanceOf(StringAttribute::class, $action->getParam('my_param'));
    }

    public function test_params_get_nonexistent()
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Typed property Afeefa\ApiResources\Action\Action::$params must not be accessed before initialization');

        $action = (new ActionBuilder())->get();
        $action->getParam('my_param');
    }

    public function test_input()
    {
        $type = $this->typeBuilder()->type('Test.Type')->get();
        $action = (new ActionBuilder())->get();

        $this->assertFalse($action->hasInput());

        $action->input($type::class);

        $this->assertTrue($action->hasInput());

        $input = $action->getInput();

        $this->assertInstanceOf(ActionInput::class, $input);
        $this->assertEquals(T('Test.Type'), $input->getTypeClass());
        $this->assertEquals([], $input->getTypeClasses());
        $this->assertFalse($input->isList());
    }

    public function test_input_list()
    {
        $type = $this->typeBuilder()->type('Test.Type')->get();
        $action = (new ActionBuilder())->get();

        $action->input(Type::list($type::class));

        $input = $action->getInput();

        $this->assertEquals(T('Test.Type'), $input->getTypeClass());
        $this->assertEquals([], $input->getTypeClasses());
        $this->assertTrue($input->isList());
    }

    public function test_input_union()
    {
        $action = (new ActionBuilder())->get();

        $TypeClasses = [
            T('Test.Type'),
            T('Test.Type2'),
            T('Test.Type3'),
        ];

        $action->input($TypeClasses);

        $input = $action->getInput();

        $this->assertNull($input->getTypeClass());
        $this->assertEquals($TypeClasses, $input->getTypeClasses());
        $this->assertFalse($input->isList());
    }

    public function test_input_union_unique()
    {
        $action = (new ActionBuilder())->get();

        $TypeClasses = [
            T('Test.Type'),
            T('Test.Type')
        ];

        $action->input($TypeClasses);

        $input = $action->getInput();

        $this->assertEquals(T('Test.Type'), $input->getTypeClass());
        $this->assertEquals([], $input->getTypeClasses());
        $this->assertFalse($input->isList());
    }

    public function test_input_union_unique2()
    {
        $action = (new ActionBuilder())->get();

        $TypeClasses = [
            T('Test.Type'),
            T('Test.Type'),
            T('Test.Type3')
        ];

        $action->input($TypeClasses);

        $input = $action->getInput();

        $ExpectedTypeClasses = [T('Test.Type'), T('Test.Type3')];

        $this->assertNull($input->getTypeClass());
        $this->assertEquals($ExpectedTypeClasses, $input->getTypeClasses());
        $this->assertFalse($input->isList());
    }

    public function test_input_union_list()
    {
        $action = (new ActionBuilder())->get();

        $TypeClasses = [
            T('Test.Type'),
            T('Test.Type2'),
            T('Test.Type3'),
        ];

        $action->input(Type::list($TypeClasses));

        $input = $action->getInput();

        $this->assertNull($input->getTypeClass());
        $this->assertEquals($TypeClasses, $input->getTypeClasses());
        $this->assertTrue($input->isList());
    }

    public function test_input_union_list_unique()
    {
        $action = (new ActionBuilder())->get();

        $TypeClasses = [
            T('Test.Type'),
            T('Test.Type')
        ];

        $action->input(Type::list($TypeClasses));

        $input = $action->getInput();

        $this->assertEquals(T('Test.Type'), $input->getTypeClass());
        $this->assertEquals([], $input->getTypeClasses());
        $this->assertTrue($input->isList());
    }

    public function test_input_union_list_unique2()
    {
        $action = (new ActionBuilder())->get();

        $TypeClasses = [
            T('Test.Type'),
            T('Test.Type'),
            T('Test.Type3')
        ];

        $action->input(Type::list($TypeClasses));

        $input = $action->getInput();

        $ExpectedTypeClasses = [T('Test.Type'), T('Test.Type3')];

        $this->assertNull($input->getTypeClass());
        $this->assertEquals($ExpectedTypeClasses, $input->getTypeClasses());
        $this->assertTrue($input->isList());
    }

    public function test_input_invalid_type()
    {
        $this->expectException(NotATypeException::class);
        $this->expectExceptionMessage('Value for input $TypeClassOrClasses is not a type.');

        $action = (new ActionBuilder())->get();
        $action->input('TEST');
    }

    public function test_input_invalid_type_list()
    {
        $this->expectException(NotATypeException::class);
        $this->expectExceptionMessage('Value for input $TypeClassOrClasses is not a type.');

        $action = (new ActionBuilder())->get();
        $action->input(Type::list('TEST'));
    }

    public function test_input_invalid_type_union()
    {
        $this->expectException(NotATypeException::class);
        $this->expectExceptionMessage('Value for input $TypeClassOrClasses is not a list of types.');

        $action = (new ActionBuilder())->get();
        $action->input(['TEST', 'TEST2']);
    }

    public function test_input_invalid_type_union_list()
    {
        $this->expectException(NotATypeException::class);
        $this->expectExceptionMessage('Value for input $TypeClassOrClasses is not a list of types.');

        $action = (new ActionBuilder())->get();
        $action->input(Type::list(['TEST', 'TEST2']));
    }

    public function test_input_invalid_type_number()
    {
        $this->expectException(NotATypeException::class);
        $this->expectExceptionMessage('Value for input $TypeClassOrClasses is not a type or a list of types.');

        $action = (new ActionBuilder())->get();
        $action->input(123);
    }

    public function test_missing_input()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Mutation action test_action does not have an input type.');

        $action = (new ActionBuilder())->action('test_action')->get();
        $this->assertFalse($action->hasInput());

        $action->getInput();
    }

    public function test_response()
    {
        $type = $this->typeBuilder()->type('Test.Type')->get();
        $action = (new ActionBuilder())->get();

        $this->assertFalse($action->hasResponse());

        $action->response($type::class);

        $this->assertTrue($action->hasResponse());

        $response = $action->getResponse();

        $this->assertInstanceOf(ActionResponse::class, $response);
        $this->assertEquals(T('Test.Type'), $response->getTypeClass());
        $this->assertEquals([], $response->getTypeClasses());
        $this->assertFalse($response->isList());
    }

    public function test_response_list()
    {
        $type = $this->typeBuilder()->type('Test.Type')->get();
        $action = (new ActionBuilder())->get();

        $action->response(Type::list($type::class));

        $response = $action->getResponse();

        $this->assertEquals(T('Test.Type'), $response->getTypeClass());
        $this->assertEquals([], $response->getTypeClasses());
        $this->assertTrue($response->isList());
    }

    public function test_response_union()
    {
        $action = (new ActionBuilder())->get();

        $TypeClasses = [
            T('Test.Type'),
            T('Test.Type2'),
            T('Test.Type3'),
        ];

        $action->response($TypeClasses);

        $response = $action->getResponse();

        $this->assertNull($response->getTypeClass());
        $this->assertEquals($TypeClasses, $response->getTypeClasses());
        $this->assertFalse($response->isList());
    }

    public function test_response_union_list()
    {
        $action = (new ActionBuilder())->get();

        $TypeClasses = [
            T('Test.Type'),
            T('Test.Type2'),
            T('Test.Type3'),
        ];

        $action->response(Type::list($TypeClasses));

        $response = $action->getResponse();

        $this->assertNull($response->getTypeClass());
        $this->assertEquals($TypeClasses, $response->getTypeClasses());
        $this->assertTrue($response->isList());
    }

    public function test_response_invalid_type()
    {
        $this->expectException(NotATypeException::class);
        $this->expectExceptionMessage('Value for response $TypeClassOrClasses is not a type.');

        $action = (new ActionBuilder())->get();
        $action->response('TEST');
    }

    public function test_response_invalid_type_list()
    {
        $this->expectException(NotATypeException::class);
        $this->expectExceptionMessage('Value for response $TypeClassOrClasses is not a type.');

        $action = (new ActionBuilder())->get();
        $action->response(Type::list('TEST'));
    }

    public function test_response_invalid_type_union()
    {
        $this->expectException(NotATypeException::class);
        $this->expectExceptionMessage('Value for response $TypeClassOrClasses is not a list of types.');

        $action = (new ActionBuilder())->get();
        $action->response(['TEST', 'TEST2']);
    }

    public function test_response_invalid_type_union_list()
    {
        $this->expectException(NotATypeException::class);
        $this->expectExceptionMessage('Value for response $TypeClassOrClasses is not a list of types.');

        $action = (new ActionBuilder())->get();
        $action->response(Type::list(['TEST', 'TEST2']));
    }

    public function test_response_invalid_type_number()
    {
        $this->expectException(NotATypeException::class);
        $this->expectExceptionMessage('Value for response $TypeClassOrClasses is not a type or a list of types.');

        $action = (new ActionBuilder())->get();
        $action->response(123);
    }

    public function test_missing_response()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Action test_action does not have a response type.');

        $action = (new ActionBuilder())->action('test_action')->get();

        $this->assertInstanceOf(ActionResponse::class, $action->getResponse());
    }

    public function test_missing_resolver()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Action my_action does not have a resolver.');

        $action = (new ActionBuilder())->action('my_action')->get();

        $action->getResolve();
    }

    public function test_invalid_resolver()
    {
        $this->expectException(NotACallbackException::class);
        $this->expectExceptionMessage('Resolve callback for action my_action is not callable.');

        $action = (new ActionBuilder())->action('my_action')->get();
        $action->resolve('nix');

        $action->getResolve();
    }

    public function test_missing_resolver_in_schema()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Action my_action does not have a resolver.');

        $action = (new ActionBuilder())
            ->action('my_action', function (Action $action) {
                $action->response(T('Test.Type'));
            })
            ->get();

        $action->toSchemaJson();
    }
}
