<?php

namespace Adichan\WorkflowEngine\Tests\Mocks\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Adichan\WorkflowEngine\Tests\Mocks\Models\PaymentRequest;

class PaymentRequestFactory extends Factory
{
    protected $model = PaymentRequest::class;

    public function definition(): array
    {
        return [
            'user_id' => fn() => UserFactory::new()->create()->id,
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'amount' => $this->faker->randomFloat(2, 100, 10000),
            'status' => 'draft',
            'attachments' => [],
            'payment_due_date' => now()->addDays(30),
            'assigned_to' => null,
            'paid_at' => null,
            'payment_confirmation' => null,
        ];
    }

    public function draft(): self
    {
        return $this->state([
            'status' => 'draft',
        ]);
    }

    public function underReview(): self
    {
        return $this->state([
            'status' => 'under_review',
        ]);
    }

    public function approved(): self
    {
        return $this->state([
            'status' => 'approved',
        ]);
    }

    public function rejected(): self
    {
        return $this->state([
            'status' => 'rejected',
        ]);
    }

    public function completed(): self
    {
        return $this->state([
            'status' => 'completed',
            'paid_at' => now(),
        ]);
    }

    public function withAttachments(array $attachments): self
    {
        return $this->state([
            'attachments' => $attachments,
        ]);
    }

    public function withAmount(float $amount): self
    {
        return $this->state([
            'amount' => $amount,
        ]);
    }

    public function overdue(): self
    {
        return $this->state([
            'payment_due_date' => now()->subDays(10),
        ]);
    }
}
