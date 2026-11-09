<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('community_report_audits')) {
            Schema::create('community_report_audits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('report_id')->constrained('community_reports')->cascadeOnDelete();
                $table->foreignId('moderator_id')->constrained('users')->cascadeOnDelete();
                $table->string('from_status', 16)->nullable();
                $table->string('to_status', 16);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['report_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('community_report_audits');
    }
};
