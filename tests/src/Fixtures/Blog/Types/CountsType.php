<?php

namespace Afeefa\ApiResources\Test\Fixtures\Blog\Types;

use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Type\Type;

class CountsType extends Type
{
    protected static string $type = 'Blog.Counts';

    protected function fields(FieldBag $fields): void
    {
        $fields
            ->int('count_articles')

            ->int('count_authors');
    }
}
