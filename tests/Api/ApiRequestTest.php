<?php

namespace Afeefa\ApiResources\Tests\Api;

use Afeefa\ApiResources\Api\ApiRequest;
use Afeefa\ApiResources\DI\Container;
use Afeefa\ApiResources\Model\Model;
use Afeefa\ApiResources\Tests\Fixtures\TestApi\TestApi;
use Afeefa\ApiResources\Tests\Fixtures\TestApi\TestResource;
use PHPUnit\Framework\TestCase;

class ApiRequestTest extends TestCase
{
    public function test_request()
    {
        $models = $this->request(5, [
            'attr1' => true
        ]);

        $this->assertCount(5, $models);

        $this->assertFields($models, ['attr1']);
    }

    public function test_request_multiple_attributes()
    {
        $models = $this->request(5, [
            'attr1' => true,
            'attr2' => true,
            'attr3' => true
        ]);

        $this->assertCount(5, $models);

        $this->assertFields($models, ['attr1', 'attr2', 'attr3']);
    }

    public function test_request_wrong_attributes()
    {
        $models = $this->request(5, [
            'attr4' => true
        ]);

        $this->assertCount(5, $models);

        $this->assertFields($models);

        $models = $this->request(5, [
            'attr1' => true,
            'attr4' => true
        ]);

        $this->assertCount(5, $models);

        $this->assertFields($models, ['attr1']);
    }

    public function test_request_no_attributes()
    {
        $models = $this->request(5);

        $this->assertCount(5, $models);

        $this->assertFields($models);
    }

    public function test_request_filter()
    {
        $models = $this->request(15, [
            'attr1' => true
        ]);

        $this->assertCount(15, $models);

        $this->assertFields($models, ['attr1']);
    }

    public function test_request_no_filter()
    {
        $models = $this->request(null, [
            'attr1' => true
        ]);

        $this->assertCount(5, $models);

        $this->assertFields($models, ['attr1']);
    }

    public function test_request_no_filter_no_fields()
    {
        $models = $this->request();

        $this->assertCount(5, $models);

        $this->assertFields($models);
    }

    private function request(?int $count = null, array $fields = null)
    {
        $container = new Container();
        $api = $container->create(TestApi::class);

        $result = $api->request(function (ApiRequest $request) use ($count, $fields) {
            $request = $request
                ->resourceName(TestResource::$type)
                ->actionName('get_types');

            if ($count !== null) {
                $request->filters([
                    'page_size' => $count
                ]);
            }

            if ($fields) {
                $request->fields($fields);
            }

            return $request;
        });

        return $this->toJson($result['data']);
    }

    private function assertFields(array $models, array $fieldNames = [])
    {
        foreach ($models as $index => $model) {
            $this->assertCount(count($fieldNames) + 2, array_keys($model));
            $this->assertEquals($index + 1, $model['id']);
            $this->assertEquals('TestType', $model['type']);
            foreach ($fieldNames as $fieldName) {
                $this->assertTrue($model[$fieldName]);
            }
        }
    }

    private function toJson(array $data)
    {
        return array_map(function (Model $model) {
            return $model->jsonSerialize();
        }, $data);
    }
}
