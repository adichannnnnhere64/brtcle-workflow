<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->json('definition');
            $table->integer('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('workflow_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_transition_id')->constrained()->onDelete('cascade');

            $table->string('approval_level');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at');
            $table->text('comments')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['workflow_transition_id', 'approval_level']);

            $table->index('approved_by');

            $table->foreign('approved_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflows');
		        Schema::dropIfExists('workflow_approvals');
    }
};
