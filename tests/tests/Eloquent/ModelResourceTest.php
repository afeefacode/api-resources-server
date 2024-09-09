<?php

namespace Afeefa\ApiResources\Tests\Eloquent;

use Afeefa\ApiResources\ApiResources;
use Afeefa\ApiResources\Test\Eloquent\ApiResourcesEloquentTest;
use Afeefa\ApiResources\Test\Fixtures\Blog\Api\BlogApi;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Article;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Author;
use Afeefa\ApiResources\Test\Fixtures\Blog\Resources\ArticleResource as ResourcesArticleResource;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use stdClass;

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

    public function test_meta_add()
    {
        $author = Author::factory()->create();

        $api = (new ApiResources())->getApi(BlogApi::class);
        $api->getResources()->add(ArticleResource::class);

        $date = Carbon::now()->setMilliSeconds(0);

        $result = $api->requestFromInput([
            'resource' => 'Blog.ArticleResource',
            'action' => 'save',
            'data' => [
                'title' => 'Great Title',
                'date' => $date,
                'author' => [
                    'id' => $author->id
                ]
            ],
            'fields' => [
                'title' => true,
                'date' => true,
                'content' => true
            ]
        ]);

        ['data' => $data] = $result;

        $this->assertEquals('Great Title', $data['title']);
        $this->assertEquals($date, $data['date']);

        $expectedLog = [
            'beforeResolve',
            'beforeAdd',
            'afterAdd',
            'afterResolve',
        ];

        $this->assertEquals($expectedLog, ArticleResource::$log);
    }

    public function test_meta_update()
    {
        $article = Article::factory()
            ->for(Author::factory())
            ->create();

        $api = (new ApiResources())->getApi(BlogApi::class);
        $api->getResources()->add(ArticleResource::class);

        $result = $api->requestFromInput([
            'resource' => 'Blog.ArticleResource',
            'action' => 'save',
            'params' => [
                'id' => $article->id
            ],
            'data' => [
                'title' => 'Great Title'
            ],
            'fields' => [
                'title' => true,
                'content' => true
            ]
        ]);

        ['data' => $data] = $result;

        $this->assertEquals('Great Title', $data['title']);

        $expectedLog = [
            'beforeResolve',
            'beforeUpdate',
            'afterUpdate',
            'afterResolve',
        ];

        $this->assertEquals($expectedLog, ArticleResource::$log);
    }

    public function test_meta_delete()
    {
        $article = Article::factory()
            ->for(Author::factory())
            ->create();

        $api = (new ApiResources())->getApi(BlogApi::class);
        $api->getResources()->add(ArticleResource::class);

        $result = $api->requestFromInput([
            'resource' => 'Blog.ArticleResource',
            'action' => 'save',
            'params' => [
                'id' => $article->id
            ],
            'data' => null
        ]);

        ['data' => $data] = $result;

        $this->assertNull($data);

        $expectedLog = [
            'beforeResolve',
            'beforeDelete',
            'afterDelete',
            'afterResolve',
        ];

        $this->assertEquals($expectedLog, ArticleResource::$log);
    }
}

class ArticleResource extends ResourcesArticleResource
{
    public static array $log;

    protected function beforeResolve(array $params, ?array $data, stdClass $meta): array
    {
        $meta->log = ['beforeResolve'];
        return [$params, $data];
    }

    protected function afterResolve(?Model $model, stdClass $meta): void
    {
        $meta->log[] = 'afterResolve';

        static::$log = $meta->log;
    }

    protected function beforeAdd(Model $model, array $saveFields, stdClass $meta): array
    {
        $meta->log[] = 'beforeAdd';
        return $saveFields;
    }

    protected function afterAdd(Model $model, array $saveFields, stdClass $meta): void
    {
        $meta->log[] = 'afterAdd';
    }

    protected function beforeUpdate(Model $model, array $saveFields, stdClass $meta): array
    {
        $meta->log[] = 'beforeUpdate';
        return $saveFields;
    }

    protected function afterUpdate(Model $model, array $saveFields, stdClass $meta): void
    {
        $meta->log[] = 'afterUpdate';
    }

    protected function beforeDelete(Model $model, stdClass $meta): void
    {
        $meta->log[] = 'beforeDelete';
    }

    protected function afterDelete(Model $model, stdClass $meta): void
    {
        $meta->log[] = 'afterDelete';
    }
}
