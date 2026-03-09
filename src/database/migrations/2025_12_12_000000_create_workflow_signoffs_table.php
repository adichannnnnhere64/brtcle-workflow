<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_signoffs', function (Blueprint $table) {
            $table->id();
            $table->string('workflow_type');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->string('signoff_type'); // e.g., 'approver', 'releaser', 'reviewer'
            $table->unsignedBigInteger('user_id');
            $table->timestamp('signed_at');
            $table->text('comments')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['model_type', 'model_id']);
            $table->index('workflow_type');
            $table->index(['signoff_type', 'user_id']);

            // Each user can only sign off once per model per signoff_type
            $table->unique(['model_type', 'model_id', 'signoff_type', 'user_id'], 'unique_signoff');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_signoffs');
    }
};
