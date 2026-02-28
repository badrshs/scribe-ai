<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staged_contents', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('url');
            $table->date('published_date')->nullable();
            $table->string('category')->nullable();
            $table->string('source_name')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->boolean('approved')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->boolean('published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('url');
            $table->index(['approved', 'published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staged_contents');
    }
};
