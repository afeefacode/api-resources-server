<?php

namespace Afeefa\ApiResources\Test\Fixtures\Blog\Types;

use Afeefa\ApiResources\Eloquent\ModelType;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\StringAttribute;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Comment;
use Afeefa\ApiResources\Type\Type;

class CommentType extends ModelType
{
    protected static string $type = 'Blog.Comment';

    public static string $ModelClass = Comment::class;

    protected function fields(FieldBag $fields): void
    {
        $fields
            ->attribute('text', StringAttribute::class)

            ->relation('owner', [AuthorType::class, ArticleType::class]);
    }

    protected function updateFields(FieldBag $updateFields): void
    {
        $updateFields
            ->attribute('text', StringAttribute::class)

            ->relation('owner', Type::link([AuthorType::class, ArticleType::class]));
    }

    protected function createFields(FieldBag $createFields, FieldBag $updateFields): void
    {
        $createFields
            ->from($updateFields, 'text', function (StringAttribute $attribute) {
                $attribute->required();
            })

            ->from($updateFields, 'owner');
    }
}
