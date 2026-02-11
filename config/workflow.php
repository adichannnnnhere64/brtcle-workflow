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

        'complex_approval' => [
            'state_column' => 'status',
            'states' => [
                ['value' => 'draft', 'label' => 'Draft', 'is_initial' => true],
                ['value' => 'awaiting_manager', 'label' => 'Awaiting Manager Approval'],
                ['value' => 'awaiting_director', 'label' => 'Awaiting Director Approval'],
                ['value' => 'awaiting_finance', 'label' => 'Awaiting Finance Processing'],
                ['value' => 'approved', 'label' => 'Approved', 'is_final' => false],
                ['value' => 'rejected', 'label' => 'Rejected', 'is_final' => true],
                ['value' => 'completed', 'label' => 'Completed', 'is_final' => true],

            ],
            'transitions' => [
                [
                    'name' => 'submit_for_manager',

                    'from' => 'draft',
                    'to' => 'awaiting_manager',
                    'guard' => 'workflow.can_submit',

                ],
                [
                    'name' => 'manager_approve',
                    'from' => 'awaiting_manager',
                    'to' => 'awaiting_director',
                    'guard' => 'workflow.can_approve',
                ],
                [
                    'name' => 'director_approve',
                    'from' => 'awaiting_director',
                    'to' => 'awaiting_finance',
                    'guard' => 'workflow.can_approve',
                    'condition' => fn ($model) => $model->amount > 5000,
                ],
                [
                    'name' => 'finance_process',
                    'from' => 'awaiting_finance',
                    'to' => 'completed',
                    'guard' => 'workflow.can_process_payment',
                ],
            ],
            'approval_levels' => [
                'manager_approval' => [

                    'required_role' => 'manager',
                    'state' => 'awaiting_manager',
                    'assign_to_field' => 'manager_id', // Model field with assigned manager

                    'can_delegate' => true,

                ],
                'director_approval' => [
                    'required_role' => 'director',
                    'state' => 'awaiting_director',
                    'amount_threshold' => 5000, // Only required for amounts > $5k
                    'auto_assign_to' => 'department.director_id', // Relationship path
                ],
                'finance_processing' => [
                    'required_role' => 'finance',
                    'state' => 'awaiting_finance',
                    'prerequisites' => ['manager_approval', 'director_approval'],
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
