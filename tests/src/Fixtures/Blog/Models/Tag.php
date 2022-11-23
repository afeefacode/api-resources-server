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

    public function authors()
    {
        return $this->morphedByMany(Author::class, 'user', 'tag_users');
    }

    public function articles()
    {
        return $this->morphedByMany(Article::class, 'user', 'tag_users');
    }

    public function tag_users()
    {
        return $this->hasMany(TagUser::class);
    }
}
