<?php

use Adichan\WorkflowEngine\States\StateMachine;
use Adichan\WorkflowEngine\Tests\TestCase;
use Adichan\WorkflowEngine\Tests\Mocks\Models\PaymentRequest;
use Adichan\WorkflowEngine\Tests\Mocks\Models\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->employee = User::factory()->create(['role' => 'employee']);
    $this->reviewer = User::factory()->create(['role' => 'reviewer']);
    $this->finance = User::factory()->create(['role' => 'finance']);
    
    // Setup gates
    Gate::define('workflow.can_submit', fn($user) => $user->hasRole('employee'));
    Gate::define('workflow.can_approve', fn($user) => $user->hasRole('reviewer'));
    Gate::define('workflow.can_reject', fn($user) => $user->hasRole('reviewer'));
    Gate::define('workflow.can_request_revision', fn($user) => $user->hasRole('reviewer'));
    Gate::define('workflow.can_process_payment', fn($user) => $user->hasRole('finance'));
});

test('complete payment request workflow', function () {
    // Step 1: Employee creates and submits request
    $this->actingAs($this->employee);
    
    $request = PaymentRequest::factory()->create([
        'user_id' => $this->employee->id,
        'status' => 'draft',
        'description' => 'Test payment request', // Required by hook
    ]);
    
    // Submit with attachments
    $transition = $request->transition('submit', [
        'attachments' => ['invoice.pdf', 'receipt.jpg'],
        'metadata' => ['submitted_at' => now()],
    ]);
    
    expect($request->refresh()->status)->toBe('under_review');
    
    // Step 2: Reviewer requests revision
    $this->actingAs($this->reviewer);
    $request->refresh();
    
    $transition = $request->transition('request_revision', [
        'remarks' => 'Please provide proper invoice',
        'metadata' => ['requested_by' => $this->reviewer->id],
    ]);
    
    expect($request->refresh()->status)->toBe('draft')
        ->and($transition->getContext())->toHaveKey('remarks');
    
    // Step 1 (again): Employee resubmits
    $this->actingAs($this->employee);
    $request->refresh();
    
    $request->transition('submit', [
        'attachments' => ['correct_invoice.pdf'],
        'remarks' => 'Updated invoice attached',
    ]);
    
    expect($request->refresh()->status)->toBe('under_review');
    
    // Step 2 (again): Reviewer approves
    $this->actingAs($this->reviewer);
    $request->refresh();
    
    $request->transition('approve');
    
    expect($request->refresh()->status)->toBe('approved');
    
    // Step 3: Finance processes payment
    $this->actingAs($this->finance);
    $request->refresh();
    
    $request->transition('process_payment', [
        'payment_proof' => 'payment_receipt.pdf',
        'processed_at' => now(),
        'metadata' => ['payment_method' => 'bank_transfer'],
    ]);
    
    expect($request->refresh()->status)->toBe('completed');
    
    // Verify complete history
    $history = $request->transitionHistory();
    
    expect($history)->toHaveCount(5)
        ->and($history[0]['transition'])->toBe('submit')
        ->and($history[1]['transition'])->toBe('request_revision')
        ->and($history[2]['transition'])->toBe('submit')
        ->and($history[3]['transition'])->toBe('approve')
        ->and($history[4]['transition'])->toBe('process_payment');
});

test('workflow prevents illegal transitions', function () {
    $this->actingAs($this->employee);
    
    $request = PaymentRequest::factory()->create([
        'user_id' => $this->employee->id,
        'status' => 'draft',
        'description' => 'Test',
    ]);
    
    // Cannot approve from draft
    expect($request->canTransition('approve'))->toBeFalse();
    
    // Submit first
    $request->transition('submit', ['attachments' => ['file.pdf']]);
    
    // Cannot submit again (already under review)
    expect($request->canTransition('submit'))->toBeFalse();
    
    // Employee cannot approve their own request
    $this->actingAs($this->employee);
    expect($request->canTransition('approve'))->toBeFalse();
    
    // Switch to reviewer
    $this->actingAs($this->reviewer);
    $request->refresh();
    
    // Cannot process payment from under_review
    expect($request->canTransition('process_payment'))->toBeFalse();
});

test('workflow with conditional transitions - simplified', function () {
    // Create a custom workflow with conditions
    $customConfig = [
        'state_column' => 'status',
        'states' => [
            ['value' => 'draft', 'label' => 'Draft', 'is_initial' => true],
            ['value' => 'under_review', 'label' => 'Under Review'],
            ['value' => 'approved', 'label' => 'Approved'],
        ],
        'transitions' => [
            [
                'name' => 'submit',
                'from' => 'draft',
                'to' => 'under_review',
                'guard' => 'workflow.can_submit',
            ],
            [
                'name' => 'approve',
                'from' => 'under_review',
                'to' => 'approved',
                'guard' => 'workflow.can_approve',
            ],
        ],
        'conditions' => [
            'approve' => function($model) {
                \Log::debug("Condition called with amount: " . $model->amount);
                return $model->amount <= 5000;
            },
        ],
    ];
    
    // Set the config
    config()->set('workflow.workflows.simple_conditional', $customConfig);
    
    // Verify config was set
    $retrieved = config('workflow.workflows.simple_conditional');
    
    $this->actingAs($this->reviewer);
    
    // Create request with large amount
    $request = PaymentRequest::factory()->create([
        'status' => 'under_review',
        'amount' => 10000,
    ]);
    
    // Manually create workflow to test
    $workflow = new StateMachine('simple_conditional', $customConfig);
    
    $canApprove = $workflow->can($request, 'approve');
    
    expect($canApprove)->toBeFalse();
});
