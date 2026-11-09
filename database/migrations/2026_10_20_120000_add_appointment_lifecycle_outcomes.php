<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('appointments', 'session_outcome')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->string('session_outcome', 32)->nullable()->after('status');
                $table->text('outcome_reason')->nullable()->after('session_outcome');
                $table->string('attendance_evidence', 40)->nullable()->after('outcome_reason');
                $table->timestamp('outcome_verified_at')->nullable()->after('attendance_evidence');
                $table->unsignedBigInteger('outcome_verified_by')->nullable()->after('outcome_verified_at');
                $table->timestamp('session_started_at')->nullable()->after('outcome_verified_by');
                $table->timestamp('session_ended_at')->nullable()->after('session_started_at');
                $table->index(['counselor_id', 'status', 'session_outcome', 'appointment_date'], 'appointments_lifecycle_lookup');
                $table->foreign('outcome_verified_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('appointment_status_histories')) {
            Schema::create('appointment_status_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
                $table->string('from_status', 24)->nullable();
                $table->string('to_status', 24)->nullable();
                $table->string('from_outcome', 32)->nullable();
                $table->string('to_outcome', 32)->nullable();
                $table->text('reason')->nullable();
                $table->string('attendance_evidence', 40)->nullable();
                $table->unsignedBigInteger('changed_by')->nullable();
                $table->timestamps();

                $table->index(['appointment_id', 'created_at'], 'appointment_history_order');
                $table->foreign('changed_by')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('appointment_status_histories');
        if (Schema::hasColumn('appointments', 'session_outcome')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->dropForeign(['outcome_verified_by']);
                $table->dropIndex('appointments_lifecycle_lookup');
                $table->dropColumn([
                    'session_outcome', 'outcome_reason', 'attendance_evidence',
                    'outcome_verified_at', 'outcome_verified_by', 'session_started_at', 'session_ended_at',
                ]);
            });
        }
        Schema::enableForeignKeyConstraints();
    }
};