<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Post-update verification: when fresh inventory lands after an update
     * batch, each run is checked against what the site actually reports.
     * verified_at stamps the check; a failed check flips the run to
     * failed with an explanatory message.
     */
    public function up(): void
    {
        Schema::table('update_runs', function (Blueprint $table) {
            $table->timestamp('verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('update_runs', function (Blueprint $table) {
            $table->dropColumn('verified_at');
        });
    }
};
