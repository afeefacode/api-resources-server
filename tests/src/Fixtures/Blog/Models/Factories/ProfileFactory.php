<?php

namespace Afeefa\ApiResources\Test\Fixtures\Blog\Models\Factories;

use function Afeefa\ApiResources\Test\fake;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProfileFactory extends Factory
{
    public function definition()
    {
        return [
            'about_me' => fake()->sentence()
        ];
    }
}
