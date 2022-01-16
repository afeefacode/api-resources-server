<?php

namespace Afeefa\ApiResources\Tests\DI;

use Afeefa\ApiResources\DI\Container;

use function Afeefa\ApiResources\DI\create;
use Afeefa\ApiResources\DI\DependencyResolver;
use function Afeefa\ApiResources\DI\factory;
use Afeefa\ApiResources\Exception\Exceptions\MissingCallbackArgumentException;
use Afeefa\ApiResources\Exception\Exceptions\MissingTypeHintException;
use Afeefa\ApiResources\Exception\Exceptions\NotATypeOrCallbackException;
use Afeefa\ApiResources\Exception\Exceptions\TooManyCallbackArgumentsException;
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

    public function test_create_with_config()
    {
        $service3 = new TestService3();

        $config = [
            TestService::class => factory(function () {
                $service = new TestService();
                $service->name = 'My new Service';
                return $service;
            }),

            TestService2::class => factory(function () {
                $service = new TestService();
                $service->name = 'My new Service2';
                return $service;
            }),

            TestService3::class => $service3
        ];

        $container = new Container($config);

        $this->assertTrue($container->has(Container::class));
        $this->assertFalse($container->has(TestService::class));
        $this->assertFalse($container->has(TestService2::class));
        $this->assertFalse($container->has(TestService3::class));

        $service = $container->create(TestService::class);

        $this->assertEquals('My new Service', $service->name);

        $serviceAgain = $container->create(TestService::class);

        $this->assertNotSame($service, $serviceAgain);
        $this->assertEquals('My new Service', $serviceAgain->name);

        $service2 = $container->get(TestService2::class);

        $this->assertEquals('My new Service2', $service2->name);

        $service2Again = $container->get(TestService2::class);

        $this->assertEquals('My new Service2', $service2Again->name);

        $service3Again = $container->get(TestService3::class);

        $this->assertSame($service3, $service3Again);
        $this->assertEquals('TestService3', $service3Again->name);
    }

    public function test_create_with_config2()
    {
        $config = [
            TestService::class => create(),
            TestService2::class => create()->call('init')
        ];

        $container = new Container($config);

        $this->assertCount(1, $container->entries());
        $this->assertTrue($container->has(Container::class));
        $this->assertFalse($container->has(TestService::class));
        $this->assertFalse($container->has(TestService2::class));

        $service = $container->get(TestService::class);
        $service2 = $container->get(TestService::class);

        $this->assertNotSame($service, $service2);

        $service3 = $container->get(TestService2::class);
        $service4 = $container->get(TestService2::class);

        $this->assertNotSame($service3, $service4);

        $this->assertSame('Another Name', $service3->name);
        $service5 = $service3->testService;
        $this->assertSame('TestService', $service5->name);

        $this->assertSame('Another Name', $service4->name);
        $service6 = $service4->testService;
        $this->assertSame('TestService', $service6->name);

        $this->assertNotSame($service5, $service6);

        $this->assertCount(1, $container->entries());
        $this->assertTrue($container->has(Container::class));
        $this->assertFalse($container->has(TestService::class));
        $this->assertFalse($container->has(TestService2::class));
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

    public function test_get_with_callback_closure()
    {
        $container = new Container();

        $service = $container->get(function (TestService $service) {
            // do something hiere
        });

        $this->assertNotNull($service);
        $this->assertInstanceOf(TestService::class, $service);

        $this->assertCount(2, $container->entries());

        $this->assertTrue($container->has(TestService::class));

        // callback in variable

        $callback = function (TestService $service) {
            // do something hiere
        };

        $service = $container->get($callback);

        $this->assertNotNull($service);
        $this->assertInstanceOf(TestService::class, $service);

        $this->assertCount(2, $container->entries());

        $this->assertTrue($container->has(TestService::class));

        // second argument in closure

        $service2 = $container->get(function (TestService $service, TestModel $model) {
            // do something hiere
        });

        $this->assertSame($service, $service2);

        $this->assertNotNull($service);
        $this->assertInstanceOf(TestService::class, $service);

        $this->assertCount(3, $container->entries());

        $this->assertTrue($container->has(TestService::class));
        $this->assertTrue($container->has(TestModel::class));
    }

    public function callbackCallablePublic(TestService $service)
    {
        // do something here
    }

    public function test_get_with_callback_callable()
    {
        $container = new Container();

        $service = $container->get([$this, 'callbackCallablePublic']);

        $this->assertNotNull($service);
        $this->assertInstanceOf(TestService::class, $service);

        $this->assertCount(2, $container->entries());

        $this->assertTrue($container->has(TestService::class));
    }

    protected function callbackCallableProtected(TestService $service)
    {
        // do something here
    }

    public function test_get_with_callback_callable_not_accessible()
    {
        $this->expectException(NotATypeOrCallbackException::class);
        $this->expectExceptionMessage('Argument is not a class string: array');

        $container = new Container();

        $container->get([$this, 'callbackCallableProtected']);
    }

    public function test_get_with_callback_callable_invalid()
    {
        $this->expectException(NotATypeOrCallbackException::class);
        $this->expectExceptionMessage('Argument is not a class string: array');

        $container = new Container();

        $container->get([$this, 'invalidMethodName']);
    }

    public function test_get_with_invalid_type()
    {
        $this->expectException(NotATypeOrCallbackException::class);
        $this->expectExceptionMessage('Argument is not a known class: InvalidType');

        $container = new Container();

        $container->get('InvalidType');
    }

    public function test_get_with_callback_closure_missing_argument()
    {
        $this->expectException(MissingCallbackArgumentException::class);
        $this->expectExceptionMessage('Callback must provide more than 0 arguments.');

        $container = new Container();

        $container->get(function () {
            // do something hiere
        });
    }

    public function test_get_with_callback_closure_missing_type_hint()
    {
        $this->expectException(MissingTypeHintException::class);
        $this->expectExceptionMessage('Callback variable $hoho does provide a type hint.');

        $container = new Container();

        $container->get(function ($hoho) {
            // do something hiere
        });
    }

    public function test_get_with_callback_closure_multiple_arguments()
    {
        $container = new Container();

        $service = $container->get(function (TestService $service, TestModel $model) {
            // do something hiere
        });

        $this->assertInstanceOf(TestService::class, $service);

        $this->assertCount(3, $container->entries());

        $this->assertTrue($container->has(TestService::class));
        $this->assertTrue($container->has(TestModel::class));

        $this->assertSame($service, array_values($container->entries())[1]);
        $this->assertInstanceOf(TestModel::class, array_values($container->entries())[2]);

        // again

        $model = $container->get(function (TestModel $model, TestService $service) {
            // do something hiere
        });

        $this->assertInstanceOf(TestModel::class, $model);

        $this->assertCount(3, $container->entries());

        $this->assertTrue($container->has(TestService::class));
        $this->assertTrue($container->has(TestModel::class));

        $this->assertInstanceOf(TestService::class, array_values($container->entries())[1]);
        $this->assertSame($model, array_values($container->entries())[2]);
    }

    public function test_get_with_callback_closure_multiple_arguments2()
    {
        $container = new Container();

        $called = false;
        $s = null;
        $m = null;

        $service = $container->get(function (TestService $service, TestModel $model) use (&$called, &$s, &$m) {
            $s = $service;
            $m = $model;
            $called = true;
        });

        $this->assertTrue($called);

        $this->assertCount(3, $container->entries());
        $this->assertSame($service, $s);
        $this->assertSame(array_values($container->entries())[2], $m);

        $called = false;
        $s2 = null;
        $m2 = null;

        $service = $container->get(function (TestService $service, TestModel $model) use (&$called, &$s2, &$m2) {
            $s2 = $service;
            $m2 = $model;
            $called = true;
        });

        $this->assertTrue($called);

        $this->assertCount(3, $container->entries());
        $this->assertSame($s, $s2);
        $this->assertSame($m, $m2);
        $this->assertSame(array_values($container->entries())[2], $m);
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

    public function test_create_with_callback_closure()
    {
        $container = new Container();

        $service = $container->create(function (TestService $service) {
            // do something hiere
        });

        $this->assertInstanceOf(TestService::class, $service);

        // callback in variable

        $callback = function (TestService $service) {
            // do something hiere
        };

        $service2 = $container->get($callback);

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

    public function test_call_closure_with_callback()
    {
        $container = new Container();

        $called = false;
        $s = null;
        $called2 = false;
        $s2 = null;

        $callback = function (TestModel $model, TestService $service) use (&$called, &$s) {
            $called = true;
            $model->name .= '-1';
            $service->name .= '-1';
            $s = $service;
            return [$service, $model];
        };

        [$service, $model] = $container->call(
            $callback,
            function (TestModel $model, TestService $service) use (&$called2, &$s, &$s2) {
                $called2 = true;
                $model->name .= '-2';
                $service->name .= '-2';
                $s2 = $service;

                $this->assertSame($s, $service);
                $this->assertSame('TestService-1-2', $service->name);
            }
        );

        $this->assertTrue($called);
        $this->assertTrue($called2);

        $this->assertSame('TestService-1-2', $service->name);
        $this->assertSame('TestModel-1-2', $model->name);

        $this->assertCount(3, $container->entries());
        $this->assertSame($model, array_values($container->entries())[1]);
        $this->assertSame($service, array_values($container->entries())[2]);
    }

    public function test_call_closure_with_two_callbacks()
    {
        $container = new Container();

        $called = false;
        $s = null;
        $called2 = false;
        $s2 = null;

        $callback = function (TestModel $model, TestService $service) use (&$called, &$s) {
            $called = true;
            $model->name .= '-1';
            $service->name .= '-1';
            $s = $service;
            return [$service, $model];
        };

        [$service, $model] = $container->call(
            $callback,
            function (TestModel $model, TestService $service) use (&$called2, &$s, &$s2) {
                $called2 = true;
                $model->name .= '-2';
                $service->name .= '-2';
                $s2 = $service;

                $this->assertSame($s, $service);
                $this->assertSame('TestService-1-2', $service->name);
            },
            function (TestModel $model, TestService $service) use (&$called2, &$s, &$s2) {
                $called2 = true;
                $model->name .= '-3';
                $service->name .= '-3';
                $s2 = $service;

                $this->assertSame($s, $service);
                $this->assertSame($s2, $service);
                $this->assertSame('TestService-1-2-3', $service->name);
            }
        );

        $this->assertTrue($called);
        $this->assertTrue($called2);

        $this->assertSame('TestService-1-2-3', $service->name);
        $this->assertSame('TestModel-1-2-3', $model->name);

        $this->assertCount(3, $container->entries());
        $this->assertSame($model, array_values($container->entries())[1]);
        $this->assertSame($service, array_values($container->entries())[2]);
    }

    public function test_call_closure_with_resolver()
    {
        $container = new Container();

        $resolverTypes = [];
        $resolverIndexes = [];

        $container->call(
            function (TestModel $model, TestService2 $service) {
            },
            function (DependencyResolver $r) use (&$resolverTypes, &$resolverIndexes) {
                $resolverTypes[] = $r->getTypeClass();
                $resolverIndexes[] = $r->getIndex();

                if ($r->getIndex() === 0) {
                    $this->assertTrue($r->isOf(TestModel::class));
                }

                if ($r->getIndex() === 1) {
                    $this->assertTrue($r->isOf(TestService2::class));
                    $this->assertTrue($r->isOf(TestService::class));
                }
            }
        );

        $this->assertCount(3, $container->entries());
        $this->assertEquals([TestModel::class, TestService2::class], $resolverTypes);
        $this->assertEquals([0, 1], $resolverIndexes);
    }

    public function test_call_closure_with_resolver_create()
    {
        $container = new Container();

        $m = null;
        $s = null;

        [$model, $service] = $container->call(
            function (TestModel $model, TestService $service) use (&$m, &$s) {
                $model->name .= '-1';
                $service->name .= '-1';
                $m = $model;
                $s = $service;
                return [$model, $service];
            },
            function (DependencyResolver $r) {
                if ($r->isOf(TestModel::class)) {
                    $r->create();
                }
            },
            function (TestModel $model, TestService $service) use (&$m, &$s) {
                $model->name .= '-2';
                $service->name .= '-2';
                $this->assertSame($m, $model);
                $this->assertSame($s, $service);
            },
        );

        $this->assertCount(2, $container->entries());
        $this->assertSame('TestService-1-2', $service->name);
        $this->assertSame('TestModel-1-2', $model->name);

        [$model2, $service2] = $container->call(
            function (TestModel $model, TestService $service) use (&$m, &$s) {
                return [$model, $service];
            },
            function (DependencyResolver $r) {
                if ($r->isOf(TestModel::class)) {
                    $r->create();
                }
            }
        );

        $this->assertCount(2, $container->entries());
        $this->assertSame('TestService-1-2', $service2->name);
        $this->assertSame('TestModel', $model2->name);
    }

    public function test_call_closure_with_resolver_fix()
    {
        $container = new Container();

        $mFix = new TestModel();
        $mFix->name .= '-fix';

        $m = null;
        $s = null;

        [$model, $service] = $container->call(
            function (TestModel $model, TestService $service) use (&$m, &$s) {
                $model->name .= '-1';
                $service->name .= '-1';
                $m = $model;
                $s = $service;
                return [$model, $service];
            },
            function (DependencyResolver $r) use ($mFix) {
                if ($r->isOf(TestModel::class)) {
                    $r->fix($mFix);
                }
            },
            function (TestModel $model, TestService $service) use (&$m, &$s) {
                $model->name .= '-2';
                $service->name .= '-2';
                $this->assertSame($m, $model);
                $this->assertSame($s, $service);
            },
        );

        $this->assertCount(2, $container->entries());
        $this->assertSame('TestService-1-2', $service->name);
        $this->assertSame('TestModel-fix-1-2', $model->name);

        [$model2, $service2] = $container->call(
            function (TestModel $model, TestService $service) use (&$m, &$s) {
                return [$model, $service];
            },
            function (DependencyResolver $r) use ($mFix) {
                if ($r->isOf(TestModel::class)) {
                    $r->fix($mFix);
                }
            }
        );

        $this->assertCount(2, $container->entries());
        $this->assertSame('TestService-1-2', $service2->name);
        $this->assertSame('TestModel-fix-1-2', $model2->name);

        [$model3, $service3] = $container->call(
            function (TestModel $model, TestService $service) use (&$m, &$s) {
                return [$model, $service];
            },
            function (DependencyResolver $r) use ($mFix) {
            }
        );

        $this->assertCount(3, $container->entries());
        $this->assertSame('TestService-1-2', $service3->name);
        $this->assertSame('TestModel', $model3->name);
    }
}
