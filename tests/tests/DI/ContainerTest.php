<?php

namespace Afeefa\ApiResources\Tests\DI;

use Afeefa\ApiResources\DI\Container;
use function Afeefa\ApiResources\DI\invokeResolverCallback;
use Afeefa\ApiResources\Exception\Exceptions\NotATypeException;
use Afeefa\ApiResources\Exception\Exceptions\NotATypeOrCallbackException;
use Afeefa\ApiResources\Exception\Exceptions\TooManyCallbackArgumentsException;
use Afeefa\ApiResources\Tests\DI\Fixtures\TestInterface;
use Afeefa\ApiResources\Tests\DI\Fixtures\TestModel;
use Afeefa\ApiResources\Tests\DI\Fixtures\TestService;
use Afeefa\ApiResources\Tests\DI\Fixtures\TestService2;
use Afeefa\ApiResources\Tests\DI\Fixtures\TestService3;
use PHPUnit\Framework\TestCase;

class ContainerTest extends TestCase
{
    public function test_create()
    {
        $container = new Container();

        $this->assertTrue($container->has(Container::class));

        $this->assertCount(1, $container->entries());

        $this->assertFalse($container->has(TestService::class));

        $this->assertSame($container, array_values($container->entries())[0]);
    }

    public function test_create_with_config_interface()
    {
        $config = [
            TestInterface::class => new TestService()
        ];

        $container = new Container($config);

        $this->assertCount(1, $container->entries());
        $this->assertTrue($container->has(Container::class));
        $this->assertFalse($container->has(TestInterface::class));

        $container->get(TestInterface::class);

        $this->assertTrue($container->has(TestInterface::class));
    }

    public function test_create_with_config_interface_not_implements()
    {
        $this->expectException(NotATypeException::class);
        $this->expectExceptionMessage(TestInterface::class . ' can not be instantiated to create a new instance.');

        $TestService = new class () {};

        $config = [
            TestInterface::class => $TestService
        ];

        $container = new Container($config);

        $this->assertCount(1, $container->entries());
        $this->assertTrue($container->has(Container::class));
        $this->assertFalse($container->has(TestInterface::class));

        $container->get(TestInterface::class);
    }

    public function test_get_creates_entry()
    {
        $container = new Container();

        $service = $container->get(TestService::class);

        $this->assertNotNull($service);
        $this->assertInstanceOf(TestService::class, $service);

        $this->assertCount(2, $container->entries());

        $this->assertTrue($container->has(TestService::class));
    }

    public function test_get_does_not_create_entry_twice()
    {
        $container = new Container();

        $service = $container->get(TestService::class);
        $service->name = 'TestServiceNew';

        $service2 = $container->get(TestService::class);

        $this->assertSame($service, $service2);

        $this->assertSame('TestServiceNew', $service2->name);

        $this->assertCount(2, $container->entries());

        $this->assertTrue($container->has(TestService::class));
    }

    public function test_get_interface_cannot_be_created()
    {
        $this->expectException(NotATypeException::class);
        $this->expectExceptionMessage(TestInterface::class . ' can not be instantiated to create a new instance.');

        $container = new Container();

        $this->assertCount(1, $container->entries());
        $this->assertTrue($container->has(Container::class));
        $this->assertFalse($container->has(TestInterface::class));

        $container->get(TestInterface::class);
    }

    public function test_get_with_invalid_type()
    {
        $this->expectException(NotATypeOrCallbackException::class);
        $this->expectExceptionMessage('Argument is not a known class or interface: InvalidType');

        $container = new Container();

        $container->get('InvalidType');
    }

    public function test_create_creates_instance()
    {
        $container = new Container();

        $service = $container->create(TestService::class);

        $this->assertNotNull($service);
        $this->assertInstanceOf(TestService::class, $service);

        $this->assertCount(1, $container->entries());

        $this->assertFalse($container->has(TestService::class));
    }

    public function test_create_creates_instances()
    {
        $container = new Container();

        $service = $container->create(TestService::class);
        $service2 = $container->create(TestService::class);
        $service3 = $container->create(TestService::class);

        $this->assertInstanceOf(TestService::class, $service);
        $this->assertInstanceOf(TestService::class, $service2);
        $this->assertInstanceOf(TestService::class, $service3);

        $this->assertNotSame($service, $service2);
        $this->assertNotSame($service, $service3);
        $this->assertNotSame($service2, $service3);

        $this->assertCount(1, $container->entries());

        $this->assertFalse($container->has(TestService::class));
    }

    public function test_create_interface()
    {
        $this->expectException(NotATypeException::class);
        $this->expectExceptionMessage(TestInterface::class . ' can not be instantiated to create a new instance.');

        $container = new Container();
        $container->create(TestInterface::class);
    }

    public function test_create_interface2()
    {
        $this->expectException(NotATypeException::class);
        $this->expectExceptionMessage(TestInterface::class . ' can not be instantiated to create a new instance.');

        $container = new Container([
            TestInterface::class => new TestService()
        ]);

        $container->create(TestInterface::class);
    }

    public function test_create_with_callback_closure()
    {
        $container = new Container();

        $service = $container->create(function (TestService $service) {
            // do something hiere
        });

        $this->assertInstanceOf(TestService::class, $service);

        $service2 = $container->get(TestService::class);

        $this->assertInstanceOf(TestService::class, $service);

        $this->assertNotSame($service, $service2);
    }

    public function test_create_with_callback_closure_multiple_arguments()
    {
        $this->expectException(TooManyCallbackArgumentsException::class);
        $this->expectExceptionMessage('Callback may only provide 1 argument.');

        $container = new Container();

        $container->create(function (TestService $service, TestModel $model) {
            // do something hiere
        });
    }

    public function test_create_with_resolver()
    {
        $container = new Container();

        $ServiceClass = TestService::class;

        $called = false;
        $s = null;

        $service = $container->create($ServiceClass, function (TestService $service) use (&$called, &$s) {
            $called = true;
            $s = $service;
        });

        $this->assertTrue($called);
        $this->assertSame($service, $s);
    }

    public function test_create_with_callback_and_resolver()
    {
        $container = new Container();

        $called = false;
        $s = null;
        $called2 = false;
        $s2 = null;

        $service = $container->create(
            function (TestService $service) use (&$called, &$s) {
                $called = true;
                $service->name .= '-1';
                $s = $service;
            },
            function (TestService $service) use (&$called2, &$s2) {
                $called2 = true;
                $service->name .= '-2';
                $s2 = $service;
            }
        );

        $this->assertSame('TestService-1-2', $service->name);

        $this->assertTrue($called);
        $this->assertSame($service, $s);

        $this->assertTrue($called2);
        $this->assertSame($service, $s2);
    }

    public function test_call_closure()
    {
        $container = new Container();

        $callback = function (TestService $service) {
            $service->name = 'test';
            return $service;
        };

        $service = $container->call($callback);

        $this->assertSame('test', $service->name);

        $this->assertCount(2, $container->entries());

        // no new entry:

        $service = $container->call($callback);

        $this->assertSame('test', $service->name);

        $this->assertCount(2, $container->entries());
    }

    public function test_call_closure_interface()
    {
        $container = new Container([
            TestInterface::class => new TestService()
        ]);

        $callback = function (TestInterface $service) {
            $service->name = 'test';
            return $service;
        };

        $service = $container->call($callback);

        $this->assertSame('test', $service->name);

        $this->assertCount(2, $container->entries());

        // no new entry:

        $service = $container->call($callback);

        $this->assertSame('test', $service->name);

        $this->assertCount(2, $container->entries());
    }

    public function callbackCallablePublic2(TestService $service)
    {
        $service->name = 'test2';
        return $service;
    }

    public function test_call_callable()
    {
        $container = new Container();

        $service = $container->call([$this, 'callbackCallablePublic2']);

        $this->assertSame('test2', $service->name);

        $this->assertCount(2, $container->entries());
    }

    public function test_call_closure_multiple_arguments()
    {
        $container = new Container();

        $service = $container->call(function (TestService $service) {
            $service->name = 'hoho';
            return $service;
        });

        $this->assertSame('hoho', $service->name);

        $this->assertCount(2, $container->entries());

        [$service2, $model] = $container->call(function (TestModel $model, TestService $service) {
            $model->name = 'model123';
            $service->name = 'service123';

            return [$service, $model];
        });

        $this->assertSame('model123', $model->name);
        $this->assertSame('service123', $service2->name);

        $this->assertCount(3, $container->entries());

        $this->assertSame($service, $service2);
        $this->assertSame($service, array_values($container->entries())[1]);
        $this->assertSame($model, array_values($container->entries())[2]);
    }

    public function test_invoke_resolver_callback_single_argument()
    {
        $container = new Container();

        $result = invokeResolverCallback(function (TestService $service) {
            $service->name = 'resolved';
        }, $container);

        $this->assertInstanceOf(TestService::class, $result);
        $this->assertSame('resolved', $result->name);

        // create() should not register in container
        $this->assertFalse($container->has(TestService::class));
    }

    public function test_invoke_resolver_callback_multiple_arguments()
    {
        $container = new Container();

        // Pre-register a service as singleton
        $existingService = $container->get(TestService::class);
        $existingService->name = 'singleton';

        $injectedService = null;

        $result = invokeResolverCallback(function (TestModel $model, TestService $service) use (&$injectedService) {
            $model->name = 'new_model';
            $injectedService = $service;
        }, $container);

        $this->assertInstanceOf(TestModel::class, $result);
        $this->assertSame('new_model', $result->name);
        // First arg is created (not registered in container)
        $this->assertFalse($container->has(TestModel::class));
        // Second arg is the singleton from the container
        $this->assertSame($existingService, $injectedService);
        $this->assertSame('singleton', $injectedService->name);
    }

    public function test_invoke_resolver_callback_before_invoke()
    {
        $container = new Container();

        $order = [];

        $result = invokeResolverCallback(
            function (TestService $service) use (&$order) {
                $order[] = 'callback:' . $service->name;
            },
            $container,
            function (TestService $service) use (&$order) {
                $service->name = 'configured';
                $order[] = 'beforeInvoke';
            }
        );

        $this->assertInstanceOf(TestService::class, $result);
        $this->assertSame('configured', $result->name);

        // beforeInvoke runs before callback
        $this->assertSame(['beforeInvoke', 'callback:configured'], $order);
    }

    public function test_invoke_resolver_callback_first_arg_is_fresh_instance()
    {
        $container = new Container();

        // Pre-register a singleton
        $singleton = $container->get(TestService::class);
        $singleton->name = 'singleton';

        $result = invokeResolverCallback(function (TestService $service) {
            // service should be a fresh instance, not the singleton
        }, $container);

        $this->assertNotSame($singleton, $result);
        $this->assertSame('TestService', $result->name);
        $this->assertSame('singleton', $singleton->name);
    }

    public function test_invoke_resolver_callback_no_arguments()
    {
        $container = new Container();

        $result = invokeResolverCallback(function () {
            // no arguments
        }, $container);

        $this->assertNull($result);
    }

}
