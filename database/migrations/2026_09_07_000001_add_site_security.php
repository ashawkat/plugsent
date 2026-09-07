<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            // Raw facts from the last security.scan command; checks and the
            // score are derived on the panel so logic can evolve freely.
            $table->json('security_facts')->nullable();
            $table->json('hardening')->nullable();
            $table->unsignedTinyInteger('security_score')->nullable();
            $table->timestamp('security_scanned_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->dropColumn(['security_facts', 'hardening', 'security_score', 'security_scanned_at']);
        });
    }
};
