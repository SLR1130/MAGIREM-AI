<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('password_setup_completed_at')->nullable()->after('password');
            $table->boolean('owner_onboarding_required')->default(false)->after('password_setup_completed_at');
            $table->timestamp('notification_email_confirmed_at')->nullable()->after('notification_email_verified_at');
            $table->foreignId('notification_email_confirmed_by')->nullable()->after('notification_email_confirmed_at')->constrained('users')->nullOnDelete();
        });

        // Existing accounts keep their current login behavior. This records that
        // their password was already established before this onboarding feature.
        DB::table('users')->whereNull('password_setup_completed_at')->update([
            'password_setup_completed_at' => now(),
        ]);

        Schema::create('account_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('delivery_method', 20);
            $table->char('token_hash', 64)->unique();
            $table->string('recipient_email')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('email_delivery_id')->nullable()->constrained('account_verification_email_deliveries')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'expires_at', 'accepted_at'], 'account_invitation_owner_state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_invitations');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('notification_email_confirmed_by');
            $table->dropColumn([
                'password_setup_completed_at',
                'owner_onboarding_required',
                'notification_email_confirmed_at',
            ]);
        });
    }
};
