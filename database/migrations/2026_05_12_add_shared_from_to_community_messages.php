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
        Schema::table('community_messages', function (Blueprint $table) {
            $table->foreignId('shared_from_id')->nullable()->constrained('community_messages')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('community_messages', function (Blueprint $table) {
            // Drop the foreign key constraint first using the constraint name
            $table->dropForeign('community_messages_shared_from_id_foreign');
            // Then drop the column
            $table->dropColumn('shared_from_id');
        });
    }
};
