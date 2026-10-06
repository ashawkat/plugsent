<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-site update mode: safe (default) runs the full pipeline with a
     * restore point, smoke test, and automatic rollback; fast skips those
     * and applies the plain update — quicker, but nothing to roll back.
     */
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->boolean('fast_updates')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('fast_updates');
        });
    }
};
