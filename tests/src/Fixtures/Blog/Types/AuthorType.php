<?php

namespace Afeefa\ApiResources\Test\Fixtures\Blog\Types;

use Afeefa\ApiResources\Eloquent\ModelType;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\StringAttribute;
use Afeefa\ApiResources\Field\Relation;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Author;
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

            ->hasMany('articles', ArticleType::class, function (Relation $relation) {
                $relation
                    ->restrictTo(Relation::RESTRICT_TO_COUNT);
            })

            ->hasMany('comments', CommentType::class)

            ->hasMany('links', LinkType::class)

            ->hasMany('tags', TagType::class)

            ->hasOne('featured_tag', TagType::class)

            ->hasOne('first_tag', TagType::class)

            ->hasOne('profile', ProfileType::class);
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

            ->hasMany('comments', CommentType::class)

            ->linkMany('tags', TagType::class)

            ->hasMany('links', LinkType::class)

            ->linkOne('featured_tag', TagType::class)

            ->linkOne('first_tag', TagType::class)

            ->hasOne('profile', ProfileType::class);
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
