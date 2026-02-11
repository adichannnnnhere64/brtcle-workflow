<?php

declare(strict_types=1);

namespace Adichan\WorkflowEngine\Enums;

enum WorkflowGuard: string
{
    case CAN_SUBMIT = 'workflow.can_submit';
    case CAN_APPROVE = 'workflow.can_approve';
    case CAN_REJECT = 'workflow.can_reject';
    case CAN_REQUEST_REVISION = 'workflow.can_request_revision';
    case CAN_PROCESS_PAYMENT = 'workflow.can_process_payment';
    case CAN_VIEW = 'workflow.can_view';
    case CAN_EDIT = 'workflow.can_edit';

    public function description(): string
    {
        return match ($this) {
            self::CAN_SUBMIT => 'Can submit for review',
            self::CAN_APPROVE => 'Can approve requests',
            self::CAN_REJECT => 'Can reject requests',
            self::CAN_REQUEST_REVISION => 'Can request revisions',
            self::CAN_PROCESS_PAYMENT => 'Can process payments',
            self::CAN_VIEW => 'Can view',
            self::CAN_EDIT => 'Can edit',
        };
    }
}
