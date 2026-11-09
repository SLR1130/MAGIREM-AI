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
        Schema::table('users', function (Blueprint $table) {
            // Counselor-specific fields
            $table->string('specialization')->nullable()->after('program');
            $table->integer('years_of_experience')->nullable()->after('specialization');
            $table->string('availability_schedule')->nullable()->after('years_of_experience');
            $table->string('status')->default('offline')->after('availability_schedule');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['specialization', 'years_of_experience', 'availability_schedule', 'status']);
        });
    }
};
