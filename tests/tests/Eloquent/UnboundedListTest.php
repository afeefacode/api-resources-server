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

    public function test_unbounded_page_size_bypasses_the_whitelist()
    {
        Author::factory()->count(60)->create();

        $api = (new ApiResources())->getApi(BlogApi::class);

        // 25 is not in the page size whitelist [15, 30, 50]; unbounded() lets the
        // server use it anyway, still paginating (page 1 → first 25 rows).
        $result = $api->newRequest(fn (ApiRequest $request) => $request
            ->resourceType('Blog.AuthorResource')
            ->actionName('list')
            ->fields(['name' => true])
            ->unbounded(25));

        ['data' => $data, 'meta' => $meta] = $result;

        $this->assertCount(25, $data);
        $this->assertEquals(25, $meta['used_filters']['page_size']);
    }

    public function test_unbounded_limit_caps_the_total_rows()
    {
        Author::factory()->count(60)->create();

        $api = (new ApiResources())->getApi(BlogApi::class);

        // Page through in steps of 25 with a hard total cap of 40 rows.
        $page1 = $api->newRequest(fn (ApiRequest $request) => $request
            ->resourceType('Blog.AuthorResource')
            ->actionName('list')
            ->filters(['page' => 1])
            ->fields(['name' => true])
            ->unbounded(25, 40));

        $page2 = $api->newRequest(fn (ApiRequest $request) => $request
            ->resourceType('Blog.AuthorResource')
            ->actionName('list')
            ->filters(['page' => 2])
            ->fields(['name' => true])
            ->unbounded(25, 40));

        // Page 1: rows 0–24 (full 25). Page 2: rows 25–39 only (15), capped at 40.
        $this->assertCount(25, $page1['data']);
        $this->assertCount(15, $page2['data']);

        // The cap must NOT distort the counts — count_search stays the true total.
        $this->assertEquals(60, $page1['meta']['count_search']);
        $this->assertEquals(60, $page1['meta']['count_all']);
    }

    /**
     * A page size below 1 (e.g. a legacy bool call like unbounded(false) coerced
     * to 0 without strict_types) must not divide by zero — it is clamped to 1.
     */
    public function test_unbounded_page_size_below_one_is_clamped()
    {
        Author::factory()->count(5)->create();

        $api = (new ApiResources())->getApi(BlogApi::class);

        $result = $api->newRequest(fn (ApiRequest $request) => $request
            ->resourceType('Blog.AuthorResource')
            ->actionName('list')
            ->fields(['name' => true])
            ->unbounded(0));

        // Clamped to page size 1 → first page returns exactly 1 row, no error.
        $this->assertCount(1, $result['data']);
        $this->assertEquals(5, $result['meta']['count_search']);
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
