<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('update_exclusions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('context'); // plugin|theme
            $table->string('slug');
            $table->timestamps();

            $table->unique(['site_id', 'context', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('update_exclusions');
    }
};
