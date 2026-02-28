<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('source_url')->nullable()->index();
            $table->foreignId('staged_content_id')->nullable()->constrained('staged_contents')->nullOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending')->index(); // pending, running, completed, failed, rejected
            $table->unsignedTinyInteger('current_stage_index')->default(0);
            $table->string('current_stage_name')->nullable();
            $table->json('stages')->nullable();          // ordered list of stage class names for this run
            $table->json('payload_snapshot')->nullable(); // serialized ContentPayload at last successful checkpoint
            $table->text('error_message')->nullable();
            $table->string('error_stage')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_runs');
    }
};
