<?php

use Adichan\WorkflowEngine\Tests\TestCase;
use Adichan\WorkflowEngine\Workflows\PaymentRequestWorkflow;
use Adichan\WorkflowEngine\Tests\Mocks\Models\PaymentRequest;
use Adichan\WorkflowEngine\Tests\Mocks\Models\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('workflow.can_submit', fn($user) => $user->hasRole('employee'));
    Gate::define('workflow.can_approve', fn($user) => $user->hasRole('reviewer'));
    Gate::define('workflow.can_process_payment', fn($user) => $user->hasRole('finance'));
});

test('workflow transitions respect business rules', function () {
    $workflow = new PaymentRequestWorkflow();
    
    $user = User::factory()->employee()->create();
    $paymentRequest = PaymentRequest::factory()->draft()->create([
        'user_id' => $user->id,
        'description' => 'Test',
        'amount' => 150000, // Over 100k limit
    ]);
    
    // Submit first
    $this->actingAs($user);
    $workflow->apply($paymentRequest, 'submit', ['attachments' => ['file.pdf']]);
    $paymentRequest->refresh();
    
    // Now under review, but amount > 100k so cannot approve (per condition)
    $this->actingAs(User::factory()->reviewer()->create());
    expect($workflow->can($paymentRequest, 'approve'))->toBeFalse();
});

test('workflow hooks execute properly', function () {
    $workflow = new PaymentRequestWorkflow();
    
    $user = User::factory()->employee()->create();
    $paymentRequest = PaymentRequest::factory()->draft()->create([
        'user_id' => $user->id,
        'description' => '', // Empty description - should trigger hook
    ]);
    
    // Should fail before hook validation (description is empty)
    $this->actingAs($user);
    
    expect(fn() => $workflow->apply($paymentRequest, 'submit', [
        'attachments' => ['file.pdf'],
    ]))->toThrow(\DomainException::class, 'Description is required');
});

test('workflow with multiple conditions', function () {
    $workflow = new PaymentRequestWorkflow();
    
    $user = User::factory()->employee()->create();
    $paymentRequest = PaymentRequest::factory()->approved()->create([
        'user_id' => $user->id,
        'payment_due_date' => now()->subDays(1), // Overdue
    ]);
    
    $financeUser = User::factory()->finance()->create();
    $this->actingAs($financeUser);
    
    // Cannot process overdue payment (per condition)
    expect($workflow->can($paymentRequest, 'process_payment', [
        'payment_proof' => 'proof.pdf',
        'processed_at' => now(),
    ]))->toBeFalse();
});

test('workflow applies transitions correctly', function () {
    $workflow = new PaymentRequestWorkflow();
    
    $user = User::factory()->employee()->create();
    $paymentRequest = PaymentRequest::factory()->draft()->create([
        'user_id' => $user->id,
        'description' => 'Valid description',
        'amount' => 50000,
    ]);
    
    // Submit with attachments
    $this->actingAs($user);
    $transition = $workflow->apply($paymentRequest, 'submit', [
        'attachments' => ['invoice.pdf'],
    ]);
    
    expect($paymentRequest->refresh()->status)->toBe('under_review')
        ->and($transition->getFromState())->toBe('draft')
        ->and($transition->getToState())->toBe('under_review');
});
