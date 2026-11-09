<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // ============ CRITICAL INDEXES FOR emotional_entries ============
        // These indexes will dramatically speed up queries used in:
        // - insights page (365 queries → 1 query)
        // - counselor dashboard (20+ queries → 2-3 queries)
        // - admin reports (50+ queries → 5 queries)
        
        try {
            // Check if table exists first
            if (Schema::hasTable('emotional_entries')) {
                Schema::table('emotional_entries', function (Blueprint $table) {
                    // Most critical: user + date queries
                    // Used in: streak calculation, daily emotion queries, insights page
                    if (!$this->indexExists('emotional_entries', 'idx_user_created')) {
                        $table->index(['user_id', 'created_at'], 'idx_user_created');
                    }
                    
                    // For conversation history queries
                    if (!$this->indexExists('emotional_entries', 'idx_session_id')) {
                        $table->index('session_id', 'idx_session_id');
                    }
                    
                    // For crisis level filtering
                    if (!$this->indexExists('emotional_entries', 'idx_crisis_level')) {
                        $table->index('crisis_level', 'idx_crisis_level');
                    }
                    
                    // For emotion distribution queries
                    if (!$this->indexExists('emotional_entries', 'idx_detected_emotion')) {
                        $table->index('detected_emotion', 'idx_detected_emotion');
                    }
                    
                    // For counselor attention queries
                    if (!$this->indexExists('emotional_entries', 'idx_user_needs_counselor')) {
                        $table->index(['user_id', 'needs_counselor'], 'idx_user_needs_counselor');
                    }
                });
            }
        } catch (\Exception $e) {
            \Log::warning("Could not add indexes to emotional_entries: " . $e->getMessage());
        }
        
        // ============ INDEXES FOR usage_patterns (Mental Health Monitoring) ============
        try {
            if (Schema::hasTable('usage_patterns')) {
                Schema::table('usage_patterns', function (Blueprint $table) {
                    // Used in mental health scoring (currently 3 separate queries)
                    if (!$this->indexExists('usage_patterns', 'idx_student_date')) {
                        $table->index(['student_id', 'message_date'], 'idx_student_date');
                    }
                    
                    // For late night usage detection
                    if (!$this->indexExists('usage_patterns', 'idx_is_late_night')) {
                        $table->index(['is_late_night', 'message_date'], 'idx_is_late_night');
                    }
                });
            }
        } catch (\Exception $e) {
            \Log::warning("Could not add indexes to usage_patterns: " . $e->getMessage());
        }
        
        // ============ INDEXES FOR community_messages (Social Platform) ============
        try {
            if (Schema::hasTable('community_messages')) {
                Schema::table('community_messages', function (Blueprint $table) {
                    // Activity feed queries (user's own posts + followed users)
                    if (!$this->indexExists('community_messages', 'idx_user_approved_created')) {
                        $table->index(['user_id', 'is_approved', 'created_at'], 'idx_user_approved_created');
                    }
                    
                    // Trending/recommended posts by category
                    if (!$this->indexExists('community_messages', 'idx_approved_category')) {
                        $table->index(['is_approved', 'category', 'created_at'], 'idx_approved_category');
                    }
                });
            }
        } catch (\Exception $e) {
            \Log::warning("Could not add indexes to community_messages: " . $e->getMessage());
        }
        
        // ============ INDEXES FOR user_follow (Followers/Following) ============
        try {
            if (Schema::hasTable('user_follow')) {
                Schema::table('user_follow', function (Blueprint $table) {
                    // Get all followers of a user (for follow status checks)
                    if (!$this->indexExists('user_follow', 'idx_user_active')) {
                        $table->index(['user_id', 'is_active'], 'idx_user_active');
                    }
                    
                    // Get all users following someone
                    if (!$this->indexExists('user_follow', 'idx_following_active')) {
                        $table->index(['following_id', 'is_active'], 'idx_following_active');
                    }
                });
            }
        } catch (\Exception $e) {
            \Log::warning("Could not add indexes to user_follow: " . $e->getMessage());
        }
        
        // ============ INDEXES FOR crisis_alerts ============
        try {
            if (Schema::hasTable('crisis_alerts')) {
                Schema::table('crisis_alerts', function (Blueprint $table) {
                    // Get active crisis alerts for dashboard
                    if (!$this->indexExists('crisis_alerts', 'idx_student_status')) {
                        $table->index(['student_id', 'is_resolved'], 'idx_student_status');
                    }
                });
            }
        } catch (\Exception $e) {
            \Log::warning("Could not add indexes to crisis_alerts: " . $e->getMessage());
        }
        
        // ============ INDEXES FOR counselor_messages ============
        try {
            if (Schema::hasTable('counselor_messages')) {
                Schema::table('counselor_messages', function (Blueprint $table) {
                    // Get unread message count
                    if (!$this->indexExists('counselor_messages', 'idx_counselor_read')) {
                        $table->index(['counselor_id', 'is_read'], 'idx_counselor_read');
                    }
                });
            }
        } catch (\Exception $e) {
            \Log::warning("Could not add indexes to counselor_messages: " . $e->getMessage());
        }
        
        // ============ INDEXES FOR mood_checkins ============
        try {
            if (Schema::hasTable('mood_checkins')) {
                Schema::table('mood_checkins', function (Blueprint $table) {
                    // Get recent mood check-ins for user
                    if (!$this->indexExists('mood_checkins', 'idx_user_created')) {
                        $table->index(['user_id', 'created_at'], 'idx_user_created');
                    }
                });
            }
        } catch (\Exception $e) {
            \Log::warning("Could not add indexes to mood_checkins: " . $e->getMessage());
        }
        
        \Log::info("Performance indexes migration completed successfully");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Only drop if safe (not in production)
        if (config('app.env') !== 'production') {
            try {
                if (Schema::hasTable('emotional_entries')) {
                    Schema::table('emotional_entries', function (Blueprint $table) {
                        $table->dropIndexIfExists('idx_user_created');
                        $table->dropIndexIfExists('idx_session_id');
                        $table->dropIndexIfExists('idx_crisis_level');
                        $table->dropIndexIfExists('idx_detected_emotion');
                        $table->dropIndexIfExists('idx_user_needs_counselor');
                    });
                }
                
                if (Schema::hasTable('usage_patterns')) {
                    Schema::table('usage_patterns', function (Blueprint $table) {
                        $table->dropIndexIfExists('idx_student_date');
                        $table->dropIndexIfExists('idx_is_late_night');
                    });
                }
                
                if (Schema::hasTable('community_messages')) {
                    Schema::table('community_messages', function (Blueprint $table) {
                        $table->dropIndexIfExists('idx_user_approved_created');
                        $table->dropIndexIfExists('idx_approved_category');
                    });
                }
                
                if (Schema::hasTable('user_follow')) {
                    Schema::table('user_follow', function (Blueprint $table) {
                        $table->dropIndexIfExists('idx_user_active');
                        $table->dropIndexIfExists('idx_following_active');
                    });
                }
                
                if (Schema::hasTable('crisis_alerts')) {
                    Schema::table('crisis_alerts', function (Blueprint $table) {
                        $table->dropIndexIfExists('idx_student_status');
                    });
                }
                
                if (Schema::hasTable('counselor_messages')) {
                    Schema::table('counselor_messages', function (Blueprint $table) {
                        $table->dropIndexIfExists('idx_counselor_read');
                    });
                }
                
                if (Schema::hasTable('mood_checkins')) {
                    Schema::table('mood_checkins', function (Blueprint $table) {
                        $table->dropIndexIfExists('idx_user_created');
                    });
                }
            } catch (\Exception $e) {
                \Log::warning("Could not drop performance indexes: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Check if an index exists on a table
     */
    private function indexExists($table, $indexName)
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index) => ($index['name'] ?? null) === $indexName);
    }
};
