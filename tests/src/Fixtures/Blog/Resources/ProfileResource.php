<?php

namespace Afeefa\ApiResources\Test\Fixtures\Blog\Resources;

use Afeefa\ApiResources\Eloquent\ModelResource;
use Afeefa\ApiResources\Test\Fixtures\Blog\Types\ProfileType;

class ProfileResource extends ModelResource
{
    protected static string $type = 'Blog.ProfileResource';

    public string $ModelTypeClass = ProfileType::class;
}
