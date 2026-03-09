<?php

namespace Adichan\WorkflowEngine\Traits;

use Adichan\WorkflowEngine\Models\WorkflowSignoff;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasSignoffs
{
    /**
     * Get the workflow name for signoffs.
     * Override this in your model if needed.
     */
    public function getSignoffWorkflowName(): string
    {
        return $this->workflowName ?? 'default';
    }

    /**
     * Get all signoffs for this model.
     */
    public function signoffs(): MorphMany
    {
        return $this->morphMany(WorkflowSignoff::class, 'model', 'model_type', 'model_id');
    }

    /**
     * Check if a user has signed off on this model.
     */
    public function hasSignoff(int $userId, string $signoffType): bool
    {
        return WorkflowSignoff::hasSignedOff(
            $this,
            $userId,
            $signoffType,
            $this->getSignoffWorkflowName()
        );
    }

    /**
     * Get all signoffs of a specific type.
     */
    public function getSignoffs(string $signoffType)
    {
        return WorkflowSignoff::getSignoffs(
            $this,
            $signoffType,
            $this->getSignoffWorkflowName()
        );
    }

    /**
     * Get signoff user IDs of a specific type.
     */
    public function getSignoffUserIds(string $signoffType): array
    {
        return WorkflowSignoff::getSignoffUserIds(
            $this,
            $signoffType,
            $this->getSignoffWorkflowName()
        );
    }

    /**
     * Record a signoff from a user.
     */
    public function addSignoff(
        int $userId,
        string $signoffType,
        ?string $comments = null,
        array $metadata = []
    ): WorkflowSignoff {
        return WorkflowSignoff::recordSignoff(
            $this,
            $userId,
            $signoffType,
            $this->getSignoffWorkflowName(),
            $comments,
            $metadata
        );
    }

    /**
     * Remove a signoff from a user.
     */
    public function removeSignoff(int $userId, string $signoffType): bool
    {
        return WorkflowSignoff::removeSignoff(
            $this,
            $userId,
            $signoffType,
            $this->getSignoffWorkflowName()
        );
    }

    /**
     * Clear all signoffs of a specific type.
     */
    public function clearSignoffs(string $signoffType): int
    {
        return WorkflowSignoff::clearSignoffs(
            $this,
            $signoffType,
            $this->getSignoffWorkflowName()
        );
    }

    /**
     * Check if all required signoffs are complete.
     *
     * @param string $signoffType The type of signoff to check
     * @param array $requiredUserIds User IDs that must have signed off
     * @return bool
     */
    public function hasAllRequiredSignoffs(string $signoffType, array $requiredUserIds): bool
    {
        if (empty($requiredUserIds)) {
            return true;
        }

        $signedUserIds = $this->getSignoffUserIds($signoffType);

        foreach ($requiredUserIds as $userId) {
            if (!in_array($userId, $signedUserIds)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get missing required signoffs.
     *
     * @param string $signoffType The type of signoff to check
     * @param array $requiredUserIds User IDs that must have signed off
     * @return array User IDs that haven't signed off yet
     */
    public function getMissingSignoffs(string $signoffType, array $requiredUserIds): array
    {
        if (empty($requiredUserIds)) {
            return [];
        }

        $signedUserIds = $this->getSignoffUserIds($signoffType);

        return array_values(array_diff($requiredUserIds, $signedUserIds));
    }

    /**
     * Get signoff count of a specific type.
     */
    public function getSignoffCount(string $signoffType): int
    {
        return count($this->getSignoffUserIds($signoffType));
    }
}
