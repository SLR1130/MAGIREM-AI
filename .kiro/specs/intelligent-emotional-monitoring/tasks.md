# Implementation Plan: Intelligent Emotional Monitoring System

## Overview

This implementation plan transforms MinSuansCore's reactive emotion detection into an intelligent pattern-based monitoring system. The system tracks emotional trajectories over time, detects meaningful patterns through multi-day analysis, and provides counselors with actionable insights through visual dashboards.

The implementation follows a bottom-up approach: database schema → core services → scheduled tasks → UI integration → testing.

## Tasks

- [x] 1. Create database schema and migrations
  - Create migration for emotional_scores table with indexes
  - Create migration for daily_summaries table with unique constraint
  - Create migration for pattern_alerts table with status enum
  - Run migrations and verify table structure
  - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5, 11.6, 11.7_

- [x] 2. Implement Emotion Scorer Service
  - [x] 2.1 Create EmotionScorerService class with emotion mapping
    - Create app/Services/EmotionScorerService.php
    - Implement mapEmotionToScore() method with score constants (Happy: +2, Excited: +3, Calm: +1, Neutral: 0, Sad: -2, Stressed: -2, Anxious: -3, Angry: -3)
    - Implement applyTimeWeighting() method for late-night entries (22:00-05:00 multiplied by 1.5)
    - Implement storeScore() method to persist to emotional_scores table
    - _Requirements: 1.1, 1.2, 1.4, 5.1, 5.2, 5.5, 5.6_

  - [ ]* 2.2 Write property test for emotion score range validation
    - **Property 1: Emotion Score Range Validation**
    - **Validates: Requirements 1.1, 1.2**

  - [ ]* 2.3 Write property test for emotion label mapping consistency
    - **Property 2: Emotion Label Mapping Consistency**
    - **Validates: Requirements 1.4**

  - [x] 2.4 Implement scoreChatMessage() public method
    - Call EmotionDetectionService->detectEmotion()
    - Map emotion to score using mapEmotionToScore()
    - Apply time weighting based on message timestamp
    - Store score with content_type='message' and content_id=message_id
    - Return array with score and emotion
    - _Requirements: 1.1, 1.3, 1.5, 12.1, 12.2_

  - [x] 2.5 Implement scoreJournalEntry() public method
    - Call EmotionDetectionService->detectEmotion()
    - Map emotion to score using mapEmotionToScore()
    - Apply time weighting based on entry timestamp
    - Store score with content_type='journal' and content_id=entry_id
    - Return array with score and emotion
    - _Requirements: 1.2, 1.3, 1.5, 12.1, 12.2_

  - [ ]* 2.6 Write property test for time weighting application
    - **Property 4: Time Weighting Application**
    - **Validates: Requirements 5.1, 5.2, 5.5**

  - [ ]* 2.7 Write property test for emotional score persistence
    - **Property 3: Emotional Score Persistence**
    - **Validates: Requirements 1.5**

  - [x] 2.8 Implement ML service failure handling
    - Add try-catch around EmotionDetectionService calls
    - On failure, assign neutral score (0) and log error
    - Implement retry logic (3 attempts with 2-second delays)
    - _Requirements: 1.6, 12.3, 12.4_

- [ ] 3. Checkpoint - Verify emotion scoring works
  - Ensure all tests pass, ask the user if questions arise.

- [x] 4. Implement Daily Aggregator Service
  - [x] 4.1 Create DailyAggregatorService class with classification logic
    - Create app/Services/DailyAggregatorService.php
    - Implement classifyMentalStatus() method (Stable: ≥0, Slightly Low: -5 to -1, Low: -10 to -6, Critical: ≤-11)
    - Implement assessReliability() method (Low: <2, Medium: 2-5, High: >5)
    - _Requirements: 2.3, 2.4, 2.5, 2.6, 6.2, 6.3, 6.4_

  - [ ]* 4.2 Write property test for status classification correctness
    - **Property 9: Status Classification Correctness**
    - **Validates: Requirements 2.3, 2.4, 2.5, 2.6**

  - [ ]* 4.3 Write property test for reliability assessment correctness
    - **Property 10: Reliability Assessment Correctness**
    - **Validates: Requirements 6.2, 6.3, 6.4**

  - [x] 4.4 Implement aggregateForStudent() method
    - Query emotional_scores for user_id and date
    - Sum all weighted_scores to get total_score
    - Count entries for entry_count
    - Classify status using classifyMentalStatus()
    - Assess reliability using assessReliability()
    - Store in daily_summaries table
    - Return null if no entries for the day
    - _Requirements: 2.1, 2.2, 2.7, 2.8, 6.1_

  - [ ]* 4.5 Write property test for daily score aggregation accuracy
    - **Property 6: Daily Score Aggregation Accuracy**
    - **Validates: Requirements 2.2**

  - [ ]* 4.6 Write property test for entry count accuracy
    - **Property 8: Entry Count Accuracy**
    - **Validates: Requirements 6.1**

  - [x] 4.7 Implement aggregateDaily() method for all students
    - Get all active students (users with role='student')
    - Loop through each student and call aggregateForStudent()
    - Return summary with processed count and summaries_created count
    - _Requirements: 2.1, 14.2_

  - [ ]* 4.8 Write property test for daily summary persistence
    - **Property 7: Daily Summary Persistence**
    - **Validates: Requirements 2.7**

- [ ] 5. Checkpoint - Verify daily aggregation works
  - Ensure all tests pass, ask the user if questions arise.

- [x] 6. Implement Pattern Detector Service
  - [x] 6.1 Create PatternDetectorService class with detection algorithms
    - Create app/Services/PatternDetectorService.php
    - Implement detectConsecutiveDays() method for 3+ Low/Critical or 5+ Slightly Low
    - Implement detectSuddenDrop() method for 10+ point drops from 3-day average
    - Implement shouldSuppressAlert() method to check for duplicates within 7 days
    - Implement createAlert() method to store in pattern_alerts table
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 4.1, 4.2, 4.6, 15.1, 15.2_

  - [ ]* 6.2 Write property test for consecutive days pattern detection
    - **Property 11: Consecutive Days Pattern Detection**
    - **Validates: Requirements 3.2, 3.3**

  - [ ]* 6.3 Write property test for single-day negative status ignored
    - **Property 12: Single-Day Negative Status Ignored**
    - **Validates: Requirements 3.4**

  - [ ]* 6.4 Write property test for sudden drop detection
    - **Property 15: Sudden Drop Detection**
    - **Validates: Requirements 4.2, 4.4**

  - [x] 6.2 Implement analyzeStudentPatterns() method
    - Get last 5 days of daily_summaries for the student
    - Call detectConsecutiveDays() to check for patterns
    - Call detectSuddenDrop() to check for rapid declines
    - For each detected pattern, check shouldSuppressAlert()
    - If not suppressed, call createAlert()
    - Return array with consecutive_days and sudden_drop results
    - _Requirements: 3.1, 4.1, 4.3, 15.1, 15.2, 15.3_

  - [ ]* 6.3 Write property test for pattern alert persistence
    - **Property 13: Pattern Alert Persistence**
    - **Validates: Requirements 3.5**

  - [ ]* 6.4 Write property test for rolling average calculation
    - **Property 14: Rolling Average Calculation**
    - **Validates: Requirements 4.1**

  - [x] 6.5 Implement analyzePatterns() method for all students
    - Get all students with daily_summaries in the last 5 days
    - Loop through each student and call analyzeStudentPatterns()
    - Return summary with students_analyzed and alerts_generated counts
    - _Requirements: 3.1, 14.3_

  - [ ]* 6.6 Write property test for sudden drop description completeness
    - **Property 16: Sudden Drop Description Completeness**
    - **Validates: Requirements 4.5**

  - [x] 6.7 Implement alert suppression logic
    - Check for existing unresolved alerts of same type within 7 days
    - If found, return true to suppress
    - If new alert has higher severity, update existing alert severity
    - Enforce maximum 3 active alerts per student
    - If at limit and new alert is High severity, suppress lowest Medium alert
    - Log all suppressed alerts
    - _Requirements: 9.5, 15.2, 15.3, 15.4, 15.5, 15.6_

  - [ ]* 6.8 Write property test for duplicate alert suppression
    - **Property 21: Duplicate Alert Suppression**
    - **Validates: Requirements 9.5**

  - [ ]* 6.9 Write property test for alert severity escalation
    - **Property 22: Alert Severity Escalation**
    - **Validates: Requirements 15.3**

  - [ ]* 6.10 Write property test for maximum active alerts per student
    - **Property 23: Maximum Active Alerts Per Student**
    - **Validates: Requirements 15.4**

- [ ] 7. Checkpoint - Verify pattern detection works
  - Ensure all tests pass, ask the user if questions arise.

- [x] 8. Implement Alert Manager Service
  - [x] 8.1 Create AlertManagerService class
    - Create app/Services/AlertManagerService.php
    - Implement getUnresolvedAlerts() method with sorting (severity desc, date desc)
    - Implement updateAlertStatus() method to change status and set resolved_at/counselor_id
    - Implement notifyCounselors() method to send email notifications
    - _Requirements: 9.1, 9.3, 9.4, 9.6_

  - [ ]* 8.2 Write property test for alert sorting correctness
    - **Property 19: Alert Sorting Correctness**
    - **Validates: Requirements 9.1**

  - [ ]* 8.3 Write property test for alert status transition
    - **Property 20: Alert Status Transition**
    - **Validates: Requirements 9.3, 9.4**

  - [x] 8.4 Implement counselor notification logic
    - Send email to all counselors for High severity alerts
    - Send within 15 minutes of alert creation
    - Include student info, alert type, and pattern description
    - _Requirements: 3.6, 9.6_

  - [ ]* 8.5 Write property test for alert prioritization at limit
    - **Property 24: Alert Prioritization at Limit**
    - **Validates: Requirements 15.5**

  - [ ]* 8.6 Write property test for suppressed alert logging
    - **Property 25: Suppressed Alert Logging**
    - **Validates: Requirements 15.6**

- [x] 9. Implement Dashboard Visualizer Service
  - [x] 9.1 Create DashboardVisualizerService class
    - Create app/Services/DashboardVisualizerService.php
    - Implement getTrajectoryData() method to fetch daily_summaries for date range
    - Implement calculateTrend() method to determine up/down/stable direction
    - Implement getSummaryStats() method for current status and averages
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.6, 8.7_

  - [ ]* 9.2 Write property test for trajectory data completeness
    - **Property 18: Trajectory Data Completeness**
    - **Validates: Requirements 8.4, 8.6**

  - [x] 9.3 Format trajectory data for Chart.js
    - Return arrays of dates, scores, statuses, and reliability flags
    - Include pattern alert markers with dates and types
    - Support 7, 30, 90 day ranges and all-time
    - _Requirements: 8.1, 8.2, 8.3, 8.6_

  - [x] 9.4 Implement trend calculation algorithm
    - Calculate linear regression slope for the period
    - Return 'up' if slope > 0.5, 'down' if slope < -0.5, 'stable' otherwise
    - Include change magnitude in response
    - _Requirements: 8.4_

- [x] 10. Create Eloquent models
  - [x] 10.1 Create EmotionalScore model
    - Create app/Models/EmotionalScore.php
    - Define relationships: belongsTo User, morphTo content
    - Add fillable fields and casts
    - _Requirements: 11.1, 11.5_

  - [x] 10.2 Create DailySummary model
    - Create app/Models/DailySummary.php
    - Define relationships: belongsTo User, hasMany EmotionalScore
    - Add fillable fields and casts
    - _Requirements: 11.2, 11.6_

  - [x] 10.3 Create PatternAlert model
    - Create app/Models/PatternAlert.php
    - Define relationships: belongsTo User, belongsTo Counselor
    - Add fillable fields and casts
    - _Requirements: 11.3, 11.7_

- [x] 11. Integrate emotion scoring into existing controllers
  - [x] 11.1 Update StudentController::storeEntry() to call EmotionScorerService
    - After creating EmotionalEntry, call scoreJournalEntry()
    - Store returned score in emotional_scores table
    - _Requirements: 1.2, 1.5_

  - [x] 11.2 Update StudentController::sendMessage() to call EmotionScorerService
    - After creating CounselorMessage, call scoreChatMessage()
    - Store returned score in emotional_scores table
    - _Requirements: 1.1, 1.5_

- [ ] 12. Implement batch processing for historical data
  - [ ] 12.1 Create Artisan command for batch processing
    - Create app/Console/Commands/ProcessHistoricalEmotions.php
    - Implement handle() method to process unscored messages and entries
    - Process in batches of 100 to prevent memory overflow
    - Process in chronological order (oldest first)
    - _Requirements: 10.1, 10.2, 10.3, 10.4_

  - [ ]* 12.2 Write property test for batch processing completeness
    - **Property 26: Batch Processing Completeness**
    - **Validates: Requirements 10.2**

  - [ ]* 12.3 Write property test for batch processing chronological order
    - **Property 27: Batch Processing Chronological Order**
    - **Validates: Requirements 10.3**

  - [ ] 12.4 Generate batch processing summary report
    - Count total entries processed
    - Count failed entries
    - Calculate score distribution by emotion
    - Return summary array
    - _Requirements: 10.5_

  - [ ]* 12.5 Write property test for batch processing report accuracy
    - **Property 28: Batch Processing Report Accuracy**
    - **Validates: Requirements 10.5**

  - [ ]* 12.6 Write property test for historical time weighting
    - **Property 29: Historical Time Weighting**
    - **Validates: Requirements 10.6**

- [ ] 13. Checkpoint - Verify batch processing works
  - Ensure all tests pass, ask the user if questions arise.

- [-] 14. Create scheduled tasks
  - [x] 14.1 Create Artisan command for daily aggregation
    - Create app/Console/Commands/AggregateDailyEmotions.php
    - Call DailyAggregatorService::aggregateDaily()
    - Schedule to run daily at 23:59 in app/Console/Kernel.php
    - _Requirements: 2.1, 14.2_

  - [x] 14.2 Create Artisan command for pattern detection
    - Create app/Console/Commands/DetectEmotionalPatterns.php
    - Call PatternDetectorService::analyzePatterns()
    - Schedule to run daily at 00:15 in app/Console/Kernel.php
    - _Requirements: 3.1, 14.3_

  - [ ] 14.3 Create Artisan command for counselor notifications
    - Create app/Console/Commands/NotifyCounselorsOfAlerts.php
    - Call AlertManagerService::notifyCounselors() for unnotified High alerts
    - Schedule to run every 15 minutes in app/Console/Kernel.php
    - _Requirements: 9.6_

- [x] 15. Update journal entries page to display emotional scores
  - [x] 15.1 Modify resources/views/student/entries.blade.php
    - Display emotion label and numerical score for each entry
    - Add color coding (green for positive, gray for neutral, amber for negative)
    - Show trend indicator (up/down/neutral arrow) comparing to previous entry
    - Only show scores to entry author
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6_

  - [ ]* 15.2 Write property test for trend indicator calculation
    - **Property 17: Trend Indicator Calculation**
    - **Validates: Requirements 7.4**

- [ ] 16. Create counselor dashboard for alerts
  - [ ] 16.1 Create CounselorController::alerts() method
    - Call AlertManagerService::getUnresolvedAlerts()
    - Pass alerts to view with sorting options
    - _Requirements: 9.1_

  - [ ] 16.2 Create resources/views/counselor/alerts.blade.php
    - Display unresolved alerts in table format
    - Show student name, alert type, severity, pattern description, detection date
    - Add filter by severity (Medium/High)
    - Add sort options (severity, date, student)
    - _Requirements: 9.1_

  - [ ] 16.3 Add alert detail modal
    - Show student emotional trajectory for alert period
    - Display detailed pattern description
    - Add buttons to update status (Acknowledge, In Progress, Resolve)
    - _Requirements: 9.2, 9.3_

  - [ ] 16.4 Implement alert status update endpoint
    - Create CounselorController::updateAlertStatus() method
    - Call AlertManagerService::updateAlertStatus()
    - Return JSON response
    - _Requirements: 9.3, 9.4_

- [ ] 17. Create counselor dashboard for student trajectories
  - [ ] 17.1 Create CounselorController::studentTrajectory() method
    - Call DashboardVisualizerService::getTrajectoryData()
    - Call DashboardVisualizerService::getSummaryStats()
    - Pass data to view
    - _Requirements: 8.1, 8.4, 8.5_

  - [ ] 17.2 Create resources/views/counselor/student-trajectory.blade.php
    - Integrate Chart.js for line chart visualization
    - Display dates on x-axis, scores on y-axis
    - Add color-coded zones (green for positive, yellow for slightly negative, red for critical)
    - Mark pattern alert dates with visual indicators
    - Add date range selector (7, 30, 90 days, all time)
    - Display summary card with current status, 7-day trend, 30-day trend
    - Show reliability indicators for each time period
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 8.6, 8.7_

  - [ ] 17.3 Implement clickable data points
    - On click, show modal with detailed breakdown of that day's entries
    - Display all messages and journal entries for the day
    - Show individual scores and emotions
    - _Requirements: 8.5_

- [ ] 18. Implement privacy and access control
  - [ ] 18.1 Create middleware to restrict emotional_scores access
    - Create app/Http/Middleware/RestrictEmotionalDataAccess.php
    - Allow counselors and admins full access
    - Allow students to view only their own scores on journal page
    - Block students from viewing raw scores on dashboard
    - _Requirements: 13.1, 13.2, 13.3_

  - [ ] 18.2 Implement access audit logging
    - Create pattern_alert_access_logs table migration
    - Log all counselor access to pattern_alerts (view, update, delete)
    - Store user_id, alert_id, action, timestamp
    - _Requirements: 13.4_

  - [ ]* 18.3 Write property test for access audit logging
    - **Property 30: Access Audit Logging**
    - **Validates: Requirements 13.4**

  - [ ] 18.4 Implement data anonymization in logs
    - Update logging configuration to anonymize emotional data
    - Mask student names and message content in error logs
    - _Requirements: 13.5_

  - [ ] 18.5 Implement cascade deletion
    - Update User model with onDelete('cascade') for relationships
    - Verify emotional_scores, daily_summaries, pattern_alerts are deleted with user
    - _Requirements: 13.6_

  - [ ]* 18.6 Write property test for cascade deletion
    - **Property 31: Cascade Deletion**
    - **Validates: Requirements 13.6**

- [ ] 19. Checkpoint - Verify UI and privacy features work
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 20. Implement JSON export/import functionality
  - [ ] 20.1 Create AdminController::exportEmotionalData() method
    - Accept start_date, end_date, tables parameters
    - Query emotional_scores, daily_summaries, pattern_alerts for date range
    - Serialize to JSON with schema definition in header
    - Return JSON file download
    - _Requirements: 16.1, 16.2, 16.3_

  - [ ]* 20.2 Write property test for JSON export validity
    - **Property 32: JSON Export Validity**
    - **Validates: Requirements 16.2**

  - [ ]* 20.3 Write property test for JSON export schema inclusion
    - **Property 33: JSON Export Schema Inclusion**
    - **Validates: Requirements 16.3**

  - [ ] 20.4 Create AdminController::importEmotionalData() method
    - Accept JSON file upload
    - Validate JSON structure against schema
    - Parse and insert records into database
    - Return descriptive error messages for invalid files
    - _Requirements: 16.4, 16.6_

  - [ ]* 20.5 Write property test for JSON import/export round-trip
    - **Property 34: JSON Import/Export Round-Trip**
    - **Validates: Requirements 16.5**

- [ ] 21. Performance optimization
  - [ ] 21.1 Add database indexes
    - Verify indexes on user_id and detected_at in emotional_scores
    - Verify indexes on user_id and date in daily_summaries
    - Verify indexes on user_id, status, severity in pattern_alerts
    - _Requirements: 11.4, 14.1_

  - [ ] 21.2 Implement caching for ML service responses
    - Cache identical message content responses for 24 hours
    - Use Laravel cache facade with content hash as key
    - _Requirements: 12.6_

  - [ ] 21.3 Optimize trajectory data queries
    - Use eager loading for relationships
    - Add select() to limit columns fetched
    - Ensure queries complete within 1 second
    - _Requirements: 14.4_

  - [ ] 21.4 Test concurrent emotion scoring
    - Verify system handles 100 simultaneous scoring requests
    - Ensure response times remain under 2 seconds per request
    - _Requirements: 14.1, 14.5_

- [ ] 22. Final integration testing
  - [ ]* 22.1 Write integration test for complete pipeline
    - Test message creation → emotion scoring → daily aggregation → pattern detection → alert generation
    - Verify all database records created correctly
    - Validate alert notifications sent to counselors

  - [ ]* 22.2 Write integration test for historical data migration
    - Create historical messages and entries without scores
    - Run batch processing command
    - Verify all entries scored correctly with proper time weighting

  - [ ]* 22.3 Write integration test for counselor workflow
    - Create pattern alert
    - View alert on counselor dashboard
    - View student trajectory
    - Update alert status to Resolved
    - Verify all UI interactions work correctly

- [ ] 23. Final checkpoint - System ready for production
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional property-based tests and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation at key milestones
- Property tests validate universal correctness properties across all inputs
- Unit tests validate specific examples and edge cases
- The implementation follows Laravel conventions and integrates with existing MinSuansCore architecture
- All scheduled tasks use Laravel's task scheduler for reliability
- The system maintains backward compatibility with existing emotion detection functionality
