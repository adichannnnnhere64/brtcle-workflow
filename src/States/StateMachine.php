<?php

declare(strict_types=1);

namespace Adichan\WorkflowEngine\States;

use Adichan\WorkflowEngine\Contracts\WorkflowInterface;
use Adichan\WorkflowEngine\Contracts\WorkflowTransitionInterface;
use Adichan\WorkflowEngine\Enums\WorkflowGuard;
use Adichan\WorkflowEngine\Events\WorkflowTransitioned;
use Adichan\WorkflowEngine\Exceptions\RequirementValidationException;
use Adichan\WorkflowEngine\Models\WorkflowApproval;
use Adichan\WorkflowEngine\Models\WorkflowTransition;
use Adichan\WorkflowEngine\Requirements\RequirementValidator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class StateMachine implements WorkflowInterface
{
    private array $states = [];

    private array $pendingApprovals = [];

    protected array $approvalLevels = [];

    public function __construct(
        protected string $workflowName,
        protected array $definition
    ) {
        $this->initializeStates();
        $this->initializeApprovalLevels();
    }

    private function initializeApprovalLevels(): void
    {
        $this->approvalLevels = $this->definition['approval_levels'] ?? [];
    }

    private function initializeStates(): void
    {
        // FIX: Check if states key exists
        if (! isset($this->definition['states'])) {
            throw new \InvalidArgumentException("Workflow definition must contain 'states' key");
        }

        foreach ($this->definition['states'] as $stateConfig) {
            $state = new class($stateConfig['value'], $stateConfig['label'], $stateConfig['is_initial'] ?? false, $stateConfig['is_final'] ?? false) extends AbstractWorkflowState {};

            $this->states[$stateConfig['value']] = $state;
        }

        // FIX: Check if transitions key exists
        if (! isset($this->definition['transitions'])) {
            throw new \InvalidArgumentException("Workflow definition must contain 'transitions' key");
        }

        foreach ($this->definition['transitions'] as $transition) {
            $fromState = $this->states[$transition['from']] ?? null;

            if (! $fromState) {
                throw new \InvalidArgumentException(
                    "Transition '{$transition['name']}' references undefined state: {$transition['from']}"
                );
            }

            $fromState->addTransition(
                $transition['to'],
                $transition['name'],
                WorkflowGuard::from($transition['guard']),
                $transition['validator'] ?? null
            );
        }
    }

    /**
     * Get requirements configuration for a specific transition
     *
     * @return array<array>
     */
    public function getTransitionRequirements(string $transitionName): array
    {
        foreach ($this->definition['transitions'] as $transition) {
            if ($transition['name'] === $transitionName) {
                return $transition['requirements'] ?? [];
            }
        }

        return [];
    }

    /**
     * Get a RequirementValidator for a specific transition
     */
    public function getRequirementValidator(string $transitionName): RequirementValidator
    {
        $requirements = $this->getTransitionRequirements($transitionName);

        return RequirementValidator::fromConfig($requirements);
    }

    /**
     * Validate context against transition requirements
     *
     * @return array{valid: bool, errors: array<string, array<string>>}
     */
    public function validateRequirements(string $transitionName, array $context): array
    {
        $validator = $this->getRequirementValidator($transitionName);

        return $validator->validate($context);
    }

    /**
     * Check if transition has any requirements
     */
    public function hasRequirements(string $transitionName): bool
    {
        return ! empty($this->getTransitionRequirements($transitionName));
    }

    public function getAvailableTransitions(Model $model): array
    {
        $stateColumn = $this->definition['state_column'] ?? 'status';
        $currentStateValue = $model->{$stateColumn} ?? 'draft';
        $currentState = $this->states[$currentStateValue] ?? null;

        if (! $currentState) {
            return [];
        }

        $available = [];

        foreach ($currentState->allowedTransitions() as $transitionName => $config) {
            if ($this->can($model, $transitionName)) {
                $requirements = $this->getTransitionRequirements($transitionName);
                $validator = RequirementValidator::fromConfig($requirements);

                $available[$transitionName] = [
                    'to' => $config['to'],
                    'guard' => $config['guard'],
                    'requirements' => $validator->toArray(),
                    'has_requirements' => $validator->hasRequirements(),
                    'has_required' => $validator->hasRequired(),
                ];
            }
        }

        return $available;
    }

    public function apply(
        Model $model,
        string $transition,
        array $context = []
    ): WorkflowTransitionInterface {
        if (! $this->can($model, $transition, $context)) {
            throw new \DomainException(
                "Transition '{$transition}' is not allowed from current state"
            );
        }

        // Validate requirements before applying
        if ($this->hasRequirements($transition)) {
            $validator = $this->getRequirementValidator($transition);
            $validator->validateOrFail($context);
        }

        $stateColumn = $this->definition['state_column'] ?? 'status';
        $currentState = $model->{$stateColumn};
        $state = $this->states[$currentState] ?? null;

        if (! $state) {
            throw new \DomainException("Invalid current state: {$currentState}");
        }

        $config = $state->allowedTransitions()[$transition] ?? null;

        if (! $config) {
            throw new \DomainException("Transition '{$transition}' not defined");
        }

        // FIX: Execute BEFORE hooks BEFORE checking if transition can be applied
        $this->executeBeforeHooks($transition, $model, $currentState, $config['to'], $context);

        return DB::transaction(function () use ($model, $transition, $config, $currentState, $context, $stateColumn) {
            $previousState = $currentState;
            $newState = $config['to'];

            // Update model state
            $model->{$stateColumn} = $newState;
            $model->save();

            // Create transition record
            $transitionRecord = WorkflowTransition::create([
                'workflow_type' => $this->workflowName,
                'model_type' => get_class($model),
                'model_id' => $model->id,
                'transition' => $transition,
                'from_state' => $previousState,
                'to_state' => $newState,
                'context' => $context,
                'performed_by' => auth()->id(),
                'performed_at' => now(),
                'metadata' => $context['metadata'] ?? [],
                'performed_at' => now(),
            ]);

            // Execute AFTER hooks
            $this->executeAfterHooks($transition, $model, $previousState, $newState, $context);

            // Dispatch event
            event(new WorkflowTransitioned(
                $model,
                $transition,
                $previousState,
                $newState,
                $context,
                now()
            ));

            return $transitionRecord;
        });
    }

    // src/States/StateMachine.php (update the can() method)
    public function can(
        Model $model,
        string $transition,
        array $context = [],
        bool $validateRequirements = false
    ): bool {
        $stateColumn = $this->definition['state_column'] ?? 'status';
        $currentState = $model->{$stateColumn};
        $state = $this->states[$currentState] ?? null;

        if (! $state) {
            return false;
        }

        $config = $state->allowedTransitions()[$transition] ?? null;

        if (! $config) {
            return false;
        }

        // Check guard
        $guardValue = $config['guard'] instanceof WorkflowGuard
            ? $config['guard']->value
            : $config['guard'];

        if (! Gate::allows($guardValue, $model)) {
            return false;
        }

        // Check validator
        if (isset($config['validator']) && is_callable($config['validator'])) {
            try {
                if (! $config['validator']($context)) {
                    return false;
                }
            } catch (\Exception $e) {
                return false;
            }
        }

        // Check custom conditions from definition
        if (isset($this->definition['conditions'][$transition])) {
            $condition = $this->definition['conditions'][$transition];
            if (is_callable($condition)) {
                try {
                    if (! $condition($model, $context)) {
                        return false;
                    }
                } catch (\Exception $e) {
                    return false;
                }
            }
        }

        // Optionally validate requirements
        if ($validateRequirements && $this->hasRequirements($transition)) {
            $validation = $this->validateRequirements($transition, $context);
            if (! $validation['valid']) {
                return false;
            }
        }

        return true;
    }

    public function getState(Model $model): string
    {
        $stateColumn = $this->definition['state_column'] ?? 'status';

        return $model->{$stateColumn};
    }

    public function getHistory(Model $model): array
    {
        return WorkflowTransition::where('model_type', get_class($model))
            ->where('model_id', $model->id)
            ->where('workflow_type', $this->workflowName)
            ->orderBy('created_at', 'asc')
            ->get()
            ->toArray();
    }

    public function getDefinition(): array
    {
        return $this->definition;
    }

    private function executeBeforeHooks(
        string $transition,
        Model $model,
        string $from,
        string $to,
        array $context
    ): void {
        // Execute before hooks
        if (isset($this->definition['hooks']['before'][$transition])) {
            $this->definition['hooks']['before'][$transition]($model, $from, $to, $context);
        }
    }

    private function executeAfterHooks(
        string $transition,
        Model $model,
        string $from,
        string $to,
        array $context
    ): void {
        // Execute after hooks
        if (isset($this->definition['hooks']['after'][$transition])) {
            $this->definition['hooks']['after'][$transition]($model, $from, $to, $context);
        }
    }

    // src/States/StateMachine.php

    public function getPendingApprovals(Model $model): array
    {
        if (empty($this->approvalLevels)) {
            return [];
        }

        $stateColumn = $this->definition['state_column'] ?? 'status';
        $currentState = $model->{$stateColumn};
        $pending = [];

        foreach ($this->approvalLevels as $levelName => $config) {
            // Check if this approval level is relevant for current state
            if (isset($config['state']) && $config['state'] !== $currentState) {
                continue;
            }

            // Check if approval is already completed
            if ($this->isApprovalCompleted($model, $levelName)) {
                continue;
            }

            // Check if approval is required (based on conditions)
            if (! $this->isApprovalRequired($model, $levelName)) {
                continue;
            }

            $pending[$levelName] = [
                'level' => $levelName,
                'label' => $config['label'] ?? ucfirst(str_replace('_', ' ', $levelName)),
                'required_role' => $config['required_role'],
                'assigned_to' => $this->getAssignedApprover($model, $levelName),
                'can_approve' => auth()->check() ? $this->canUserApproveLevel(auth()->user(), $levelName, $model) : false,
                'config' => $config,
            ];
        }

        return $pending;
    }

    public function canUserApproveLevel($user, string $levelName, Model $model): bool
    {
        $config = $this->approvalLevels[$levelName] ?? null;

        if (! $config) {
            return false;
        }

        // Check role requirement - with null safety
        if (! $user) {
            return false;
        }

        $requiredRole = $config['required_role'] ?? null;
        if (! $requiredRole) {
            return false;
        }

        if (! $this->userHasRole($user, $requiredRole)) {
            return false;
        }

        // Check assignment
        if (isset($config['assign_to_field'])) {
            $assignedTo = $model->{$config['assign_to_field']};
            if ($assignedTo && (is_numeric($assignedTo) ? (int) $assignedTo !== (int) $user->id : $assignedTo !== $user->id)) {
                return false;
            }
        }

        // Check amount threshold
        if (isset($config['amount_threshold']) && isset($model->amount)) {
            if ($model->amount < $config['amount_threshold']) {
                return false;
            }
        }

        // Check custom condition
        if (isset($config['condition']) && is_callable($config['condition'])) {
            try {
                if (! $config['condition']($model, $user)) {
                    return false;
                }
            } catch (\Exception $e) {
                return false;
            }
        }

        // Check prerequisites
        if (! $this->arePrerequisitesMet($model, $levelName)) {
            return false;
        }

        return true;
    }

    // src/States/StateMachine.php

    // src/States/StateMachine.php - Fix applyWithApproval method

    public function applyWithApproval(
        Model $model,
        ?string $transition,
        string $approvalLevel,
        array $context = []
    ): WorkflowTransitionInterface {
        // FIRST: Check if approval is already completed
        // This should be the VERY FIRST check, before anything else
        if ($this->isApprovalCompleted($model, $approvalLevel)) {
            throw new \DomainException("Approval level '{$approvalLevel}' has already been completed");
        }

        // SECOND: Check if transition exists and is available from current state
        $stateColumn = $this->definition['state_column'] ?? 'status';
        $currentState = $model->{$stateColumn};
        $state = $this->states[$currentState] ?? null;

        if (! $state) {
            throw new \DomainException("Invalid current state: {$currentState}");
        }

        // If transition is provided, check if it exists from current state
        if ($transition) {
            $config = $state->allowedTransitions()[$transition] ?? null;

            if (! $config) {
                throw new \DomainException("Transition '{$transition}' not defined from state '{$currentState}'");
            }
        }

        // THEN check authentication
        if (! auth()->check()) {
            throw new \DomainException('User must be authenticated to approve');
        }

        // THEN check if approval level exists
        $levelConfig = $this->approvalLevels[$approvalLevel] ?? null;
        if (! $levelConfig) {
            throw new \DomainException("Approval level '{$approvalLevel}' not defined");
        }

        // THEN check if user can approve at this level
        if (! $this->canUserApproveLevel(auth()->user(), $approvalLevel, $model)) {
            throw new \DomainException("User cannot approve at level: {$approvalLevel}");
        }

        // THEN check if all prerequisites are met
        if (! $this->arePrerequisitesMet($model, $approvalLevel)) {
            $prereqs = $this->getUnmetPrerequisites($model, $approvalLevel);
            throw new \DomainException("Prerequisites not met for level '{$approvalLevel}': ".implode(', ', $prereqs));
        }

        return DB::transaction(function () use ($model, $transition, $approvalLevel, $context, $stateColumn, $currentState) {
            // Apply the transition if provided
            $transitionRecord = null;
            $newState = $currentState;

            if ($transition) {
                $state = $this->states[$currentState] ?? null;
                $config = $state->allowedTransitions()[$transition] ?? null;
                $newState = $config['to'];

                // Execute BEFORE hooks
                $this->executeBeforeHooks($transition, $model, $currentState, $newState, $context);

                // Update model state
                $model->{$stateColumn} = $newState;
                $model->save();

                // Create transition record
                $transitionRecord = WorkflowTransition::create([
                    'workflow_type' => $this->workflowName,
                    'model_type' => get_class($model),
                    'model_id' => $model->id,
                    'transition' => $transition,
                    'from_state' => $currentState,
                    'to_state' => $newState,
                    'context' => $context,
                    'performed_by' => auth()->id(),
                    'metadata' => $context['metadata'] ?? [],
                    'performed_at' => now(),
                ]);
            }

            // Record the approval
            $approvalRecord = WorkflowApproval::create([
                'workflow_transition_id' => $transitionRecord?->id,
                'approval_level' => $approvalLevel,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'comments' => $context['comments'] ?? null,
                'metadata' => $context['metadata'] ?? [],
            ]);

            // Execute AFTER hooks
            if ($transitionRecord) {
                $this->executeAfterHooks($transition, $model, $currentState, $newState, $context);
            }

            // Dispatch event
            if ($transitionRecord) {
                event(new WorkflowTransitioned(
                    $model,
                    $transition,
                    $currentState,
                    $newState,
                    array_merge($context, ['approval_level' => $approvalLevel])
                ));
            }

            return $transitionRecord ?? $approvalRecord;
        });
    }

    public function isApprovalCompleted(Model $model, string $levelName): bool
    {
        // Check if this approval level was already approved
        return WorkflowApproval::whereHas('transition', function ($query) use ($model) {
            $query->where('model_type', get_class($model))
                ->where('model_id', $model->id)
                ->where('workflow_type', $this->workflowName);
        })
            ->where('approval_level', $levelName)

            ->exists();
    }

    // src/States/StateMachine.php

    public function isApprovalRequired(Model $model, string $levelName): bool
    {
        $config = $this->approvalLevels[$levelName] ?? null;

        if (! $config) {
            return false;
        }

        // Check if approval can be skipped
        if (isset($config['can_skip']) && $config['can_skip'] === true) {
            if (isset($config['skip_condition']) && is_callable($config['skip_condition'])) {
                return ! $config['skip_condition']($model);
            }
        }

        // Check amount threshold
        if (isset($config['amount_threshold']) && isset($model->amount)) {
            if ($model->amount < $config['amount_threshold']) {
                return false;
            }
        }

        return true;
    }

    public function arePrerequisitesMet(Model $model, string $levelName): bool
    {
        $config = $this->approvalLevels[$levelName] ?? null;

        if (! isset($config['prerequisites'])) {
            return true;
        }

        foreach ($config['prerequisites'] as $prerequisite) {
            if (! $this->isApprovalCompleted($model, $prerequisite)) {
                return false;
            }
        }

        return true;
    }

    public function getUnmetPrerequisites(Model $model, string $levelName): array
    {
        $config = $this->approvalLevels[$levelName] ?? null;

        $unmet = [];

        if (! isset($config['prerequisites'])) {
            return $unmet;
        }

        foreach ($config['prerequisites'] as $prerequisite) {
            if (! $this->isApprovalCompleted($model, $prerequisite)) {
                $unmet[] = $prerequisite;
            }
        }

        return $unmet;

    }

    public function areAllApprovalsComplete(Model $model, ?string $targetState = null): bool
    {
        $stateColumn = $this->definition['state_column'] ?? 'status';
        $currentState = $model->{$stateColumn};

        // If target state is provided, check approvals for that state
        $checkState = $targetState ?? $currentState;

        foreach ($this->approvalLevels as $levelName => $config) {
            // Only check approvals that are required for this state
            if (isset($config['state']) && $config['state'] !== $checkState) {
                continue;
            }

            // Skip if approval is not required
            if (! $this->isApprovalRequired($model, $levelName)) {
                continue;
            }

            if (! $this->isApprovalCompleted($model, $levelName)) {
                return false;
            }
        }

        return true;
    }

    public function getAssignedApprover(Model $model, string $levelName): ?array
    {
        $config = $this->approvalLevels[$levelName] ?? null;

        if (! $config) {
            return null;
        }

        // If explicitly assigned via field
        if (isset($config['assign_to_field'])) {
            $assignedId = $model->{$config['assign_to_field']};

            if ($assignedId) {
                $userModel = config('auth.providers.users.model');
                $user = $userModel::find($assignedId);
                if ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ];
                }
            }

        }

        // Auto-assign based on role and business rules
        if (isset($config['auto_assign']) && $config['auto_assign'] === true) {
            return $this->findAutoAssignApprover($model, $levelName);
        }

        return null;
    }

    protected function findAutoAssignApprover(Model $model, string $levelName): ?array
    {
        $config = $this->approvalLevels[$levelName] ?? null;

        if (! $config || ! isset($config['required_role'])) {
            return null;
        }

        $userModel = config('auth.providers.users.model');

        // Simple auto-assign: find first user with required role
        // This can be enhanced based on your business logic
        $approver = $userModel::where('role', $config['required_role'])->first();

        if ($approver) {
            return [
                'id' => $approver->id,
                'name' => $approver->name,
                'email' => $approver->email,
            ];
        }

        return null;
    }

    protected function userHasRole($user, string $role): bool
    {
        if (method_exists($user, 'hasRole')) {
            return $user->hasRole($role);
        }

        // Fallback to role property or attribute
        return $user->role === $role || $user->getAttribute('role') === $role;
    }

    // src/States/StateMachine.php

    public function determineNextState(Model $model, string $transition, string $approvalLevel): string
    {
        $state = $this->states[$model->{$this->definition['state_column'] ?? 'status'}] ?? null;

        if (! $state) {
            throw new \DomainException('Invalid current state');
        }

        $config = $state->allowedTransitions()[$transition] ?? null;

        if (! $config) {
            throw new \DomainException("Transition '{$transition}' not defined");
        }

        $defaultNextState = $config['to'];

        // Check if we should skip any approval levels
        $nextState = $defaultNextState;
        $currentCheckState = $defaultNextState;
        $maxSkipLevels = 3; // Prevent infinite loops
        $skipCount = 0;

        while ($skipCount < $maxSkipLevels) {
            $approvalsForNextState = array_filter($this->approvalLevels,
                fn ($level) => ($level['state'] ?? null) === $currentCheckState
            );

            $allSkippable = true;
            foreach ($approvalsForNextState as $levelName => $levelConfig) {
                if (! $this->isApprovalRequired($model, $levelName)) {
                    // This approval can be skipped, find the transition out of this state
                    $nextStateObj = $this->states[$currentCheckState] ?? null;
                    if ($nextStateObj) {
                        $transitions = $nextStateObj->allowedTransitions();
                        if (! empty($transitions)) {
                            // Use the first available transition
                            $firstTransition = array_key_first($transitions);
                            $currentCheckState = $transitions[$firstTransition]['to'];

                            continue 2; // Continue checking the new state
                        }
                    }
                } else {
                    $allSkippable = false;
                    break;
                }
            }

            if ($allSkippable && ! empty($approvalsForNextState)) {
                // All approvals for this state are skippable, move to next state
                $nextStateObj = $this->states[$currentCheckState] ?? null;
                if ($nextStateObj) {
                    $transitions = $nextStateObj->allowedTransitions();
                    if (! empty($transitions)) {
                        $firstTransition = array_key_first($transitions);
                        $currentCheckState = $transitions[$firstTransition]['to'];
                        $skipCount++;

                        continue;
                    }
                }
            }

            break;
        }

        return $currentCheckState;
    }

    public function isOverdue(Model $model, string $state, int $slaHours = 24): bool
    {
        $enteredStateAt = WorkflowTransition::where('model_type', get_class($model))
            ->where('model_id', $model->id)
            ->where('to_state', $state)
            ->orderBy('performed_at', 'desc')
            ->value('performed_at');

        if (! $enteredStateAt) {
            return false;
        }

        return now()->diffInHours($enteredStateAt) > $slaHours;
    }

    public function getTransitionStatistics(array $options = []): array
    {
        $query = WorkflowTransition::where('workflow_type', $this->workflowName);

        if (isset($options['from'])) {
            $query->where('performed_at', '>=', $options['from']);
        }

        if (isset($options['to'])) {
            $query->where('performed_at', '<=', $options['to']);
        }

        return [
            'total_transitions' => $query->count(),
            'by_transition' => $query->groupBy('transition')
                ->selectRaw('transition, count(*) as count')
                ->pluck('count', 'transition')
                ->toArray(),
            'average_duration' => $query->selectRaw('AVG(TIMESTAMPDIFF(SECOND, performed_at, created_at)) as avg')
                ->value('avg'),
            'peak_hours' => $query->selectRaw('HOUR(performed_at) as hour, count(*) as count')
                ->groupBy('hour')
                ->orderBy('count', 'desc')
                ->first(),
        ];
    }
}
