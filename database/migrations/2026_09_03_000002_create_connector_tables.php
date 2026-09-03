<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pairing_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash')->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('site_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('site_key')->unique();
            $table->text('secret'); // encrypted at rest
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('site_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->json('payload')->nullable();
            $table->string('status')->default('pending');
            $table->json('result')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'status']);
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('context'); // core | plugin | theme
            $table->string('slug');
            $table->string('name');
            $table->string('version')->nullable();
            $table->boolean('update_available')->default(false);
            $table->string('update_version')->nullable();
            $table->boolean('active')->default(false);
            $table->timestamps();

            $table->unique(['site_id', 'context', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('site_commands');
        Schema::dropIfExists('site_credentials');
        Schema::dropIfExists('pairing_codes');
    }
};
