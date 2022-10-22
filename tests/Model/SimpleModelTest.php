<?php

namespace Afeefa\ApiResources\Tests\Model;

use Afeefa\ApiResources\Model\SimpleModel;
use PHPUnit\Framework\TestCase;

class SimpleModelTest extends TestCase
{
    public function test_model()
    {
        $model = new SimpleModel();

        $json = $model->jsonSerialize();

        $this->assertEquals([], $json);
    }
}
