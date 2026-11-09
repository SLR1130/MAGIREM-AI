<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Removes 40 empty tables that are no longer used.
     */
    public function up(): void
    {
        // Historical cleanup used a hard-coded list and could erase tables that
        // became active later (including wellbeing and privacy data). Cleanup is
        // now performed only by explicit, forward migrations after data review.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: up() is intentionally non-destructive.
    }
};
