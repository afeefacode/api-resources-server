<?php

namespace Afeefa\ApiResources\Test\Fixtures\Blog\Models\Factories;

use function Afeefa\ApiResources\Test\fake;

use Illuminate\Database\Eloquent\Factories\Factory;

class AuthorFactory extends Factory
{
    public function definition()
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->email()
        ];
    }
}
