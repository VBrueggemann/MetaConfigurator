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
        Schema::create('views', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('configurator_id')->constrained()->cascadeOnDelete();
            $table->uuid('parent_id')->nullable()->index();
            $table->string('type');
            $table->unsignedInteger('order')->default(0);
            $table->json('config')->nullable();
            $table->timestamps();
        });

        Schema::table('views', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('views')->cascadeOnDelete();
            $table->index('configurator_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('views');
    }
};
