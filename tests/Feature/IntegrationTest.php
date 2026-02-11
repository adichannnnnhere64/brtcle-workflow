<?php

use Adichan\WorkflowEngine\Tests\TestCase;
use Adichan\WorkflowEngine\Tests\Mocks\Models\User;
use Adichan\WorkflowEngine\Tests\Mocks\Models\PaymentRequest;
use Illuminate\Support\Facades\Gate;

/* uses(TestCase::class); */

beforeEach(function () {
    // Setup gates for testing
    Gate::define('workflow.can_submit', function ($user) {
        return $user->hasRole('employee');
    });

    Gate::define('workflow.can_approve', function ($user) {
        return $user->hasRole('reviewer');
    });

    Gate::define('workflow.can_reject', function ($user) {
        return $user->hasRole('reviewer');
    });

    Gate::define('workflow.can_request_revision', function ($user) {
        return $user->hasRole('reviewer');
    });

    Gate::define('workflow.can_process_payment', function ($user) {
        return $user->hasRole('finance');
    });
});

test('full integration with mock models', function () {
    $employee = User::factory()->employee()->create();
    $reviewer = User::factory()->reviewer()->create();
    
    // Create payment request with description (required by hook)
    $paymentRequest = PaymentRequest::factory()->draft()->create([
        'user_id' => $employee->id,
        'description' => 'Test payment request',
    ]);
    
    // Employee can submit
    $this->actingAs($employee);
    expect($paymentRequest->canTransition('submit', [
        'attachments' => ['file.pdf']
    ]))->toBeTrue();
    
    // Submit the request
    $transition = $paymentRequest->transition('submit', [
        'attachments' => ['invoice.pdf'],
    ]);
    
    expect($paymentRequest->refresh()->status)->toBe('under_review');
    
    // Reviewer can approve
    $this->actingAs($reviewer);
    $paymentRequest->refresh();
    
    expect($paymentRequest->canTransition('approve'))->toBeTrue();
    
    // Approve the request
    $paymentRequest->transition('approve', [
        'approved_at' => now(),
        'remarks' => 'Looks good',
    ]);
    
    expect($paymentRequest->refresh()->status)->toBe('approved');
    
    // Verify history
    $history = $paymentRequest->transitionHistory();
    expect($history)->toHaveCount(2)
        ->and($history[0]['transition'])->toBe('submit')
        ->and($history[1]['transition'])->toBe('approve');
});
