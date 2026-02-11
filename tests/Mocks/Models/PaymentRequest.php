<?php

namespace Adichan\WorkflowEngine\Tests\Mocks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Adichan\WorkflowEngine\Traits\HasWorkflow;
use Adichan\WorkflowEngine\Tests\Mocks\Factories\PaymentRequestFactory;

class PaymentRequest extends Model
{
    use HasFactory, HasWorkflow;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'amount',
        'status',
        'attachments',
        'payment_due_date',
        'assigned_to',
        'paid_at',
        'payment_confirmation',
    ];

    protected $casts = [
        'attachments' => 'array',
        'amount' => 'decimal:2',
        'payment_due_date' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public string $workflowName = 'payment_request';
    protected string $stateColumn = 'status';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    public function isApproved(): bool
    {
        return in_array($this->status, ['approved', 'completed']);
    }

    public function canBeProcessed(): bool
    {
        return $this->status === 'approved' && $this->payment_due_date > now();
    }

    protected static function newFactory(): Factory
    {
        return PaymentRequestFactory::new();
    }
}
