<?php

namespace Afeefa\ApiResources\Tests\Eloquent;

use Afeefa\ApiResources\Test\Eloquent\ApiResourcesEloquentTest;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Article;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Author;

class ModelTest extends ApiResourcesEloquentTest
{
    public function test_model()
    {
        Author::factory()
            ->count(5)
            ->has(Article::factory()->count(5))
            ->create();

        $this->assertEquals(5, Author::count());
        $this->assertEquals(25, Article::count());
    }
}
