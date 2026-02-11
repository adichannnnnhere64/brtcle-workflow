<?php

declare(strict_types=1);

namespace Adichan\WorkflowEngine;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

class WorkflowServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/workflow.php' => config_path('workflow.php'),
        ], 'workflow-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'workflow-migrations');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->registerGates();
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/workflow.php',
            'workflow'
        );
    }

    protected function registerGates(): void
    {
        // Only register gates if they haven't been registered already
        // This allows users to override them in their own AuthServiceProvider
        
        if (!Gate::has('workflow.can_submit')) {
            Gate::define('workflow.can_submit', function ($user) {
                $allowedRoles = config('workflow.guards.workflow.can_submit', ['employee']);
                return in_array($user->role, $allowedRoles);
            });
        }

        if (!Gate::has('workflow.can_approve')) {
            Gate::define('workflow.can_approve', function ($user) {
                $allowedRoles = config('workflow.guards.workflow.can_approve', ['reviewer', 'manager']);
                return in_array($user->role, $allowedRoles);
            });
        }

        if (!Gate::has('workflow.can_reject')) {
            Gate::define('workflow.can_reject', function ($user) {
                $allowedRoles = config('workflow.guards.workflow.can_reject', ['reviewer', 'manager']);
                return in_array($user->role, $allowedRoles);
            });
        }

        if (!Gate::has('workflow.can_request_revision')) {
            Gate::define('workflow.can_request_revision', function ($user) {
                $allowedRoles = config('workflow.guards.workflow.can_request_revision', ['reviewer', 'manager']);
                return in_array($user->role, $allowedRoles);
            });
        }

        if (!Gate::has('workflow.can_process_payment')) {
            Gate::define('workflow.can_process_payment', function ($user) {
                $allowedRoles = config('workflow.guards.workflow.can_process_payment', ['finance']);
                return in_array($user->role, $allowedRoles);
            });
        }

        if (!Gate::has('workflow.can_view')) {
            Gate::define('workflow.can_view', function ($user, $model) {
                // Default implementation - can be overridden
                return $user->id === $model->user_id ||
                       in_array($user->role, ['reviewer', 'finance', 'manager']);
            });
        }

        if (!Gate::has('workflow.can_edit')) {
            Gate::define('workflow.can_edit', function ($user, $model) {
                // Default implementation - can be overridden
                return $user->id === $model->user_id && $model->status === 'draft';
            });
        }
    }
}
