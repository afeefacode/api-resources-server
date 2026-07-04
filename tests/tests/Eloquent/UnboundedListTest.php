<?php

namespace Afeefa\ApiResources\Tests\Eloquent;

use Afeefa\ApiResources\Api\ApiRequest;
use Afeefa\ApiResources\ApiResources;
use Afeefa\ApiResources\Test\Eloquent\ApiResourcesEloquentTest;
use Afeefa\ApiResources\Test\Fixtures\Blog\Api\BlogApi;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Author;

class UnboundedListTest extends ApiResourcesEloquentTest
{
    public function test_unbounded_returns_all_rows_without_limit()
    {
        // 60 rows is well beyond the largest allowed page size (50).
        Author::factory()->count(60)->create();

        $api = (new ApiResources())->getApi(BlogApi::class);

        $result = $api->newRequest(fn (ApiRequest $request) => $request
            ->resourceType('Blog.AuthorResource')
            ->actionName('list')
            ->fields(['name' => true])
            ->unbounded());

        ['data' => $data, 'meta' => $meta] = $result;

        $this->assertCount(60, $data);
        $this->assertEquals(1, $meta['used_filters']['page']);
        $this->assertEquals(60, $meta['used_filters']['page_size']);
    }

    /**
     * Security: the unbounded mode must be reachable only through the PHP API,
     * never from the request body. A client sending page_size=0 (as a filter or
     * a param) must NOT get the full result set — the page size whitelist falls
     * back to the default and the list stays paginated.
     */
    public function test_page_size_zero_in_body_does_not_unbound_the_list()
    {
        Author::factory()->count(60)->create();

        $result = (new ApiResources())->requestFromInput(BlogApi::class, [
            'resource' => 'Blog.AuthorResource',
            'action' => 'list',
            'params' => [
                'page_size' => 0
            ],
            'filters' => [
                'page_size' => 0
            ],
            'fields' => [
                'name' => true
            ]
        ]);

        ['data' => $data, 'meta' => $meta] = $result;

        // Whitelist fallback to the default page size (15), NOT all 60 rows.
        $this->assertCount(15, $data);
        $this->assertEquals(15, $meta['used_filters']['page_size']);
    }

    public function test_normal_list_stays_paginated()
    {
        Author::factory()->count(60)->create();

        $result = (new ApiResources())->requestFromInput(BlogApi::class, [
            'resource' => 'Blog.AuthorResource',
            'action' => 'list',
            'filters' => [
                'page_size' => 50
            ],
            'fields' => [
                'name' => true
            ]
        ]);

        ['data' => $data, 'meta' => $meta] = $result;

        $this->assertCount(50, $data);
        $this->assertEquals(50, $meta['used_filters']['page_size']);
    }
}
