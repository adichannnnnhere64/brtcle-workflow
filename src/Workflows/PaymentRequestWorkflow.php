<?php

declare(strict_types=1);

namespace Adichan\WorkflowEngine\Workflows;

use Adichan\WorkflowEngine\Workflow;
use Adichan\WorkflowEngine\Enums\WorkflowGuard;
use Illuminate\Database\Eloquent\Model;

class PaymentRequestWorkflow extends Workflow
{
    public function getName(): string
    {
        return 'payment_request';
    }

    public function getDefinition(): array
    {
        return [
            'state_column' => 'status',
            'states' => [
                [
                    'value' => 'draft',
                    'label' => 'Draft',
                    'is_initial' => true,
                    'is_final' => false,
                ],
                [
                    'value' => 'under_review',
                    'label' => 'Under Review',
                    'is_initial' => false,
                    'is_final' => false,
                ],
                [
                    'value' => 'approved',
                    'label' => 'Approved',
                    'is_initial' => false,
                    'is_final' => false,
                ],
                [
                    'value' => 'rejected',
                    'label' => 'Rejected',
                    'is_initial' => false,
                    'is_final' => true,
                ],
                [
                    'value' => 'completed',
                    'label' => 'Completed',
                    'is_initial' => false,
                    'is_final' => true,
                ],
            ],
            'transitions' => [
                [
                    'name' => 'submit',
                    'from' => 'draft',
                    'to' => 'under_review',
                    'guard' => WorkflowGuard::CAN_SUBMIT->value,
                    'validator' => fn(array $context) => isset($context['attachments']) && count($context['attachments']) > 0,
                ],
                [
                    'name' => 'approve',
                    'from' => 'under_review',
                    'to' => 'approved',
                    'guard' => WorkflowGuard::CAN_APPROVE->value,
                ],
                [
                    'name' => 'reject',
                    'from' => 'under_review',
                    'to' => 'rejected',
                    'guard' => WorkflowGuard::CAN_REJECT->value,
                    'validator' => fn(array $context) => isset($context['reason']) && !empty($context['reason']),
                ],
                [
                    'name' => 'request_revision',
                    'from' => 'under_review',
                    'to' => 'draft',
                    'guard' => WorkflowGuard::CAN_REQUEST_REVISION->value,
                    'validator' => fn(array $context) => isset($context['remarks']) && !empty($context['remarks']),
                ],
                [
                    'name' => 'process_payment',
                    'from' => 'approved',
                    'to' => 'completed',
                    'guard' => WorkflowGuard::CAN_PROCESS_PAYMENT->value,
                    'validator' => fn(array $context) => isset($context['payment_proof']) && isset($context['processed_at']),
                ],
            ],
            'conditions' => [
                // Custom business logic
                'approve' => fn(Model $model) => $model->amount <= 100000,
                'process_payment' => fn(Model $model) => $model->payment_due_date > now(),
            ],
            'hooks' => [
                'before' => [
                    'submit' => function (Model $model, string $from, string $to, array $context) {
                        // Validate required fields before submission
                        if (empty($model->description)) {
                            throw new \DomainException('Description is required');
                        }
                    },
                ],
                'after' => [
                    'approve' => function (Model $model, string $from, string $to, array $context) {
                        // Auto-assign to finance team if amount > 5000
                        if ($model->amount > 5000) {
                            $model->assigned_to = 'finance_team';
                            $model->save();
                        }
                    },
                    'process_payment' => function (Model $model, string $from, string $to, array $context) {
                        // Mark as paid in accounting system
                        $model->update([
                            'paid_at' => now(),
                            'payment_confirmation' => $context['payment_proof'] ?? null,
                        ]);
                    },
                ],
            ],
        ];
    }
}
