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
        Schema::create('components', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('configurator_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->json('config');
            $table->timestamps();

            $table->unique(['configurator_id', 'type']);
        });
    }
};
