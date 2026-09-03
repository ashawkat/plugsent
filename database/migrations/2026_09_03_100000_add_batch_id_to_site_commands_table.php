<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_commands', function (Blueprint $table) {
            $table->string('batch_id')->nullable()->after('site_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('site_commands', function (Blueprint $table) {
            $table->dropIndex(['batch_id']);
            $table->dropColumn('batch_id');
        });
    }
};
