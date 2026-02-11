<?php

use Adichan\WorkflowEngine\States\StateMachine;
use Adichan\WorkflowEngine\Enums\WorkflowGuard;
use Adichan\WorkflowEngine\Tests\Mocks\Models\PaymentRequest;
use Adichan\WorkflowEngine\Tests\Mocks\Models\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'employee']);
    $this->reviewer = User::factory()->create(['role' => 'reviewer']);
    $this->finance = User::factory()->create(['role' => 'finance']);

    // Define workflow config
    $this->testWorkflowConfig = [
        'state_column' => 'status',
        'states' => [
            ['value' => 'draft', 'label' => 'Draft', 'is_initial' => true],
            ['value' => 'under_review', 'label' => 'Under Review'],
            ['value' => 'approved', 'label' => 'Approved'],
            ['value' => 'rejected', 'label' => 'Rejected', 'is_final' => true],
            ['value' => 'completed', 'label' => 'Completed', 'is_final' => true],
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
            [
                'name' => 'reject',
                'from' => 'under_review',
                'to' => 'rejected',
                'guard' => 'workflow.can_reject',
            ],
            [
                'name' => 'request_revision',
                'from' => 'under_review',
                'to' => 'draft',
                'guard' => 'workflow.can_request_revision',
            ],
            [
                'name' => 'process_payment',
                'from' => 'approved',
                'to' => 'completed',
                'guard' => 'workflow.can_process_payment',
            ],
        ],
    ];

    // Setup gates - ensure they work with our mock User model
    Gate::define('workflow.can_submit', function ($user) {
        return in_array($user->role, ['employee', 'manager']);
    });

    Gate::define('workflow.can_approve', function ($user) {
        return in_array($user->role, ['reviewer', 'manager', 'director']);
    });

    Gate::define('workflow.can_reject', function ($user) {
        return in_array($user->role, ['reviewer', 'manager', 'director']);
    });

    Gate::define('workflow.can_request_revision', function ($user) {
        return in_array($user->role, ['reviewer', 'manager']);
    });

    Gate::define('workflow.can_process_payment', function ($user) {
        return in_array($user->role, ['finance']);
    });
});

it('creates workflow instance', function () {
    $workflow = new StateMachine('payment_request', $this->testWorkflowConfig);
    
    expect($workflow)->toBeInstanceOf(StateMachine::class);
});

it('returns available transitions', function () {
    // We need to be authenticated as the right user
    $this->actingAs($this->user);
    
    $request = PaymentRequest::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'draft',
    ]);

    $workflow = new StateMachine('payment_request', $this->testWorkflowConfig);
    $transitions = $workflow->getAvailableTransitions($request);
    
    // Debug output to see what's happening
    if (empty($transitions)) {
        \Log::debug('No transitions available. Current user role:', ['role' => $this->user->role]);
        \Log::debug('Current state:', ['status' => $request->status]);
        \Log::debug('Gate check result:', ['can_submit' => Gate::allows('workflow.can_submit', $request)]);
    }
    
    expect($transitions)->toHaveKey('submit');
});

it('allows employee to submit draft', function () {
    $this->actingAs($this->user);
    
    $request = PaymentRequest::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'draft',
    ]);

    $workflow = new StateMachine('payment_request', $this->testWorkflowConfig);
    
    expect($workflow->can($request, 'submit'))->toBeTrue();
});

it('prevents non-reviewer from approving', function () {
    $this->actingAs($this->user); // Employee, not reviewer
    
    $request = PaymentRequest::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'under_review',
    ]);

    $workflow = new StateMachine('payment_request', $this->testWorkflowConfig);
    
    expect($workflow->can($request, 'approve'))->toBeFalse();
});

it('allows reviewer to approve', function () {
    $this->actingAs($this->reviewer);
    
    $request = PaymentRequest::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'under_review',
    ]);

    $workflow = new StateMachine('payment_request', $this->testWorkflowConfig);
    
    expect($workflow->can($request, 'approve'))->toBeTrue();
});

it('applies transition successfully', function () {
    $this->actingAs($this->user);
    
    $request = PaymentRequest::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'draft',
    ]);

    $workflow = new StateMachine('payment_request', $this->testWorkflowConfig);
    $transition = $workflow->apply($request, 'submit', [
        'attachments' => ['file1.pdf'],
    ]);
    
    expect($request->refresh()->status)->toBe('under_review')
        ->and($transition->getTransitionName())->toBe('submit')
        ->and($transition->getFromState())->toBe('draft')
        ->and($transition->getToState())->toBe('under_review');
});

it('prevents invalid transition', function () {
    $this->actingAs($this->user);
    
    $request = PaymentRequest::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'draft',
    ]);

    $workflow = new StateMachine('payment_request', $this->testWorkflowConfig);
    
    expect(fn() => $workflow->apply($request, 'approve'))
        ->toThrow(\DomainException::class);
});

it('maintains transition history', function () {
    $this->actingAs($this->user);
    
    $request = PaymentRequest::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'draft',
    ]);

    $workflow = new StateMachine('payment_request', $this->testWorkflowConfig);
    
    $workflow->apply($request, 'submit');
    $request->refresh();
    
    $this->actingAs($this->reviewer);
    $workflow->apply($request, 'approve');
    
    $history = $workflow->getHistory($request);
    
    expect($history)->toHaveCount(2)
        ->and($history[0]['transition'])->toBe('submit')
        ->and($history[1]['transition'])->toBe('approve');
});

it('enforces workflow rules', function () {
    $this->actingAs($this->finance);
    
    $request = PaymentRequest::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'under_review', // Not approved yet
    ]);

    $workflow = new StateMachine('payment_request', $this->testWorkflowConfig);
    
    expect($workflow->can($request, 'process_payment'))->toBeFalse();
});

it('validates transition context', function () {
    // Create a modified config with validator
    $config = $this->testWorkflowConfig;
    
    // Add validator to the request_revision transition
    foreach ($config['transitions'] as &$transition) {
        if ($transition['name'] === 'request_revision') {
            $transition['validator'] = function($context) {
                return isset($context['remarks']) && !empty($context['remarks']);
            };
        }
    }
    
    $this->actingAs($this->reviewer);
    
    $request = PaymentRequest::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'under_review',
    ]);

    $workflow = new StateMachine('payment_request', $config);
    
    expect($workflow->can($request, 'request_revision', []))->toBeFalse()
        ->and($workflow->can($request, 'request_revision', ['remarks' => 'Need revision']))->toBeTrue();
});
