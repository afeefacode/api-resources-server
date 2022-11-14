<?php

namespace Afeefa\ApiResources\Test\Fixtures\Blog\Resources;

use Afeefa\ApiResources\Eloquent\ModelResource;
use Afeefa\ApiResources\Test\Fixtures\Blog\Types\AuthorType;

class AuthorResource extends ModelResource
{
    protected static string $type = 'Blog.AuthorResource';

    public string $ModelTypeClass = AuthorType::class;
}
