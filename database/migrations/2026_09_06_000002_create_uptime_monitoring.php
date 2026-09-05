<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->boolean('uptime_enabled')->default(true);
            $table->string('uptime_status')->default('unknown'); // up|down|unknown
            $table->timestamp('uptime_last_checked_at')->nullable();
            $table->integer('uptime_last_status_code')->nullable();
            $table->integer('uptime_last_response_ms')->nullable();
            $table->string('uptime_last_error')->nullable();
            $table->unsignedInteger('uptime_consecutive_failures')->default(0);
        });

        Schema::create('uptime_incidents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('last_status_code')->nullable();
            $table->string('last_error')->nullable();
            $table->unsignedInteger('failure_count')->default(0);
            $table->boolean('down_notified')->default(false);
            $table->timestamps();

            $table->index(['site_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uptime_incidents');
        Schema::table('sites', function (Blueprint $table): void {
            $table->dropColumn([
                'uptime_enabled', 'uptime_status', 'uptime_last_checked_at',
                'uptime_last_status_code', 'uptime_last_response_ms',
                'uptime_last_error', 'uptime_consecutive_failures',
            ]);
        });
    }
};
