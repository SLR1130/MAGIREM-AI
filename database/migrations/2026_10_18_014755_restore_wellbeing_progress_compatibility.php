
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve the table if it already exists.
        if (Schema::hasTable('wellbeing_progress')) {
            return;
        }

        Schema::create('wellbeing_progress', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->unique(false);

            $table->date('date');

            $table->integer('emotional_stability')->default(5);
            $table->integer('stress_level')->default(5);
            $table->integer('wellbeing_score')->default(5);

            $table->integer('entries_count')->default(0);
            $table->integer('counselor_interactions')->default(0);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['user_id', 'date'],
                'wellbeing_progress_user_date_unique'
            );

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Intentionally non-destructive.
        // Do not automatically delete restored wellbeing data.
    }
};
