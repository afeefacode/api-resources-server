<?php

namespace Afeefa\ApiResources\Test\Fixtures\Blog\Types;

use Afeefa\ApiResources\Eloquent\ModelType;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Tag;

class TagType extends ModelType
{
    protected static string $type = 'Blog.Tag';

    public static string $ModelClass = Tag::class;

    protected function fields(FieldBag $fields): void
    {
        $fields
            ->string('name')

            ->hasMany('tag_users', TagUserType::class);
    }
}
