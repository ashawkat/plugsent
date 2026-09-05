<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('update_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('context'); // plugin|theme
            $table->string('slug');
            $table->foreignId('command_id')->nullable()->constrained('site_commands')->nullOnDelete();
            $table->string('from_version')->nullable();
            $table->string('to_version')->nullable();
            $table->string('status'); // updated|rolled_back|failed
            $table->text('message')->nullable();
            $table->boolean('smoke_ok')->nullable();
            $table->integer('smoke_status_code')->nullable();
            $table->boolean('db_backup')->default(false);
            $table->boolean('files_backup')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('update_runs');
    }
};
