<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('answer_chunks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('configuration_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('anchor_view_id')->constrained('views')->cascadeOnDelete();
            $table->boolean('relevant')->default(true);
            $table->integer('sequence');
            $table->timestamps();

            $table->index(['configuration_id', 'sequence']);
            $table->index(['configuration_id', 'relevant']);
        });
    }
};
