<?php

namespace Afeefa\ApiResources\Test\Fixtures\Blog\Resources;

use Afeefa\ApiResources\Eloquent\ModelResource;
use Afeefa\ApiResources\Test\Fixtures\Blog\Types\ArticleType;

class ArticleResource extends ModelResource
{
    protected static string $type = 'Blog.ArticleResource';

    public string $ModelTypeClass = ArticleType::class;
}
