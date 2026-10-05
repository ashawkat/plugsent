<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The per-change "updates detected" emails were retired in favor of a
     * single daily digest, so the dedup fingerprint is no longer needed.
     */
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['updates_fingerprint', 'updates_notified_at']);
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('updates_fingerprint')->nullable();
            $table->timestamp('updates_notified_at')->nullable();
        });
    }
};
