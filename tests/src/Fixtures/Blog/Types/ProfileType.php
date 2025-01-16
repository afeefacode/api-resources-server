<?php

namespace Afeefa\ApiResources\Test\Fixtures\Blog\Types;

use Afeefa\ApiResources\Eloquent\ModelType;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Profile;

class ProfileType extends ModelType
{
    protected static string $type = 'Blog.Profile';

    public static string $ModelClass = Profile::class;

    protected function fields(FieldBag $fields): void
    {
        $fields
            ->string('about_me')

            ->hasOne('author', AuthorType::class);
    }

    protected function updateFields(FieldBag $updateFields): void
    {
        $updateFields
            ->string('about_me')

            ->linkOne('author', AuthorType::class);
    }

    protected function createFields(FieldBag $createFields, FieldBag $updateFields): void
    {
        $createFields
            ->from($updateFields, 'about_me')

            ->from($updateFields, 'author');
    }
}
