<?php

namespace Afeefa\ApiResources\Test\Fixtures\Blog\Models;

use Afeefa\ApiResources\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tag extends EloquentModel
{
    use HasFactory;

    public static $type = 'Blog.Tag';

    protected $table = 'tags';

    public $timestamps = false;
}
