<?php

namespace Afeefa\ApiResources\Test\Fixtures\Blog\Types;

use Afeefa\ApiResources\Eloquent\ModelType;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\StringAttribute;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Tag;
use Afeefa\ApiResources\Type\Type;

class TagType extends ModelType
{
    protected static string $type = 'Blog.Tag';

    public static string $ModelClass = Tag::class;

    protected function fields(FieldBag $fields): void
    {
        $fields
            ->attribute('name', StringAttribute::class)

            ->relation('tag_users', Type::list(TagUserType::class));
    }
}
