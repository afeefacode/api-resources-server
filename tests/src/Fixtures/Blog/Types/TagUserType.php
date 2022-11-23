<?php

namespace Afeefa\ApiResources\Test\Fixtures\Blog\Types;

use Afeefa\ApiResources\Eloquent\ModelType;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\TagUser;

class TagUserType extends ModelType
{
    protected static string $type = 'Blog.TagUser';

    public static string $ModelClass = TagUser::class;

    protected function fields(FieldBag $fields): void
    {
        $fields
            ->relation('user', [AuthorType::class, ArticleType::class]);
    }
}
