<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('configuration_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('view_id')->constrained('views')->cascadeOnDelete();
            $table->foreignUuid('answer_chunk_id')->constrained('answer_chunks')->cascadeOnDelete();
            $table->jsonb('value')->nullable();
            $table->timestamps();

            $table->index('view_id');
        });
    }
};
