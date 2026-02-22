<?php

namespace Afeefa\ApiResources\Test\Fixtures\Blog\Models;

use Afeefa\ApiResources\Eloquent\Model as EloquentModel;
use Ankurk91\Eloquent\HasMorphToOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Author extends EloquentModel
{
    use HasFactory;
    use HasMorphToOne;

    public static $type = 'Blog.Author';

    protected $table = 'authors';

    public $timestamps = false;

    public function articles()
    {
        return $this->hasMany(Article::class);
    }

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'user', 'tag_users');
    }

    public function links()
    {
        return $this->hasMany(Link::class);
    }

    public function featured_tag()
    {
        return $this->belongsTo(Tag::class);
    }

    public function first_tag()
    {
        return $this->morphToOne(Tag::class, 'user', 'tag_users');
    }

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'owner');
    }
}
