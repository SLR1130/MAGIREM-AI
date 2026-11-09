
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('wellbeing_progress')) {
            return;
        }

        $indexExists = collect(Schema::getIndexes('wellbeing_progress'))
            ->contains(fn (array $index) => ($index['name'] ?? null) === 'wellbeing_progress_user_id_unique');

        if ($indexExists) {
            Schema::table('wellbeing_progress', function (Blueprint $table) {
                $table->dropUnique('wellbeing_progress_user_id_unique');
            });
        }
    }

    public function down(): void
    {
        // Non-destructive rollback.
    }
};
