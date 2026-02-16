<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_transitions', function (Blueprint $table) {
            $table->id();
            $table->string('workflow_type');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->string('transition');
            $table->string('from_state');
            $table->string('to_state');
            $table->json('context')->nullable();
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->timestamp('performed_at')->nullable(); 
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['model_type', 'model_id']);
            $table->index('workflow_type');
            $table->index('performed_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_transitions');
    }
};
