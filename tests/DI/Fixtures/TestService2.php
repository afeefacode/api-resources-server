<?php

namespace Afeefa\ApiResources\Tests\DI\Fixtures;

class TestService2 extends TestService
{
    public string $name = 'TestService2';

    public ?TestService $testService = null;

    public function init(TestService $testService)
    {
        $this->name = 'Another Name';
        $this->testService = $testService;
    }
}
