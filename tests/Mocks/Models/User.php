<?php

namespace Adichan\WorkflowEngine\Tests\Mocks\Models;

use Adichan\WorkflowEngine\Tests\Mocks\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Auth\User as AuthUser;

class User extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'role',
        'department',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function can($ability, $arguments = [])
    {
		/* ModelsUser */
        // Simple role-based permission check for testing
        $permissions = [
            'workflow.can_submit' => ['employee', 'manager'],
            'workflow.can_approve' => ['reviewer', 'manager', 'director'],
            'workflow.can_reject' => ['reviewer', 'manager', 'director'],
            'workflow.can_request_revision' => ['reviewer', 'manager'],
            'workflow.can_process_payment' => ['finance'],
        ];

        return in_array($this->role, $permissions[$ability] ?? []);
    }

    protected static function newFactory(): Factory
    {
        return UserFactory::new();
    }
}
