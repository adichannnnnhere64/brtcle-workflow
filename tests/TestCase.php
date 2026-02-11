<?php

namespace Adichan\WorkflowEngine\Tests;

use Adichan\WorkflowEngine\WorkflowServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app)
    {
        return [WorkflowServiceProvider::class];
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/../src/database/migrations');
        $this->createUsersTable();
        $this->createPaymentRequestsTable();
    }

    protected function createUsersTable(): void
    {
        if (!\Schema::hasTable('users')) {
            \Schema::create('users', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('role');
                $table->string('department')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->string('email')->unique();
                $table->rememberToken();
                $table->timestamps();
            });
        }
    }

    protected function createPaymentRequestsTable(): void
    {
        if (!\Schema::hasTable('payment_requests')) {
            \Schema::create('payment_requests', function ($table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('title');
                $table->string('workflowName')->nullable();
                $table->text('description');
                $table->decimal('amount', 10, 2);
                $table->string('status')->default('draft');
                $table->json('attachments')->nullable();
                $table->dateTime('payment_due_date');
                $table->string('assigned_to')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->string('payment_confirmation')->nullable();
                $table->timestamps();
                
                $table->index('status');
                $table->index('user_id');
            });
        }
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup database
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        // Load our workflow config
        $app['config']->set('workflow', require __DIR__.'/../config/workflow.php');

        // Setup auth
        $app['config']->set('auth.defaults.guard', 'web');
        $app['config']->set('auth.guards.web', [
            'driver' => 'session',
            'provider' => 'users',
        ]);
        $app['config']->set('auth.providers.users', [
            'driver' => 'eloquent',
            'model' => \Adichan\WorkflowEngine\Tests\Mocks\Models\User::class,
        ]);

        // Setup basic gates for testing
        $app['config']->set('workflow.guards', [
            'workflow.can_submit' => ['employee'],
            'workflow.can_approve' => ['reviewer', 'manager'],
            'workflow.can_reject' => ['reviewer', 'manager'],
            'workflow.can_request_revision' => ['reviewer', 'manager'],
            'workflow.can_process_payment' => ['finance'],
        ]);

        // Setup logging
        $app['config']->set('logging.default', 'single');
        $app['config']->set('logging.channels.single', [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => 'debug',
        ]);
    }
}
