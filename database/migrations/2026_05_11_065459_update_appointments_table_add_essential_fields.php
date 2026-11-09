<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('reason')->nullable()->after('category');
            $table->text('rejection_reason')->nullable()->after('status');
            $table->text('session_summary')->nullable()->after('counselor_notes');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['reason', 'rejection_reason', 'session_summary']);
        });
    }
};
