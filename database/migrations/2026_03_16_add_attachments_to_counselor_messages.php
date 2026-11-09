<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('counselor_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('counselor_messages', 'attachment_path')) {
                $table->string('attachment_path')->nullable()->after('message');
            }
            if (!Schema::hasColumn('counselor_messages', 'attachment_name')) {
                $table->string('attachment_name')->nullable()->after('attachment_path');
            }
            if (!Schema::hasColumn('counselor_messages', 'attachment_type')) {
                $table->string('attachment_type')->nullable()->after('attachment_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('counselor_messages', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'attachment_name', 'attachment_type']);
        });
    }
};
