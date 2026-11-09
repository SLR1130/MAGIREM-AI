<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('video_calls', 'appointment_id')) {
            Schema::table('video_calls', function (Blueprint $table) {
                $table->unsignedBigInteger('appointment_id')->nullable()->after('id');
                $table->index(['appointment_id', 'status'], 'video_calls_appointment_status');
                $table->foreign('appointment_id')->references('id')->on('appointments')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('video_calls', 'appointment_id')) {
            Schema::table('video_calls', function (Blueprint $table) {
                $table->dropForeign(['appointment_id']);
                $table->dropIndex('video_calls_appointment_status');
                $table->dropColumn('appointment_id');
            });
        }
    }
};