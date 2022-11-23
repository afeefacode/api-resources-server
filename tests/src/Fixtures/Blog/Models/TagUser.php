<?php

namespace Afeefa\ApiResources\Test\Fixtures\Blog\Models;

use Afeefa\ApiResources\Eloquent\Model as EloquentModel;

class TagUser extends EloquentModel
{
    public static $type = 'Blog.TagUser';

    protected $table = 'tag_users';

    public function user()
    {
        return $this->morphTo();
    }
}
