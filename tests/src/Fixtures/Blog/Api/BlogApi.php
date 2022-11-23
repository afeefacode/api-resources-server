<?php

namespace Afeefa\ApiResources\Test\Fixtures\Blog\Api;

use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Resource\ResourceBag;
use Afeefa\ApiResources\Test\Fixtures\Blog\Resources\AppResource;
use Afeefa\ApiResources\Test\Fixtures\Blog\Resources\ArticleResource;
use Afeefa\ApiResources\Test\Fixtures\Blog\Resources\AuthorResource;
use Afeefa\ApiResources\Test\Fixtures\Blog\Resources\CommentResource;
use Afeefa\ApiResources\Test\Fixtures\Blog\Resources\TagResource;

class BlogApi extends Api
{
    protected static string $type = 'Blog.BlogApi';

    protected function resources(ResourceBag $resources): void
    {
        $resources
            ->add(AppResource::class)
            ->add(ArticleResource::class)
            ->add(TagResource::class)
            ->add(AuthorResource::class)
            ->add(CommentResource::class);
    }
}
