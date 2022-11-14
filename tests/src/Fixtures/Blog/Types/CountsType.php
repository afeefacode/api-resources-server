<?php

namespace Afeefa\ApiResources\Test\Fixtures\Blog\Types;

use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\IntAttribute;
use Afeefa\ApiResources\Type\Type;

class CountsType extends Type
{
    protected static string $type = 'Blog.Counts';

    protected function fields(FieldBag $fields): void
    {
        $fields
            ->attribute('count_articles', IntAttribute::class)

            ->attribute('count_authors', IntAttribute::class);
    }
}
