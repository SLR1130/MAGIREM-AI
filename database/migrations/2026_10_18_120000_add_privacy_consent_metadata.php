<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('privacy_settings')) {
            return;
        }

        Schema::table('privacy_settings', function (Blueprint $table) {
            $table->boolean('share_with_counselor')->default(false)->change();
            $table->boolean('share_analytics')->default(false)->change();
            $table->boolean('allow_anonymous_community')->default(false)->change();
            $table->boolean('allow_ai_recommendations')->default(false)->change();
        });

        Schema::table('privacy_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('privacy_settings', 'counselor_sharing_consented_at')) {
                $table->timestamp('counselor_sharing_consented_at')->nullable();
            }
            if (!Schema::hasColumn('privacy_settings', 'analytics_consented_at')) {
                $table->timestamp('analytics_consented_at')->nullable();
            }
            if (!Schema::hasColumn('privacy_settings', 'community_consented_at')) {
                $table->timestamp('community_consented_at')->nullable();
            }
            if (!Schema::hasColumn('privacy_settings', 'ai_recommendations_consented_at')) {
                $table->timestamp('ai_recommendations_consented_at')->nullable();
            }
            if (!Schema::hasColumn('privacy_settings', 'consent_policy_version')) {
                $table->string('consent_policy_version', 64)->nullable();
            }
            if (!Schema::hasColumn('privacy_settings', 'consent_source')) {
                $table->string('consent_source', 64)->nullable();
            }
        });
    }

    public function down(): void
    {
        // Consent evidence and privacy-safe defaults are intentionally preserved.
    }
};
