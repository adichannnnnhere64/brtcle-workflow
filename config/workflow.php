<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Workflow
    |--------------------------------------------------------------------------
    |
    | The default workflow to use when none is specified
    |
    */
    'default_workflow' => env('WORKFLOW_DEFAULT', 'payment_request'),

    /*
    |--------------------------------------------------------------------------
    | Workflow Definitions
    |--------------------------------------------------------------------------
    |
    | Define all available workflows in your application
    |
    */
    'workflows' => [
        'payment_request' => [
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
        ],

        'leave_request' => [
            'state_column' => 'status',
            'states' => [
                ['value' => 'draft', 'label' => 'Draft', 'is_initial' => true],
                ['value' => 'pending', 'label' => 'Pending Approval'],
                ['value' => 'approved', 'label' => 'Approved', 'is_final' => true],
                ['value' => 'rejected', 'label' => 'Rejected', 'is_final' => true],
                ['value' => 'cancelled', 'label' => 'Cancelled', 'is_final' => true],
            ],
            'transitions' => [
                [
                    'name' => 'submit',
                    'from' => 'draft',
                    'to' => 'pending',
                    'guard' => 'workflow.can_submit',
                ],
                [
                    'name' => 'approve',
                    'from' => 'pending',
                    'to' => 'approved',
                    'guard' => 'workflow.can_approve',
                ],
                [
                    'name' => 'reject',
                    'from' => 'pending',
                    'to' => 'rejected',
                    'guard' => 'workflow.can_reject',
                ],
                [
                    'name' => 'cancel',
                    'from' => 'pending',
                    'to' => 'cancelled',
                    'guard' => 'workflow.cancel_request',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Guards
    |--------------------------------------------------------------------------
    |
    | Default gate definitions for workflow transitions
    |
    */
    'guards' => [
        'workflow.can_submit' => ['employee'],
        'workflow.can_approve' => ['reviewer', 'manager', 'director'],
        'workflow.can_reject' => ['reviewer', 'manager', 'director'],
        'workflow.can_request_revision' => ['reviewer', 'manager'],
        'workflow.can_process_payment' => ['finance'],
        'workflow.cancel_request' => ['employee', 'manager'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Listeners
    |--------------------------------------------------------------------------
    |
    | Register workflow event listeners
    |
    */
    'listeners' => [
        \Adichan\WorkflowEngine\Events\WorkflowTransitioned::class => [
            \Adichan\WorkflowEngine\Listeners\LogWorkflowTransition::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | History Settings
    |--------------------------------------------------------------------------
    |
    | Configure workflow history/audit trail
    |
    */
    'history' => [
        'enabled' => true,
        'table' => 'workflow_transitions',
        'keep_for_days' => 365,
    ],
];
