<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mood_checkins') || Schema::hasColumn('mood_checkins', 'notes')) {
            return;
        }

        Schema::table('mood_checkins', function (Blueprint $table) {
            $table->text('notes')->nullable();
        });

        if (Schema::hasColumn('mood_checkins', 'note')) {
            DB::table('mood_checkins')
                ->whereNotNull('note')
                ->update(['notes' => DB::raw('note')]);
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive: historical mood notes are preserved.
    }
};
