<?php

namespace Afeefa\ApiResources\Test\Fixtures\Blog\Models;

use Afeefa\ApiResources\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Profile extends EloquentModel
{
    use HasFactory;

    public static $type = 'Blog.Profile';

    protected $table = 'profiles';

    public $timestamps = false;
}
