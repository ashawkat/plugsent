<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where a command came from: the dashboard actions leave it null,
     * MCP-issued commands carry "mcp" plus a human-readable actor
     * ("user@example.com · Claude Desktop") for the audit trail.
     */
    public function up(): void
    {
        Schema::table('site_commands', function (Blueprint $table) {
            $table->string('source')->nullable();
            $table->string('actor')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_commands', function (Blueprint $table) {
            $table->dropColumn(['source', 'actor']);
        });
    }
};
