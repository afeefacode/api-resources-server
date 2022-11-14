<?php

namespace Afeefa\ApiResources\Test\Fixtures\Blog\Types;

use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\StringAttribute;
use Afeefa\ApiResources\Type\Type;

class TagType extends Type
{
    protected static string $type = 'Blog.Tag';

    protected function fields(FieldBag $fields): void
    {
        $fields
            ->attribute('name', StringAttribute::class)

            ->relation('users', Type::list([AuthorType::class, ArticleType::class]));
    }
}
