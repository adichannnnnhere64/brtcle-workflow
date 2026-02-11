<?php

use Adichan\WorkflowEngine\Tests\TestCase;
use Adichan\WorkflowEngine\Tests\Mocks\Models\PaymentRequest;
use Adichan\WorkflowEngine\Tests\Mocks\Models\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    // Create users
    $this->employee = User::factory()->create(['role' => 'employee', 'name' => 'Employee']);
    
    // Create multiple reviewers with same role
    $this->reviewer1 = User::factory()->create(['role' => 'reviewer', 'name' => 'Reviewer 1']);
    $this->reviewer2 = User::factory()->create(['role' => 'reviewer', 'name' => 'Reviewer 2']);
    $this->reviewer3 = User::factory()->create(['role' => 'reviewer', 'name' => 'Reviewer 3']);
    
    // Setup basic gates
    Gate::define('workflow.can_submit', fn($user) => $user->hasRole('employee'));
    Gate::define('workflow.can_approve', fn($user) => in_array($user->role, ['reviewer', 'manager', 'director']));
    Gate::define('workflow.can_reject', fn($user) => in_array($user->role, ['reviewer', 'manager', 'director']));
});

test('approval queue with multiple approvers - first come first served', function () {
    // Create a workflow where once one approver acts, others cannot
    $customConfig = [
        'state_column' => 'status',
        'states' => [
            ['value' => 'draft', 'label' => 'Draft', 'is_initial' => true],
            ['value' => 'pending_approval', 'label' => 'Pending Approval'],
            ['value' => 'approved', 'label' => 'Approved', 'is_final' => true],
            ['value' => 'rejected', 'label' => 'Rejected', 'is_final' => true],
        ],
        'transitions' => [
            [
                'name' => 'submit',
                'from' => 'draft',
                'to' => 'pending_approval',
                'guard' => 'workflow.can_submit',
            ],
            [
                'name' => 'approve',
                'from' => 'pending_approval',
                'to' => 'approved',
                'guard' => 'workflow.can_approve',
            ],
            [
                'name' => 'reject',
                'from' => 'pending_approval',
                'to' => 'rejected',
                'guard' => 'workflow.can_reject',
            ],
        ],
    ];
    
    config()->set('workflow.workflows.approval_queue', $customConfig);
    
    // Create request
    $this->actingAs($this->employee);
    $request = PaymentRequest::factory()->create([
        'user_id' => $this->employee->id,
        'status' => 'draft',
    ]);
    
    // Override workflow
    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('workflowName');
    $property->setAccessible(true);
    $property->setValue($request, 'approval_queue');
    
    // Submit
    $request->transition('submit', ['attachments' => ['file.pdf']]);
    expect($request->refresh()->status)->toBe('pending_approval');
    
    // All reviewers can approve
    $this->actingAs($this->reviewer1);
    $request->refresh();
    expect($request->canTransition('approve'))->toBeTrue();
    
    $this->actingAs($this->reviewer2);
    $request->refresh();
    expect($request->canTransition('approve'))->toBeTrue();
    
    $this->actingAs($this->reviewer3);
    $request->refresh();
    expect($request->canTransition('approve'))->toBeTrue();
    
    // Reviewer2 is first to approve
    $this->actingAs($this->reviewer2);
    $request->refresh();
    $request->transition('approve');
    expect($request->refresh()->status)->toBe('approved');
    
    // After approval, other reviewers cannot approve anymore
    $this->actingAs($this->reviewer1);
    $request->refresh();
    expect($request->canTransition('approve'))->toBeFalse();
    
    $this->actingAs($this->reviewer3);
    $request->refresh();
    expect($request->canTransition('approve'))->toBeFalse();
    
    // History shows Reviewer2 as the approver
    $history = $request->transitionHistory();
    $approveTransition = collect($history)->firstWhere('transition', 'approve');
    expect($approveTransition['performed_by'])->toBe($this->reviewer2->id);
});

test('approval with department-based multiple approvers', function () {
    // Create users with same role but different departments
    $hrReviewer = User::factory()->create(['role' => 'reviewer', 'department' => 'HR', 'name' => 'HR Reviewer']);
    $financeReviewer = User::factory()->create(['role' => 'reviewer', 'department' => 'Finance', 'name' => 'Finance Reviewer']);
    $itReviewer = User::factory()->create(['role' => 'reviewer', 'department' => 'IT', 'name' => 'IT Reviewer']);
    
    // Custom gate that checks both role AND department
    Gate::define('workflow.can_approve_hr', fn($user) => $user->role === 'reviewer' && $user->department === 'HR');
    Gate::define('workflow.can_approve_finance', fn($user) => $user->role === 'reviewer' && $user->department === 'Finance');
    Gate::define('workflow.can_approve_it', fn($user) => $user->role === 'reviewer' && $user->department === 'IT');
    
    $customConfig = [
        'state_column' => 'status',
        'states' => [
            ['value' => 'draft', 'label' => 'Draft', 'is_initial' => true],
            ['value' => 'pending_hr', 'label' => 'Pending HR'],
            ['value' => 'pending_finance', 'label' => 'Pending Finance'],
            ['value' => 'pending_it', 'label' => 'Pending IT'],
            ['value' => 'approved', 'label' => 'Approved', 'is_final' => true],
        ],
        'transitions' => [
            [
                'name' => 'submit',
                'from' => 'draft',
                'to' => 'pending_hr',
                'guard' => 'workflow.can_submit',
            ],
            [
                'name' => 'approve_hr',
                'from' => 'pending_hr',
                'to' => 'pending_finance',
                'guard' => 'workflow.can_approve_hr',
            ],
            [
                'name' => 'approve_finance',
                'from' => 'pending_finance',
                'to' => 'pending_it',
                'guard' => 'workflow.can_approve_finance',
            ],
            [
                'name' => 'approve_it',
                'from' => 'pending_it',
                'to' => 'approved',
                'guard' => 'workflow.can_approve_it',
            ],
        ],
    ];
    
    config()->set('workflow.workflows.department_approval', $customConfig);
    
    $this->actingAs($this->employee);
    $request = PaymentRequest::factory()->create([
        'user_id' => $this->employee->id,
        'status' => 'draft',
    ]);
    
    // Override workflow
    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('workflowName');
    $property->setAccessible(true);
    $property->setValue($request, 'department_approval');
    
    // Submit
    $request->transition('submit', ['attachments' => ['file.pdf']]);
    expect($request->refresh()->status)->toBe('pending_hr');
    
    // Only HR reviewer can approve at this stage
    $this->actingAs($hrReviewer);
    $request->refresh();
    expect($request->canTransition('approve_hr'))->toBeTrue();
    $request->transition('approve_hr');
    expect($request->refresh()->status)->toBe('pending_finance');
    
    // Only Finance reviewer can approve at this stage
    $this->actingAs($financeReviewer);
    $request->refresh();
    expect($request->canTransition('approve_finance'))->toBeTrue();
    $request->transition('approve_finance');
    expect($request->refresh()->status)->toBe('pending_it');
    
    // Only IT reviewer can approve at this stage
    $this->actingAs($itReviewer);
    $request->refresh();
    expect($request->canTransition('approve_it'))->toBeTrue();
    $request->transition('approve_it');
    expect($request->refresh()->status)->toBe('approved');
    
    // Verify history
    $history = $request->transitionHistory();
    expect($history)->toHaveCount(4); // submit + 3 approvals
    
    $hrApproval = collect($history)->firstWhere('transition', 'approve_hr');
    expect($hrApproval['performed_by'])->toBe($hrReviewer->id);
    
    $financeApproval = collect($history)->firstWhere('transition', 'approve_finance');
    expect($financeApproval['performed_by'])->toBe($financeReviewer->id);
    
    $itApproval = collect($history)->firstWhere('transition', 'approve_it');
    expect($itApproval['performed_by'])->toBe($itReviewer->id);
});
