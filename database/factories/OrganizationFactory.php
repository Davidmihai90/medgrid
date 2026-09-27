<?php

namespace Database\Factories;

use App\Domain\Organizations\Enums\OrganizationStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Synthetic '.fake()->unique()->company();

        return ['name' => $name, 'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'), 'type' => 'OTHER', 'status' => OrganizationStatus::Active, 'timezone' => 'Europe/Bucharest'];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => OrganizationStatus::Inactive]);
    }
}
