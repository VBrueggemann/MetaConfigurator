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
        Schema::create('calculations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('configurator_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('expression');
            $table->timestamps();

            $table->unique(['configurator_id', 'name']);
        });
    }
};
