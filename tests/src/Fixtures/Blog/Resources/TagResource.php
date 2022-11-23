<?php

namespace Afeefa\ApiResources\Test\Fixtures\Blog\Resources;

use Afeefa\ApiResources\Eloquent\ModelResource;
use Afeefa\ApiResources\Test\Fixtures\Blog\Types\TagType;

class TagResource extends ModelResource
{
    protected static string $type = 'Blog.TagResource';

    public string $ModelTypeClass = TagType::class;
}
