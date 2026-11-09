<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('microsoft_tenant_id', 64)->nullable();
            $table->string('microsoft_object_id', 64)->nullable();
            $table->string('institutional_email')->nullable();
            $table->timestamp('institutional_verified_at')->nullable();
            $table->string('requested_role', 24)->nullable();
            $table->string('approval_status', 32)->default('approved')->index();
            $table->timestamp('approval_submitted_at')->nullable();
            $table->string('campus', 120)->nullable();
            $table->string('staff_position', 160)->nullable();
            $table->string('notification_email')->nullable();
            $table->timestamp('notification_email_verified_at')->nullable();
            $table->boolean('notification_email_enabled')->default(true);
            $table->string('verification_document_path')->nullable();
            $table->timestamp('verification_document_submitted_at')->nullable();
            $table->timestamp('alternative_review_requested_at')->nullable();
            $table->string('privacy_consent_version', 40)->nullable();
            $table->timestamp('privacy_consent_at')->nullable();
            $table->foreignId('approval_reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approval_reviewed_at')->nullable();
            $table->text('internal_review_reason')->nullable();
            $table->text('information_request')->nullable();
            $table->string('approval_notification_status', 32)->nullable();
            $table->string('verification_email_delivery_status', 32)->default('not_requested');
            $table->unique(['microsoft_tenant_id', 'microsoft_object_id'], 'users_microsoft_identity_unique');
            $table->index(['approval_status', 'requested_role'], 'users_approval_role_index');
        });

        Schema::create('account_verification_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->text('internal_note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['application_user_id', 'created_at'], 'account_verification_audit_history');
        });

        Schema::create('account_email_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->char('code_hash', 64)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('send_count')->default(0);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('account_verification_email_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 40);
            $table->string('recipient_email');
            $table->string('status', 24)->default('queued');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('last_error_code', 80)->nullable();
            $table->timestamp('queued_at')->useCurrent();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'kind', 'created_at'], 'verification_email_delivery_history');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_verification_email_deliveries');
        Schema::dropIfExists('account_email_verifications');
        Schema::dropIfExists('account_verification_audits');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['approval_reviewer_id']);
            $table->dropUnique('users_microsoft_identity_unique');
            $table->dropIndex('users_approval_role_index');
            $table->dropColumn([
                'microsoft_tenant_id', 'microsoft_object_id', 'institutional_email', 'institutional_verified_at',
                'requested_role', 'approval_status', 'approval_submitted_at', 'campus', 'staff_position',
                'notification_email', 'notification_email_verified_at', 'notification_email_enabled',
                'verification_document_path', 'verification_document_submitted_at', 'alternative_review_requested_at',
                'privacy_consent_version', 'privacy_consent_at', 'approval_reviewer_id', 'approval_reviewed_at',
                'internal_review_reason', 'information_request', 'approval_notification_status', 'verification_email_delivery_status',
            ]);
        });
    }
};
