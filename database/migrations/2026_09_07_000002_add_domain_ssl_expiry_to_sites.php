<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->timestamp('ssl_expires_at')->nullable();
            $table->timestamp('domain_expires_at')->nullable();
            $table->timestamp('domain_checked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->dropColumn(['ssl_expires_at', 'domain_expires_at', 'domain_checked_at']);
        });
    }
};
