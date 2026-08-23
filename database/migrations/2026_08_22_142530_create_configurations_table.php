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
        Schema::create('configurations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('configurator_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index('configurator_id');
        });
    }
};
