<?php

namespace Afeefa\ApiResources\Test\Fixtures\Blog\Types;

use Afeefa\ApiResources\Eloquent\ModelType;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\StringAttribute;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Link;

class LinkType extends ModelType
{
    protected static string $type = 'Blog.Link';

    public static string $ModelClass = Link::class;

    protected function fields(FieldBag $fields): void
    {
        $fields->string('url');
    }

    protected function updateFields(FieldBag $updateFields): void
    {
        $updateFields->string('url');
    }

    protected function createFields(FieldBag $createFields, FieldBag $updateFields): void
    {
        $createFields->from($updateFields, 'url', function (StringAttribute $attribute) {
            $attribute->required();
        });
    }
}
