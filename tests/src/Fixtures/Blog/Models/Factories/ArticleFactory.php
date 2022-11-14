<?php

namespace Afeefa\ApiResources\Test\Fixtures\Blog\Models\Factories;

use function Afeefa\ApiResources\Test\fake;

use Illuminate\Database\Eloquent\Factories\Factory;

class ArticleFactory extends Factory
{
    public function definition()
    {
        return [
            'title' => fake()->sentence(),
            'date' => fake()->date()
        ];
    }
}
