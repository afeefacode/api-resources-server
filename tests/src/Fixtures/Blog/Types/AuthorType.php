<?php

namespace Afeefa\ApiResources\Test\Fixtures\Blog\Types;

use Afeefa\ApiResources\Eloquent\ModelType;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\StringAttribute;
use Afeefa\ApiResources\Field\Relation;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Author;
use Afeefa\ApiResources\Type\Type;
use Afeefa\ApiResources\Validator\Validators\StringValidator;

class AuthorType extends ModelType
{
    protected static string $type = 'Blog.Author';

    public static string $ModelClass = Author::class;

    protected function fields(FieldBag $fields): void
    {
        $fields
            ->attribute('name', StringAttribute::class)

            ->attribute('email', StringAttribute::class)

            ->relation('articles', Type::list(ArticleType::class), function (Relation $relation) {
                $relation
                    ->restrictTo(Relation::RESTRICT_TO_COUNT);
            })

            ->relation('comments', Type::list(CommentType::class))

            ->relation('links', Type::list(LinkType::class))

            ->relation('tags', Type::list(TagType::class))

            ->relation('featured_tag', TagType::class)

            ->relation('first_tag', TagType::class)

            ->relation('profile', ProfileType::class);
    }

    protected function updateFields(FieldBag $updateFields): void
    {
        $updateFields
            ->attribute('name', function (StringAttribute $attribute) {
                $attribute->validate(function (StringValidator $v) {
                    $v
                        ->filled()
                        ->min(5)
                        ->max(101);
                });
            })

            ->relation('comments', Type::list(CommentType::class))

            ->relation('tags', Type::list(Type::link(TagType::class)))

            ->relation('links', Type::list(LinkType::class))

            ->relation('featured_tag', Type::link(TagType::class))

            ->relation('first_tag', Type::link(TagType::class))

            ->relation('profile', ProfileType::class);
    }

    protected function createFields(FieldBag $createFields, FieldBag $updateFields): void
    {
        $createFields
            ->from($updateFields, 'name')

            ->attribute('email', StringAttribute::class)

            ->from($updateFields, 'comments')

            ->from($updateFields, 'tags')

            ->from($updateFields, 'links')

            ->from($updateFields, 'featured_tag')

            ->from($updateFields, 'first_tag')

            ->from($updateFields, 'profile');
    }
}
