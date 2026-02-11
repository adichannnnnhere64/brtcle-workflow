<?php

namespace Adichan\WorkflowEngine\Tests\Mocks\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Adichan\WorkflowEngine\Tests\Mocks\Models\User;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'role' => 'employee',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ];
    }

    public function employee(): self
    {
        return $this->state([
            'role' => 'employee',
        ]);
    }

    public function reviewer(): self
    {
        return $this->state([
            'role' => 'reviewer',
        ]);
    }

    public function finance(): self
    {
        return $this->state([
            'role' => 'finance',
        ]);
    }

    public function manager(): self
    {
        return $this->state([
            'role' => 'manager',
        ]);
    }

    public function director(): self
    {
        return $this->state([
            'role' => 'director',
        ]);
    }
}
