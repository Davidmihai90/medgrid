<?php

namespace Database\Factories;

use App\Domain\Identity\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return ['name' => fake()->name(), 'email' => fake()->unique()->safeEmail(), 'email_verified_at' => now(), 'status' => UserStatus::Active, 'password' => static::$password ??= Hash::make('Test-Only!Password2026'), 'remember_token' => Str::random(10)];
    }

    public function invited(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Invited]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Suspended]);
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Disabled]);
    }
}
