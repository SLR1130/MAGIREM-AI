# Requirements Document

## Introduction

The Intelligent Emotional Monitoring System transforms MinSuansCore's reactive emotion detection into a supervised, pattern-based mental wellness monitoring platform. Instead of triggering alerts on single messages, the system tracks emotional trajectories over time, detects meaningful patterns, and provides counselors with actionable insights into student mental health trends.

The system introduces continuous emotion scoring, daily mental status aggregation, pattern detection algorithms, time-based behavior analysis, and visual dashboards that replace immediate alerts with intelligent, context-aware interventions.

## Glossary

- **Emotion_Scorer**: Component that assigns numerical emotional scores to messages and journal entries
- **Pattern_Detector**: Component that analyzes emotional score sequences to identify concerning trends
- **Daily_Aggregator**: Component that calculates daily mental status from individual emotional scores
- **Alert_Manager**: Component that generates alerts based on detected patterns, not individual events
- **Dashboard_Visualizer**: Component that displays emotional trajectories and mental health trends
- **Journal_Analyzer**: Component that processes journal entries for emotional content
- **Time_Weighting_Engine**: Component that applies higher weights to late-night entries
- **ML_Service**: Python-based machine learning service (fast_ml_server.py) for emotion detection
- **Emotional_Score**: Numerical value representing emotional state (-3 to +3 scale)
- **Mental_Status**: Aggregated daily classification (Stable, Slightly Low, Low, Critical)
- **Pattern_Alert**: Notification triggered by multi-day emotional trends, not single events
- **Consecutive_Days**: Sequence of days with similar emotional characteristics
- **Sudden_Drop**: Rapid decrease in emotional score over short time period
- **Late_Night_Entry**: Message or journal entry created between 22:00 and 05:00
- **Usage_Pattern**: Frequency and timing characteristics of student platform interaction
- **Emotional_Trajectory**: Time-series visualization of emotional scores over days or weeks

## Requirements

### Requirement 1: Emotion Scoring System

**User Story:** As a mental health monitoring system, I want to assign numerical scores to every message and journal entry, so that I can quantify emotional states for pattern analysis.

#### Acceptance Criteria

1. WHEN a student sends a chat message, THE Emotion_Scorer SHALL assign an emotional score between -3 and +3
2. WHEN a student creates a journal entry, THE Emotion_Scorer SHALL assign an emotional score between -3 and +3
3. THE Emotion_Scorer SHALL use the ML_Service to detect emotional content before scoring
4. THE Emotion_Scorer SHALL map detected emotions to scores: Happy (+2), Excited (+3), Calm (+1), Neutral (0), Sad (-2), Stressed (-2), Anxious (-3), Angry (-3)
5. THE Emotion_Scorer SHALL store each score with timestamp, user_id, content_type, and content_id in the emotional_scores table
6. WHEN emotion detection fails, THE Emotion_Scorer SHALL assign a neutral score (0) and log the failure

### Requirement 2: Daily Mental Status Aggregation

**User Story:** As a counselor, I want to see daily mental status summaries instead of raw emotions, so that I can quickly assess student wellbeing without information overload.

#### Acceptance Criteria

1. THE Daily_Aggregator SHALL calculate daily mental status at 23:59 each day for all active students
2. THE Daily_Aggregator SHALL sum all emotional scores for each student within the 24-hour period
3. WHEN daily score sum is greater than or equal to 0, THE Daily_Aggregator SHALL classify status as "Stable"
4. WHEN daily score sum is between -5 and -1, THE Daily_Aggregator SHALL classify status as "Slightly Low"
5. WHEN daily score sum is between -10 and -6, THE Daily_Aggregator SHALL classify status as "Low"
6. WHEN daily score sum is less than or equal to -11, THE Daily_Aggregator SHALL classify status as "Critical"
7. THE Daily_Aggregator SHALL store daily summaries in the daily_summaries table with date, user_id, total_score, status, and entry_count
8. WHEN a student has zero entries for a day, THE Daily_Aggregator SHALL not create a summary record for that day

### Requirement 3: Consecutive Days Pattern Detection

**User Story:** As a pattern detection system, I want to identify multi-day negative trends, so that I can alert counselors to sustained mental health concerns.

#### Acceptance Criteria

1. THE Pattern_Detector SHALL analyze daily summaries every 24 hours for all students
2. WHEN a student has 3 consecutive days with status "Low" or "Critical", THE Pattern_Detector SHALL generate a pattern alert
3. WHEN a student has 5 consecutive days with status "Slightly Low", THE Pattern_Detector SHALL generate a pattern alert
4. THE Pattern_Detector SHALL not generate alerts for single-day negative status
5. THE Pattern_Detector SHALL store pattern alerts in the pattern_alerts table with user_id, alert_type, severity, detection_date, and pattern_description
6. WHEN a pattern alert is generated, THE Alert_Manager SHALL notify assigned counselors within 1 hour

### Requirement 4: Sudden Drop Detection

**User Story:** As a crisis prevention system, I want to detect rapid emotional declines, so that I can identify students who may need immediate intervention.

#### Acceptance Criteria

1. THE Pattern_Detector SHALL calculate 3-day rolling average emotional scores for all students daily
2. WHEN a student's daily score drops by 10 or more points below their 3-day rolling average, THE Pattern_Detector SHALL generate a "Sudden Drop" alert
3. THE Pattern_Detector SHALL compare current day score against the average of the previous 3 days
4. WHEN a sudden drop is detected, THE Pattern_Detector SHALL classify severity as "High" and set priority flag to true
5. THE Pattern_Detector SHALL include previous average and current score in the pattern_description field
6. THE Pattern_Detector SHALL not trigger sudden drop alerts for students with fewer than 3 days of historical data

### Requirement 5: Time-Based Weighting System

**User Story:** As a mental health analyst, I want late-night entries to carry more weight, so that concerning behavior during vulnerable hours is properly emphasized.

#### Acceptance Criteria

1. WHEN a message or journal entry is created between 22:00 and 05:00, THE Time_Weighting_Engine SHALL multiply the emotional score by 1.5
2. WHEN a message or journal entry is created between 05:01 and 21:59, THE Time_Weighting_Engine SHALL use the original emotional score without modification
3. THE Time_Weighting_Engine SHALL apply time weighting before the Emotion_Scorer stores the score in the database
4. THE Time_Weighting_Engine SHALL use the server timezone for time calculations
5. THE Time_Weighting_Engine SHALL round weighted scores to one decimal place
6. THE Time_Weighting_Engine SHALL store both original_score and weighted_score in the emotional_scores table

### Requirement 6: Usage Pattern Analysis

**User Story:** As a data reliability system, I want to track student usage patterns, so that I can assess the reliability of emotional scoring data.

#### Acceptance Criteria

1. THE Daily_Aggregator SHALL count the number of entries (messages plus journal entries) per student per day
2. WHEN a student has fewer than 2 entries in a day, THE Daily_Aggregator SHALL set reliability_flag to "Low" in the daily summary
3. WHEN a student has 2 to 5 entries in a day, THE Daily_Aggregator SHALL set reliability_flag to "Medium" in the daily summary
4. WHEN a student has more than 5 entries in a day, THE Daily_Aggregator SHALL set reliability_flag to "High" in the daily summary
5. THE Pattern_Detector SHALL prioritize alerts from students with "High" or "Medium" reliability over "Low" reliability
6. THE Dashboard_Visualizer SHALL display reliability indicators alongside mental status information

### Requirement 7: Journal Entry Emotion Display

**User Story:** As a student, I want to see emotional scores on my journal entries, so that I can understand how my emotions are being tracked over time.

#### Acceptance Criteria

1. WHEN a student views the journal entries page (student/entries), THE Journal_Analyzer SHALL display the emotional score for each entry
2. THE Journal_Analyzer SHALL display scores using color coding: positive scores in green, neutral in gray, negative in amber
3. THE Journal_Analyzer SHALL display the emotion label (Happy, Sad, Anxious, etc.) alongside the numerical score
4. THE Journal_Analyzer SHALL show a trend indicator (up arrow, down arrow, or neutral) comparing to the previous entry
5. WHEN a student has no previous entries, THE Journal_Analyzer SHALL not display a trend indicator
6. THE Journal_Analyzer SHALL display scores only to the entry author, not to other users

### Requirement 8: Emotional Trajectory Visualization

**User Story:** As a counselor, I want to see visual graphs of student emotional trajectories, so that I can quickly identify trends and patterns.

#### Acceptance Criteria

1. THE Dashboard_Visualizer SHALL display a line chart of daily emotional scores for the past 30 days
2. THE Dashboard_Visualizer SHALL use color-coded zones: green for positive scores, yellow for slightly negative, red for critical
3. THE Dashboard_Visualizer SHALL mark pattern alert dates with visual indicators on the timeline
4. THE Dashboard_Visualizer SHALL display a summary card showing current status, 7-day trend, and 30-day trend
5. WHEN a counselor clicks on a data point, THE Dashboard_Visualizer SHALL show detailed breakdown of that day's entries
6. THE Dashboard_Visualizer SHALL allow counselors to filter by date range (7 days, 30 days, 90 days, all time)
7. THE Dashboard_Visualizer SHALL display usage pattern reliability indicators for each time period

### Requirement 9: Pattern Alert Management

**User Story:** As a counselor, I want to manage pattern alerts efficiently, so that I can prioritize interventions and track resolution.

#### Acceptance Criteria

1. THE Alert_Manager SHALL display all unresolved pattern alerts on the counselor dashboard sorted by severity and date
2. WHEN a counselor views an alert, THE Alert_Manager SHALL display the student's emotional trajectory for the alert period
3. THE Alert_Manager SHALL allow counselors to mark alerts as "Acknowledged", "In Progress", or "Resolved"
4. WHEN an alert is marked "Resolved", THE Alert_Manager SHALL record the resolution date and counselor_id
5. THE Alert_Manager SHALL not generate duplicate alerts for the same pattern within 7 days
6. THE Alert_Manager SHALL send email notifications to counselors for "High" severity alerts within 15 minutes of detection

### Requirement 10: Historical Data Migration

**User Story:** As a system administrator, I want to process existing chat messages and journal entries, so that historical emotional data is available for pattern analysis.

#### Acceptance Criteria

1. THE Emotion_Scorer SHALL provide a batch processing function for historical data
2. WHEN batch processing is initiated, THE Emotion_Scorer SHALL process all chat_messages and journal_entries without emotional scores
3. THE Emotion_Scorer SHALL process historical entries in chronological order from oldest to newest
4. THE Emotion_Scorer SHALL process entries in batches of 100 to prevent memory overflow
5. WHEN batch processing completes, THE Emotion_Scorer SHALL generate a summary report showing total entries processed and score distribution
6. THE Emotion_Scorer SHALL apply time weighting to historical entries based on their original creation timestamp

### Requirement 11: Database Schema Requirements

**User Story:** As a data persistence system, I want properly structured database tables, so that emotional monitoring data is stored reliably and efficiently.

#### Acceptance Criteria

1. THE System SHALL create an emotional_scores table with columns: id, user_id, content_type, content_id, emotion_label, original_score, weighted_score, detected_at, created_at
2. THE System SHALL create a daily_summaries table with columns: id, user_id, date, total_score, status, entry_count, reliability_flag, created_at
3. THE System SHALL create a pattern_alerts table with columns: id, user_id, alert_type, severity, pattern_description, detection_date, status, resolved_at, counselor_id, created_at
4. THE System SHALL create indexes on user_id and date columns for all three tables
5. THE System SHALL create a foreign key constraint linking emotional_scores.user_id to users.id
6. THE System SHALL create a foreign key constraint linking daily_summaries.user_id to users.id
7. THE System SHALL create a foreign key constraint linking pattern_alerts.user_id to users.id

### Requirement 12: ML Service Integration

**User Story:** As an emotion detection system, I want to integrate with the existing ML service, so that emotion classification is consistent across the platform.

#### Acceptance Criteria

1. THE Emotion_Scorer SHALL send message content to the ML_Service via HTTP POST to the /detect_emotion endpoint
2. WHEN the ML_Service returns an emotion label, THE Emotion_Scorer SHALL map it to the appropriate numerical score
3. WHEN the ML_Service is unavailable, THE Emotion_Scorer SHALL retry up to 3 times with 2-second delays
4. WHEN all retries fail, THE Emotion_Scorer SHALL assign a neutral score (0) and log the failure to the application log
5. THE Emotion_Scorer SHALL include a timeout of 5 seconds for ML_Service requests
6. THE Emotion_Scorer SHALL cache ML_Service responses for identical message content for 24 hours to improve performance

### Requirement 13: Privacy and Data Protection

**User Story:** As a privacy-conscious system, I want to protect student emotional data, so that sensitive mental health information remains confidential.

#### Acceptance Criteria

1. THE System SHALL restrict access to emotional_scores table to counselors and administrators only
2. THE System SHALL not display raw emotional scores to students on the main dashboard
3. THE System SHALL allow students to view their own emotional scores only on the journal entries page
4. THE System SHALL log all access to pattern_alerts table with user_id, timestamp, and action
5. THE System SHALL anonymize emotional data in system logs and error messages
6. WHEN a student account is deleted, THE System SHALL cascade delete all associated emotional_scores, daily_summaries, and pattern_alerts within 24 hours

### Requirement 14: Performance Requirements

**User Story:** As a scalable system, I want efficient processing of emotional data, so that the platform remains responsive as usage grows.

#### Acceptance Criteria

1. THE Emotion_Scorer SHALL process and store an emotional score within 2 seconds of message or entry creation
2. THE Daily_Aggregator SHALL complete daily summary calculations for all students within 10 minutes of midnight
3. THE Pattern_Detector SHALL complete pattern analysis for all students within 15 minutes of daily summary completion
4. THE Dashboard_Visualizer SHALL render emotional trajectory charts within 1 second of page load
5. THE System SHALL support concurrent emotion scoring for up to 100 simultaneous users
6. THE System SHALL maintain response times when the emotional_scores table contains over 1 million records

### Requirement 15: Alert Suppression Logic

**User Story:** As an alert management system, I want to prevent alert fatigue, so that counselors focus on new or escalating concerns rather than repetitive notifications.

#### Acceptance Criteria

1. WHEN a pattern alert is generated for a student, THE Alert_Manager SHALL check for existing unresolved alerts for that student
2. WHEN an unresolved alert of the same type exists within the past 7 days, THE Alert_Manager SHALL not create a duplicate alert
3. WHEN an unresolved alert exists but the new pattern shows increased severity, THE Alert_Manager SHALL update the existing alert severity and add a note
4. THE Alert_Manager SHALL allow a maximum of 3 active alerts per student at any time
5. WHEN the alert limit is reached, THE Alert_Manager SHALL prioritize the highest severity alerts and suppress lower severity ones
6. THE Alert_Manager SHALL log all suppressed alerts for audit purposes

## Parser and Serializer Requirements

### Requirement 16: Emotional Data Export

**User Story:** As a system administrator, I want to export emotional monitoring data, so that external analysis tools can process the information.

#### Acceptance Criteria

1. THE System SHALL provide a JSON export function for emotional_scores, daily_summaries, and pattern_alerts
2. WHEN export is requested, THE System SHALL serialize all records for the specified date range into valid JSON format
3. THE System SHALL include a JSON schema definition in the export file header
4. THE System SHALL provide a JSON import function that parses exported files back into the database
5. FOR ALL valid export files, importing then exporting then importing SHALL produce equivalent database records (round-trip property)
6. WHEN an invalid JSON file is provided to the import function, THE System SHALL return descriptive error messages indicating the validation failure

