<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->unsignedInteger('sites_total')->default(0);
            $table->unsignedInteger('sites_connected')->default(0);
            $table->decimal('avg_security_score', 4, 1)->nullable();
            $table->unsignedInteger('updates_pending')->default(0);
            $table->unsignedInteger('vulns_open')->default(0);
            $table->unsignedInteger('vulns_critical')->default(0);
            $table->unsignedInteger('vulns_high')->default(0);
            $table->decimal('uptime_avg_pct', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_snapshots');
    }
};
