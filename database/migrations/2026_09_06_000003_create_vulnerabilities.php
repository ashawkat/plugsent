<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vulnerabilities', function (Blueprint $table): void {
            $table->id();
            $table->string('source')->default('wordfence');
            $table->string('external_id');
            $table->string('software_type'); // plugin|theme
            $table->string('software_slug');
            $table->string('title');
            $table->string('cve')->nullable();
            $table->decimal('cvss', 3, 1)->nullable();
            $table->boolean('patched')->nullable();
            $table->string('patched_version')->nullable();
            $table->string('affected_from')->nullable();
            $table->boolean('affected_from_inclusive')->default(true);
            $table->string('affected_to')->nullable();
            $table->boolean('affected_to_inclusive')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_id']);
            $table->index(['software_type', 'software_slug']);
        });

        Schema::table('inventory_items', function (Blueprint $table): void {
            // null = not yet checked against the feed, 0 = checked/clean.
            $table->unsignedInteger('vuln_count')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->dropColumn('vuln_count');
        });
        Schema::dropIfExists('vulnerabilities');
    }
};
