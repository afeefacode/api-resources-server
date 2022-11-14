<?php

namespace Afeefa\ApiResources\Tests\Eloquent;

use Afeefa\ApiResources\ApiResources;
use Afeefa\ApiResources\Test\Eloquent\ApiResourcesEloquentTest;
use Afeefa\ApiResources\Test\Fixtures\Blog\Api\BlogApi;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Article;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Author;

class ModelResourceTest extends ApiResourcesEloquentTest
{
    public function test_model_resource()
    {
        Author::factory()
            ->count(2)
            ->has(Article::factory()->count(3))
            ->create();

        $result = (new ApiResources())->requestFromInput(BlogApi::class, [
            'resource' => 'Blog.AuthorResource',
            'action' => 'list',
            'fields' => [
                'name' => true,
                'count_articles' => true
            ]
        ]);

        ['data' => $data] = $result;

        $this->assertCount(2, $data);
        $this->assertEquals(3, $data[0]['count_articles']);
        $this->assertEquals(3, $data[1]['count_articles']);
    }

    public function test_create_model()
    {
        Author::factory()
            ->count(2)
            ->has(Article::factory()->count(3))
            ->create();

        $result = (new ApiResources())->requestFromInput(BlogApi::class, [
            'resource' => 'Blog.AuthorResource',
            'action' => 'save',
            'data' => [
                'name' => 'King Writer',
                'email' => 'king@writer'
            ],
            'fields' => [
                'name' => true,
                'count_articles' => true
            ]
        ]);

        ['data' => $data] = $result;

        $this->assertEquals('King Writer', $data['name']);
        $this->assertEquals(0, $data['count_articles']);

        $this->assertEquals(3, Author::count());
        $this->assertEquals(6, Article::count());
    }

    public function test_update_model()
    {
        $authors = Author::factory()
            ->count(2)
            ->has(Article::factory()->count(3))
            ->create();

        $result = (new ApiResources())->requestFromInput(BlogApi::class, [
            'resource' => 'Blog.AuthorResource',
            'action' => 'save',
            'params' => [
                'id' => $authors->first()->id
            ],
            'data' => [
                'name' => 'King Writer'
            ],
            'fields' => [
                'name' => true,
                'count_articles' => true
            ]
        ]);

        ['data' => $data] = $result;

        $this->assertEquals('King Writer', $data['name']);
        $this->assertEquals(3, $data['count_articles']);
    }
}
