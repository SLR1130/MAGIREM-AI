<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('system_settings')) {
            Schema::create('system_settings', function (Blueprint $table): void {
                $table->string('key', 64)->primary();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
