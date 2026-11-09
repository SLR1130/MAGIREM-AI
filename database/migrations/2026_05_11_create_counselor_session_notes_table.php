<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('counselor_session_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('counselor_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->text('note');
            $table->string('session_type')->default('chat'); // 'chat', 'appointment', 'crisis'
            $table->string('mood_assessment')->nullable(); // 'improving', 'stable', 'declining'
            $table->timestamp('session_date');
            $table->timestamps();
            
            $table->index(['counselor_id', 'student_id']);
            $table->index('session_date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('counselor_session_notes');
    }
};
