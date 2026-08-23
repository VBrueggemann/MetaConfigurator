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
        Schema::create('rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('view_id')->nullable()->constrained('views')->cascadeOnDelete();
            $table->string('component');
            $table->text('expression');
            $table->json('meta')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index('view_id');
        });
    }
};
