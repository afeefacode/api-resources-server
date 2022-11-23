<?php

namespace Afeefa\ApiResources\Test\Fixtures\Blog\Resources;

use Afeefa\ApiResources\Eloquent\ModelResource;
use Afeefa\ApiResources\Test\Fixtures\Blog\Types\CommentType;

class CommentResource extends ModelResource
{
    protected static string $type = 'Blog.CommentResource';

    public string $ModelTypeClass = CommentType::class;
}
