<?php

namespace Afeefa\ApiResources\Test\Fixtures\Blog\Models;

use Afeefa\ApiResources\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Comment extends EloquentModel
{
    use HasFactory;

    public static $type = 'Blog.Comment';

    protected $table = 'comments';

    public $timestamps = false;

    public function owner()
    {
        return $this->morphTo('owner');
    }
}
