<?php

namespace Afeefa\ApiResources\Test\Fixtures\Blog\Models;

use Afeefa\ApiResources\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Article extends EloquentModel
{
    use HasFactory;

    public static $type = 'Blog.Article';

    protected $table = 'articles';

    public $timestamps = false;

    public function author()
    {
        return $this->belongsTo(Author::class);
    }

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'user', 'tag_users');
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'owner');
    }
}
