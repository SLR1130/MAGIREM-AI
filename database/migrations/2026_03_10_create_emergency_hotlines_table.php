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
        Schema::create('emergency_hotlines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone');
            $table->string('type'); // national, university, crisis, suicide, abuse, etc
            $table->text('description');
            $table->string('availability')->default('24/7'); // 24/7, business hours, etc
            $table->string('country')->default('Global');
            $table->integer('priority')->default(0); // for sorting
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emergency_hotlines');
    }
};
