<?php

namespace Afeefa\ApiResources\Test\Fixtures\Blog\Types;

use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\StringAttribute;
use Afeefa\ApiResources\Type\Type;

class ProfileType extends Type
{
    protected static string $type = 'Blog.Profile';

    protected function fields(FieldBag $fields): void
    {
        $fields->attribute('about_me', StringAttribute::class);
    }

    protected function updateFields(FieldBag $updateFields): void
    {
        $updateFields->attribute('about_me', StringAttribute::class);
    }

    protected function createFields(FieldBag $createFields, FieldBag $updateFields): void
    {
        $createFields->from($updateFields, 'about_me');
    }
}
