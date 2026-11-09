<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('emotional_entries', function (Blueprint $table) {
            $table->string('crisis_level')->default('none')->after('confidence_score');
            $table->boolean('needs_counselor')->default(false)->after('crisis_level');
            $table->text('detected_contexts')->nullable()->after('needs_counselor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emotional_entries', function (Blueprint $table) {
            $table->dropColumn(['crisis_level', 'needs_counselor', 'detected_contexts']);
        });
    }
};
