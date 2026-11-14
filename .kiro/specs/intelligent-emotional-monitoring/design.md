# Design Document: Intelligent Emotional Monitoring System

## Overview

The Intelligent Emotional Monitoring System transforms MinSuansCore from a reactive crisis detection platform into a proactive mental wellness monitoring system. Instead of triggering immediate alerts on individual messages, the system tracks emotional trajectories over time, identifies meaningful patterns through multi-day analysis, and provides counselors with actionable insights through visual dashboards.

### Core Design Principles

1. **Pattern-Based Detection**: Replace single-event alerts with multi-day trend analysis
2. **Time-Weighted Scoring**: Emphasize late-night entries (22:00-05:00) as indicators of vulnerability
3. **Reliability Assessment**: Weight alerts based on student engagement levels
4. **Non-Intrusive Monitoring**: Students see minimal emotional data; counselors see comprehensive analytics
5. **Integration-First**: Leverage existing ML service (fast_ml_server.py) without modification

### Key Innovations

- **Emotional Trajectory Visualization**: Line charts showing 7/30/90-day emotional trends
- **Consecutive Days Detection**: Identify sustained negative patterns (3+ days low, 5+ days slightly low)
- **Sudden Drop Alerts**: Detect rapid emotional declines (10+ point drops from 3-day average)
- **Usage Pattern Analysis**: Assess data reliability based on daily entry frequency
- **Alert Suppression Logic**: Prevent counselor fatigue through intelligent deduplication

## Architecture

### System Components

```
┌─────────────────────────────────────────────────────────────────┐
│                     Student Interaction Layer                    │
│  ┌──────────────────┐              ┌──────────────────┐         │
│  │  Chat Messages   │              │ Journal Entries  │         │
│  └────────┬─────────┘              └────────┬─────────┘         │
└───────────┼──────────────────────────────────┼──────────────────┘
            │                                  │
            ▼                                  ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Emotion Scoring Pipeline                      │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │  1. ML Service Integration (fast_ml_server.py)           │   │
│  │     - HTTP POST to /analyze endpoint                     │   │
│  │     - Returns: emotion, confidence, crisis_level         │   │
│  │  2. Time Weighting Engine                                │   │
│  │     - Multiply score by 1.5 if 22:00-05:00              │   │
│  │  3. Score Mapping                                        │   │
│  │     - Happy: +2, Excited: +3, Calm: +1                  │   │
│  │     - Neutral: 0                                         │   │
│  │     - Sad: -2, Stressed: -2, Anxious: -3, Angry: -3     │   │
│  │  4. Database Storage (emotional_scores table)            │   │
│  └──────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
            │
            ▼
┌─────────────────────────────────────────────────────────────────┐
│                   Daily Aggregation Service                      │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │  Scheduled Task: Daily at 23:59                          │   │
│  │  1. Sum all emotional scores for each student            │   │
│  │  2. Classify mental status:                              │   │
│  │     - Stable: sum >= 0                                   │   │
│  │     - Slightly Low: -5 to -1                             │   │
│  │     - Low: -10 to -6                                     │   │
│  │     - Critical: <= -11                                   │   │
│  │  3. Calculate reliability flag (entry count)             │   │
│  │  4. Store in daily_summaries table                       │   │
│  └──────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
            │
            ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Pattern Detection Engine                      │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │  Scheduled Task: Daily at 00:15 (after aggregation)      │   │
│  │  1. Consecutive Days Detection                           │   │
│  │     - 3+ days "Low" or "Critical" → Alert               │   │
│  │     - 5+ days "Slightly Low" → Alert                    │   │
│  │  2. Sudden Drop Detection                                │   │
│  │     - Calculate 3-day rolling average                    │   │
│  │     - If current day drops 10+ points → High Alert      │   │
│  │  3. Alert Suppression Logic                              │   │
│  │     - Check for duplicate alerts within 7 days           │   │
│  │     - Max 3 active alerts per student                    │   │
│  │  4. Store in pattern_alerts table                        │   │
│  └──────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
            │
            ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Counselor Dashboard Layer                     │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────┐  │
│  │ Alert Management │  │ Trajectory Charts│  │ Student List │  │
│  │  - Unresolved    │  │  - 7/30/90 days  │  │  - Filtered  │  │
│  │  - By severity   │  │  - Color zones   │  │  - Sorted    │  │
│  │  - Acknowledge   │  │  - Pattern marks │  │  - Reliable  │  │
│  └──────────────────┘  └──────────────────┘  └──────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

### Data Flow Sequence

1. **Message/Entry Creation** → Emotion Scorer receives content
2. **ML Analysis** → fast_ml_server.py returns emotion + confidence
3. **Score Mapping** → Emotion label converted to numerical score (-3 to +3)
4. **Time Weighting** → Late-night entries multiplied by 1.5
5. **Storage** → emotional_scores table stores original + weighted scores
6. **Daily Aggregation** → Scheduled task sums scores, classifies status
7. **Pattern Detection** → Analyzes daily_summaries for trends
8. **Alert Generation** → Creates pattern_alerts for counselors
9. **Dashboard Rendering** → Visualizes trajectories and alerts

### Integration Points

#### Existing ML Service (fast_ml_server.py)
- **Endpoint**: `POST http://localhost:5000/analyze`
- **Request**: `{"text": "student message content"}`
- **Response**: 
  ```json
  {
    "emotion": "sad",
    "confidence": 85.5,
    "raw_label": "sadness",
    "crisis_level": "moderate",
    "contexts": ["academic_stress"]
  }
  ```
- **No modifications required** - service already provides all needed data

#### Existing EmotionDetectionService.php
- **Current**: Returns emotion + confidence + crisis_level
- **Enhancement**: Add new method `scoreEmotion()` that calls existing `detectEmotion()` and maps to numerical score
- **Backward Compatible**: Existing crisis detection remains unchanged

#### Existing Database Tables
- **emotional_entries**: Journal entries (unchanged)
- **counselor_messages**: Chat messages (unchanged)
- **New Tables**: emotional_scores, daily_summaries, pattern_alerts

## Components and Interfaces

### 1. Emotion Scorer Service

**File**: `app/Services/EmotionScorerService.php`

**Responsibilities**:
- Receive message/journal content from controllers
- Call EmotionDetectionService for ML analysis
- Map emotion labels to numerical scores
- Apply time-based weighting
- Store scores in emotional_scores table

**Public Methods**:

```php
class EmotionScorerService
{
    /**
     * Score a chat message and store result
     * 
     * @param int $messageId - counselor_messages.id
     * @param string $content - message text
     * @param int $userId - sender user_id
     * @return array ['score' => float, 'emotion' => string]
     */
    public function scoreChatMessage(int $messageId, string $content, int $userId): array;
    
    /**
     * Score a journal entry and store result
     * 
     * @param int $entryId - emotional_entries.id
     * @param string $content - entry text
     * @param int $userId - author user_id
     * @return array ['score' => float, 'emotion' => string]
     */
    public function scoreJournalEntry(int $entryId, string $content, int $userId): array;
    
    /**
     * Batch process historical data
     * 
     * @param string $contentType - 'message' or 'journal'
     * @param int $batchSize - records per batch (default 100)
     * @return array ['processed' => int, 'failed' => int]
     */
    public function batchProcessHistoricalData(string $contentType, int $batchSize = 100): array;
}
```

**Private Methods**:

```php
/**
 * Map emotion label to numerical score
 * 
 * @param string $emotion - happy, sad, anxious, angry, neutral, excited, calm, stressed
 * @return int - score between -3 and +3
 */
private function mapEmotionToScore(string $emotion): int;

/**
 * Apply time-based weighting to score
 * 
 * @param int $originalScore - base score
 * @param Carbon $timestamp - when content was created
 * @return float - weighted score (1 decimal place)
 */
private function applyTimeWeighting(int $originalScore, Carbon $timestamp): float;

/**
 * Store emotional score in database
 * 
 * @param array $data - [user_id, content_type, content_id, emotion_label, original_score, weighted_score, detected_at]
 * @return bool
 */
private function storeScore(array $data): bool;
```

**Emotion to Score Mapping**:
```php
private const EMOTION_SCORES = [
    'happy' => 2,
    'excited' => 3,
    'calm' => 1,
    'neutral' => 0,
    'sad' => -2,
    'stressed' => -2,
    'anxious' => -3,
    'angry' => -3,
];
```

### 2. Daily Aggregator Service

**File**: `app/Services/DailyAggregatorService.php`

**Responsibilities**:
- Run daily at 23:59 via Laravel scheduler
- Calculate daily mental status for all active students
- Assess data reliability based on entry count
- Store summaries in daily_summaries table

**Public Methods**:

```php
class DailyAggregatorService
{
    /**
     * Aggregate emotional scores for all students for a specific date
     * 
     * @param Carbon $date - date to aggregate (default: today)
     * @return array ['processed' => int, 'summaries_created' => int]
     */
    public function aggregateDaily(Carbon $date = null): array;
    
    /**
     * Aggregate for a single student
     * 
     * @param int $userId
     * @param Carbon $date
     * @return array|null - summary data or null if no entries
     */
    public function aggregateForStudent(int $userId, Carbon $date): ?array;
}
```

**Private Methods**:

```php
/**
 * Calculate mental status from total score
 * 
 * @param float $totalScore - sum of weighted scores
 * @return string - 'Stable', 'Slightly Low', 'Low', 'Critical'
 */
private function classifyMentalStatus(float $totalScore): string;

/**
 * Determine reliability flag from entry count
 * 
 * @param int $entryCount - number of entries in day
 * @return string - 'Low', 'Medium', 'High'
 */
private function assessReliability(int $entryCount): string;
```

**Status Classification Logic**:
```php
private function classifyMentalStatus(float $totalScore): string
{
    if ($totalScore >= 0) return 'Stable';
    if ($totalScore >= -5) return 'Slightly Low';
    if ($totalScore >= -10) return 'Low';
    return 'Critical';
}
```

### 3. Pattern Detector Service

**File**: `app/Services/PatternDetectorService.php`

**Responsibilities**:
- Run daily at 00:15 via Laravel scheduler
- Detect consecutive days patterns
- Detect sudden drop patterns
- Apply alert suppression logic
- Generate pattern_alerts for counselors

**Public Methods**:

```php
class PatternDetectorService
{
    /**
     * Analyze patterns for all students
     * 
     * @param Carbon $date - analysis date (default: today)
     * @return array ['students_analyzed' => int, 'alerts_generated' => int]
     */
    public function analyzePatterns(Carbon $date = null): array;
    
    /**
     * Analyze patterns for a single student
     * 
     * @param int $userId
     * @param Carbon $date
     * @return array - ['consecutive_days' => array|null, 'sudden_drop' => array|null]
     */
    public function analyzeStudentPatterns(int $userId, Carbon $date): array;
}
```

**Private Methods**:

```php
/**
 * Detect consecutive days of negative status
 * 
 * @param int $userId
 * @param Carbon $date
 * @return array|null - alert data or null if no pattern
 */
private function detectConsecutiveDays(int $userId, Carbon $date): ?array;

/**
 * Detect sudden drop in emotional score
 * 
 * @param int $userId
 * @param Carbon $date
 * @return array|null - alert data or null if no pattern
 */
private function detectSuddenDrop(int $userId, Carbon $date): ?array;

/**
 * Check if alert should be suppressed
 * 
 * @param int $userId
 * @param string $alertType
 * @return bool - true if should suppress
 */
private function shouldSuppressAlert(int $userId, string $alertType): bool;

/**
 * Create pattern alert in database
 * 
 * @param array $alertData
 * @return bool
 */
private function createAlert(array $alertData): bool;
```

**Consecutive Days Detection Algorithm**:
```php
private function detectConsecutiveDays(int $userId, Carbon $date): ?array
{
    // Get last 5 days of summaries
    $summaries = DailySummary::where('user_id', $userId)
        ->whereBetween('date', [$date->copy()->subDays(4), $date])
        ->orderBy('date', 'desc')
        ->get();
    
    // Check for 3 consecutive "Low" or "Critical"
    $consecutiveLowCount = 0;
    $consecutiveSlightlyLowCount = 0;
    
    foreach ($summaries as $summary) {
        if (in_array($summary->status, ['Low', 'Critical'])) {
            $consecutiveLowCount++;
            $consecutiveSlightlyLowCount = 0; // Reset
        } elseif ($summary->status === 'Slightly Low') {
            $consecutiveSlightlyLowCount++;
            $consecutiveLowCount = 0; // Reset
        } else {
            // Reset both on Stable day
            $consecutiveLowCount = 0;
            $consecutiveSlightlyLowCount = 0;
        }
    }
    
    if ($consecutiveLowCount >= 3) {
        return [
            'alert_type' => 'consecutive_low',
            'severity' => 'High',
            'pattern_description' => "{$consecutiveLowCount} consecutive days of Low/Critical status"
        ];
    }
    
    if ($consecutiveSlightlyLowCount >= 5) {
        return [
            'alert_type' => 'consecutive_slightly_low',
            'severity' => 'Medium',
            'pattern_description' => "{$consecutiveSlightlyLowCount} consecutive days of Slightly Low status"
        ];
    }
    
    return null;
}
```

**Sudden Drop Detection Algorithm**:
```php
private function detectSuddenDrop(int $userId, Carbon $date): ?array
{
    // Get last 4 days (3 for average + 1 current)
    $summaries = DailySummary::where('user_id', $userId)
        ->whereBetween('date', [$date->copy()->subDays(3), $date])
        ->orderBy('date', 'asc')
        ->get();
    
    if ($summaries->count() < 4) {
        return null; // Need at least 4 days of data
    }
    
    // Calculate 3-day rolling average (exclude current day)
    $previousThreeDays = $summaries->slice(0, 3);
    $rollingAverage = $previousThreeDays->avg('total_score');
    
    // Get current day score
    $currentDay = $summaries->last();
    $currentScore = $currentDay->total_score;
    
    // Check for 10+ point drop
    $drop = $rollingAverage - $currentScore;
    
    if ($drop >= 10) {
        return [
            'alert_type' => 'sudden_drop',
            'severity' => 'High',
            'pattern_description' => sprintf(
                "Sudden drop of %.1f points (from %.1f average to %.1f)",
                $drop,
                $rollingAverage,
                $currentScore
            )
        ];
    }
    
    return null;
}
```

### 4. Alert Manager Service

**File**: `app/Services/AlertManagerService.php`

**Responsibilities**:
- Manage pattern alert lifecycle
- Send notifications to counselors
- Handle alert acknowledgment and resolution
- Implement alert suppression logic

**Public Methods**:

```php
class AlertManagerService
{
    /**
     * Get unresolved alerts for counselor dashboard
     * 
     * @param string $sortBy - 'severity', 'date', 'student'
     * @return Collection - PatternAlert models
     */
    public function getUnresolvedAlerts(string $sortBy = 'severity'): Collection;
    
    /**
     * Update alert status
     * 
     * @param int $alertId
     * @param string $status - 'Acknowledged', 'In Progress', 'Resolved'
     * @param int $counselorId
     * @return bool
     */
    public function updateAlertStatus(int $alertId, string $status, int $counselorId): bool;
    
    /**
     * Send notification to counselors
     * 
     * @param PatternAlert $alert
     * @return bool
     */
    public function notifyCounselors(PatternAlert $alert): bool;
}
```

### 5. Dashboard Visualizer Service

**File**: `app/Services/DashboardVisualizerService.php`

**Responsibilities**:
- Generate emotional trajectory data for charts
- Calculate trend indicators
- Format data for frontend visualization libraries

**Public Methods**:

```php
class DashboardVisualizerService
{
    /**
     * Get emotional trajectory data for chart
     * 
     * @param int $userId
     * @param int $days - 7, 30, 90, or 0 (all time)
     * @return array - ['dates' => [], 'scores' => [], 'statuses' => [], 'alerts' => []]
     */
    public function getTrajectoryData(int $userId, int $days = 30): array;
    
    /**
     * Calculate trend indicator
     * 
     * @param int $userId
     * @param int $days - period for trend calculation
     * @return array - ['direction' => 'up'|'down'|'stable', 'change' => float]
     */
    public function calculateTrend(int $userId, int $days): array;
    
    /**
     * Get summary statistics
     * 
     * @param int $userId
     * @return array - ['current_status', 'avg_7day', 'avg_30day', 'reliability']
     */
    public function getSummaryStats(int $userId): array;
}
```

## Data Models

### Database Schema

#### emotional_scores Table

```sql
CREATE TABLE emotional_scores (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    content_type ENUM('message', 'journal') NOT NULL,
    content_id BIGINT UNSIGNED NOT NULL,
    emotion_label VARCHAR(50) NOT NULL,
    original_score INT NOT NULL,
    weighted_score DECIMAL(3,1) NOT NULL,
    detected_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_date (user_id, detected_at),
    INDEX idx_content (content_type, content_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

**Field Descriptions**:
- `user_id`: Student who created the content
- `content_type`: 'message' (counselor_messages) or 'journal' (emotional_entries)
- `content_id`: Foreign key to counselor_messages.id or emotional_entries.id
- `emotion_label`: ML-detected emotion (happy, sad, anxious, etc.)
- `original_score`: Base score before time weighting (-3 to +3)
- `weighted_score`: Score after time weighting (1.5x for late-night)
- `detected_at`: Timestamp when content was created (for time weighting)

#### daily_summaries Table

```sql
CREATE TABLE daily_summaries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    total_score DECIMAL(6,1) NOT NULL,
    status ENUM('Stable', 'Slightly Low', 'Low', 'Critical') NOT NULL,
    entry_count INT NOT NULL,
    reliability_flag ENUM('Low', 'Medium', 'High') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_user_date (user_id, date),
    INDEX idx_user_date (user_id, date),
    INDEX idx_status (status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

**Field Descriptions**:
- `user_id`: Student being summarized
- `date`: Date of summary (YYYY-MM-DD)
- `total_score`: Sum of all weighted_scores for the day
- `status`: Classified mental status based on total_score
- `entry_count`: Number of messages + journal entries for the day
- `reliability_flag`: Data reliability based on entry_count

#### pattern_alerts Table

```sql
CREATE TABLE pattern_alerts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    alert_type ENUM('consecutive_low', 'consecutive_slightly_low', 'sudden_drop') NOT NULL,
    severity ENUM('Medium', 'High') NOT NULL,
    pattern_description TEXT NOT NULL,
    detection_date DATE NOT NULL,
    status ENUM('Unresolved', 'Acknowledged', 'In Progress', 'Resolved') DEFAULT 'Unresolved',
    resolved_at TIMESTAMP NULL,
    counselor_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_status (user_id, status),
    INDEX idx_severity (severity),
    INDEX idx_detection_date (detection_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (counselor_id) REFERENCES users(id) ON DELETE SET NULL
);
```

**Field Descriptions**:
- `user_id`: Student with detected pattern
- `alert_type`: Type of pattern detected
- `severity`: Alert priority level
- `pattern_description`: Human-readable description with metrics
- `detection_date`: Date when pattern was detected
- `status`: Current alert lifecycle status
- `resolved_at`: Timestamp when marked resolved
- `counselor_id`: Counselor who resolved the alert

### Model Relationships

```php
// User.php
public function emotionalScores()
{
    return $this->hasMany(EmotionalScore::class);
}

public function dailySummaries()
{
    return $this->hasMany(DailySummary::class);
}

public function patternAlerts()
{
    return $this->hasMany(PatternAlert::class);
}

// EmotionalScore.php
public function user()
{
    return $this->belongsTo(User::class);
}

public function content()
{
    // Polymorphic relationship
    if ($this->content_type === 'message') {
        return $this->belongsTo(CounselorMessage::class, 'content_id');
    }
    return $this->belongsTo(EmotionalEntry::class, 'content_id');
}

// DailySummary.php
public function user()
{
    return $this->belongsTo(User::class);
}

public function emotionalScores()
{
    return $this->hasMany(EmotionalScore::class, 'user_id', 'user_id')
        ->whereDate('detected_at', $this->date);
}

// PatternAlert.php
public function user()
{
    return $this->belongsTo(User::class);
}

public function counselor()
{
    return $this->belongsTo(User::class, 'counselor_id');
}
```


## API Endpoints and Data Flow

### Student-Facing Endpoints

#### POST /student/chat/send
**Purpose**: Send chat message to counselor with emotion scoring

**Request**:
```json
{
  "counselor_id": 5,
  "message": "I've been feeling really stressed about my exams"
}
```

**Flow**:
1. Store message in counselor_messages table
2. Call EmotionScorerService.scoreChatMessage()
3. Return message confirmation to student

**Response**:
```json
{
  "success": true,
  "message_id": 123,
  "sent_at": "2026-03-15T14:30:00Z"
}
```

#### POST /student/journal/create
**Purpose**: Create journal entry with emotion scoring

**Request**:
```json
{
  "entry_text": "Today was really difficult. I couldn't focus on anything.",
  "is_anonymous": true
}
```

**Flow**:
1. Store entry in emotional_entries table
2. Call EmotionScorerService.scoreJournalEntry()
3. Return entry with emotional score

**Response**:
```json
{
  "success": true,
  "entry_id": 456,
  "emotion": "sad",
  "score": -2.0,
  "created_at": "2026-03-15T23:45:00Z"
}
```

#### GET /student/journal/entries
**Purpose**: View journal entries with emotional scores

**Response**:
```json
{
  "entries": [
    {
      "id": 456,
      "entry_text": "Today was really difficult...",
      "emotion": "sad",
      "score": -3.0,
      "trend": "down",
      "created_at": "2026-03-15T23:45:00Z"
    }
  ]
}
```

### Counselor-Facing Endpoints

#### GET /counselor/alerts
**Purpose**: View unresolved pattern alerts

**Query Parameters**:
- `sort_by`: severity | date | student (default: severity)
- `severity`: Medium | High (optional filter)

**Response**:
```json
{
  "alerts": [
    {
      "id": 789,
      "student": {
        "id": 12,
        "name": "Student Name",
        "email": "student@example.com"
      },
      "alert_type": "sudden_drop",
      "severity": "High",
      "pattern_description": "Sudden drop of 12.5 points (from 3.2 average to -9.3)",
      "detection_date": "2026-03-15",
      "status": "Unresolved",
      "created_at": "2026-03-15T00:15:00Z"
    }
  ]
}
```

#### PUT /counselor/alerts/{id}/status
**Purpose**: Update alert status

**Request**:
```json
{
  "status": "In Progress"
}
```

**Response**:
```json
{
  "success": true,
  "alert_id": 789,
  "status": "In Progress",
  "updated_at": "2026-03-15T10:30:00Z"
}
```

#### GET /counselor/student/{id}/trajectory
**Purpose**: View student emotional trajectory

**Query Parameters**:
- `days`: 7 | 30 | 90 | 0 (all time, default: 30)

**Response**:
```json
{
  "student_id": 12,
  "period_days": 30,
  "trajectory": {
    "dates": ["2026-02-14", "2026-02-15", ...],
    "scores": [2.5, 1.0, -3.5, ...],
    "statuses": ["Stable", "Stable", "Low", ...],
    "reliability": ["High", "Medium", "High", ...]
  },
  "alerts": [
    {
      "date": "2026-03-15",
      "type": "sudden_drop",
      "severity": "High"
    }
  ],
  "summary": {
    "current_status": "Low",
    "avg_7day": -2.3,
    "avg_30day": 0.5,
    "trend_7day": "down",
    "trend_30day": "stable"
  }
}
```

### Administrative Endpoints

#### POST /admin/emotion-scoring/batch-process
**Purpose**: Process historical data

**Request**:
```json
{
  "content_type": "journal",
  "batch_size": 100
}
```

**Response**:
```json
{
  "success": true,
  "processed": 1250,
  "failed": 3,
  "score_distribution": {
    "happy": 320,
    "sad": 450,
    "anxious": 280,
    "neutral": 200
  }
}
```

#### GET /admin/emotion-scoring/export
**Purpose**: Export emotional data as JSON

**Query Parameters**:
- `start_date`: YYYY-MM-DD
- `end_date`: YYYY-MM-DD
- `tables`: emotional_scores | daily_summaries | pattern_alerts (comma-separated)

**Response**: JSON file download

### Scheduled Tasks

#### Daily Aggregation Task
**Schedule**: Daily at 23:59
**Command**: `php artisan emotion:aggregate-daily`
**Service**: DailyAggregatorService::aggregateDaily()

#### Pattern Detection Task
**Schedule**: Daily at 00:15
**Command**: `php artisan emotion:detect-patterns`
**Service**: PatternDetectorService::analyzePatterns()

#### Alert Notification Task
**Schedule**: Every 15 minutes
**Command**: `php artisan emotion:notify-counselors`
**Service**: AlertManagerService::notifyCounselors()

## Error Handling

### ML Service Failures

**Scenario**: fast_ml_server.py is unavailable

**Handling**:
```php
try {
    $emotion = $this->emotionDetectionService->detectEmotion($text);
} catch (MLServiceException $e) {
    Log::error('ML service unavailable', ['error' => $e->getMessage()]);
    
    // Fallback to neutral score
    $emotion = [
        'emotion' => 'neutral',
        'confidence' => 0.5,
        'method' => 'fallback'
    ];
}
```

**Impact**: System continues with neutral scores; no data loss

### Database Constraint Violations

**Scenario**: Duplicate daily_summary for same user/date

**Handling**:
```php
try {
    DailySummary::create($data);
} catch (QueryException $e) {
    if ($e->getCode() === '23000') { // Duplicate entry
        // Update existing record instead
        DailySummary::where('user_id', $data['user_id'])
            ->where('date', $data['date'])
            ->update($data);
    } else {
        throw $e;
    }
}
```

### Missing Historical Data

**Scenario**: Student has < 3 days of data for sudden drop detection

**Handling**:
```php
if ($summaries->count() < 4) {
    Log::info("Insufficient data for sudden drop detection", [
        'user_id' => $userId,
        'days_available' => $summaries->count()
    ]);
    return null; // Skip detection, no alert generated
}
```

### Alert Suppression Edge Cases

**Scenario**: Student has 3 active alerts, new high-severity alert detected

**Handling**:
```php
$activeAlerts = PatternAlert::where('user_id', $userId)
    ->where('status', 'Unresolved')
    ->orderBy('severity', 'asc')
    ->get();

if ($activeAlerts->count() >= 3) {
    // Replace lowest severity alert if new alert is higher
    $lowestAlert = $activeAlerts->first();
    if ($newSeverity === 'High' && $lowestAlert->severity === 'Medium') {
        $lowestAlert->update(['status' => 'Suppressed']);
        // Create new high-severity alert
    } else {
        Log::info('Alert suppressed due to limit', [
            'user_id' => $userId,
            'alert_type' => $newAlertType
        ]);
        return false;
    }
}
```

### Time Zone Handling

**Scenario**: Server timezone differs from student timezone

**Handling**:
```php
// Use server timezone consistently for all calculations
$timestamp = Carbon::parse($content->created_at, config('app.timezone'));

// For late-night detection (22:00-05:00)
$hour = $timestamp->hour;
$isLateNight = ($hour >= 22 || $hour < 5);
```

**Note**: All timestamps stored in UTC; converted to server timezone for weighting calculations

### Privacy and Access Control

**Scenario**: Student attempts to view another student's emotional data

**Handling**:
```php
// Middleware: EnsureStudentOwnsData
public function handle($request, Closure $next)
{
    $userId = $request->route('userId');
    
    if (auth()->user()->role === 'student' && auth()->id() !== $userId) {
        abort(403, 'Unauthorized access to emotional data');
    }
    
    return $next($request);
}
```

**Scenario**: Counselor accesses pattern alert

**Handling**:
```php
// Log all counselor access to alerts
PatternAlertAccessLog::create([
    'alert_id' => $alertId,
    'counselor_id' => auth()->id(),
    'action' => 'view',
    'timestamp' => now()
]);
```

## Testing Strategy

### Unit Testing Approach

Unit tests focus on specific examples, edge cases, and error conditions. Property-based tests handle comprehensive input coverage through randomization.

#### Emotion Scorer Service Tests

**File**: `tests/Unit/Services/EmotionScorerServiceTest.php`

```php
class EmotionScorerServiceTest extends TestCase
{
    /** @test */
    public function it_maps_happy_emotion_to_positive_score()
    {
        $service = new EmotionScorerService();
        $score = $service->mapEmotionToScore('happy');
        $this->assertEquals(2, $score);
    }
    
    /** @test */
    public function it_applies_time_weighting_for_late_night_entries()
    {
        $service = new EmotionScorerService();
        $timestamp = Carbon::parse('2026-03-15 23:30:00');
        $weightedScore = $service->applyTimeWeighting(-2, $timestamp);
        $this->assertEquals(-3.0, $weightedScore); // -2 * 1.5 = -3.0
    }
    
    /** @test */
    public function it_does_not_apply_weighting_for_daytime_entries()
    {
        $service = new EmotionScorerService();
        $timestamp = Carbon::parse('2026-03-15 14:30:00');
        $weightedScore = $service->applyTimeWeighting(-2, $timestamp);
        $this->assertEquals(-2.0, $weightedScore);
    }
    
    /** @test */
    public function it_handles_ml_service_failure_gracefully()
    {
        $this->mock(EmotionDetectionService::class)
            ->shouldReceive('detectEmotion')
            ->andThrow(new Exception('ML service unavailable'));
        
        $service = new EmotionScorerService();
        $result = $service->scoreChatMessage(1, 'test message', 1);
        
        $this->assertEquals('neutral', $result['emotion']);
        $this->assertEquals(0, $result['score']);
    }
}
```

#### Daily Aggregator Service Tests

**File**: `tests/Unit/Services/DailyAggregatorServiceTest.php`

```php
class DailyAggregatorServiceTest extends TestCase
{
    /** @test */
    public function it_classifies_positive_total_as_stable()
    {
        $service = new DailyAggregatorService();
        $status = $service->classifyMentalStatus(5.0);
        $this->assertEquals('Stable', $status);
    }
    
    /** @test */
    public function it_classifies_zero_total_as_stable()
    {
        $service = new DailyAggregatorService();
        $status = $service->classifyMentalStatus(0.0);
        $this->assertEquals('Stable', $status);
    }
    
    /** @test */
    public function it_classifies_critical_threshold_correctly()
    {
        $service = new DailyAggregatorService();
        $status = $service->classifyMentalStatus(-11.0);
        $this->assertEquals('Critical', $status);
    }
    
    /** @test */
    public function it_assesses_reliability_based_on_entry_count()
    {
        $service = new DailyAggregatorService();
        
        $this->assertEquals('Low', $service->assessReliability(1));
        $this->assertEquals('Medium', $service->assessReliability(3));
        $this->assertEquals('High', $service->assessReliability(6));
    }
    
    /** @test */
    public function it_does_not_create_summary_for_zero_entries()
    {
        $user = User::factory()->create();
        $date = Carbon::today();
        
        $service = new DailyAggregatorService();
        $result = $service->aggregateForStudent($user->id, $date);
        
        $this->assertNull($result);
        $this->assertDatabaseMissing('daily_summaries', [
            'user_id' => $user->id,
            'date' => $date->toDateString()
        ]);
    }
}
```

#### Pattern Detector Service Tests

**File**: `tests/Unit/Services/PatternDetectorServiceTest.php`

```php
class PatternDetectorServiceTest extends TestCase
{
    /** @test */
    public function it_detects_three_consecutive_low_days()
    {
        $user = User::factory()->create();
        $date = Carbon::today();
        
        // Create 3 consecutive "Low" days
        for ($i = 2; $i >= 0; $i--) {
            DailySummary::factory()->create([
                'user_id' => $user->id,
                'date' => $date->copy()->subDays($i),
                'status' => 'Low',
                'total_score' => -8.0
            ]);
        }
        
        $service = new PatternDetectorService();
        $patterns = $service->analyzeStudentPatterns($user->id, $date);
        
        $this->assertNotNull($patterns['consecutive_days']);
        $this->assertEquals('consecutive_low', $patterns['consecutive_days']['alert_type']);
        $this->assertEquals('High', $patterns['consecutive_days']['severity']);
    }
    
    /** @test */
    public function it_detects_sudden_drop_pattern()
    {
        $user = User::factory()->create();
        $date = Carbon::today();
        
        // Create 3 days with positive scores (average = 3.0)
        DailySummary::factory()->create(['user_id' => $user->id, 'date' => $date->copy()->subDays(3), 'total_score' => 2.0]);
        DailySummary::factory()->create(['user_id' => $user->id, 'date' => $date->copy()->subDays(2), 'total_score' => 3.0]);
        DailySummary::factory()->create(['user_id' => $user->id, 'date' => $date->copy()->subDays(1), 'total_score' => 4.0]);
        
        // Current day drops to -8.0 (drop of 11 points)
        DailySummary::factory()->create(['user_id' => $user->id, 'date' => $date, 'total_score' => -8.0]);
        
        $service = new PatternDetectorService();
        $patterns = $service->analyzeStudentPatterns($user->id, $date);
        
        $this->assertNotNull($patterns['sudden_drop']);
        $this->assertEquals('sudden_drop', $patterns['sudden_drop']['alert_type']);
        $this->assertEquals('High', $patterns['sudden_drop']['severity']);
    }
    
    /** @test */
    public function it_does_not_detect_pattern_with_insufficient_data()
    {
        $user = User::factory()->create();
        $date = Carbon::today();
        
        // Only 2 days of data
        DailySummary::factory()->create(['user_id' => $user->id, 'date' => $date->copy()->subDays(1), 'total_score' => 3.0]);
        DailySummary::factory()->create(['user_id' => $user->id, 'date' => $date, 'total_score' => -8.0]);
        
        $service = new PatternDetectorService();
        $patterns = $service->analyzeStudentPatterns($user->id, $date);
        
        $this->assertNull($patterns['sudden_drop']);
    }
    
    /** @test */
    public function it_suppresses_duplicate_alerts_within_seven_days()
    {
        $user = User::factory()->create();
        
        // Create existing alert from 3 days ago
        PatternAlert::factory()->create([
            'user_id' => $user->id,
            'alert_type' => 'consecutive_low',
            'status' => 'Unresolved',
            'detection_date' => Carbon::today()->subDays(3)
        ]);
        
        $service = new PatternDetectorService();
        $shouldSuppress = $service->shouldSuppressAlert($user->id, 'consecutive_low');
        
        $this->assertTrue($shouldSuppress);
    }
}
```

### Property-Based Testing Configuration

**Library**: [Pest PHP](https://pestphp.com/) with [Pest Property Testing Plugin](https://github.com/pestphp/pest-plugin-property-testing)

**Installation**:
```bash
composer require pestphp/pest --dev
composer require pestphp/pest-plugin-property-testing --dev
```

**Configuration**: Each property test runs minimum 100 iterations

**Test File Structure**:
```
tests/
├── Unit/
│   └── Services/
│       ├── EmotionScorerServiceTest.php
│       ├── DailyAggregatorServiceTest.php
│       └── PatternDetectorServiceTest.php
└── Property/
    └── EmotionalMonitoringPropertiesTest.php
```

### Integration Testing

**File**: `tests/Feature/EmotionalMonitoringIntegrationTest.php`

```php
class EmotionalMonitoringIntegrationTest extends TestCase
{
    /** @test */
    public function it_processes_message_through_complete_pipeline()
    {
        $student = User::factory()->create(['role' => 'student']);
        $counselor = User::factory()->create(['role' => 'counselor']);
        
        // Send message
        $response = $this->actingAs($student)
            ->postJson('/student/chat/send', [
                'counselor_id' => $counselor->id,
                'message' => 'I am feeling very anxious about my exams'
            ]);
        
        $response->assertStatus(200);
        
        // Verify message stored
        $this->assertDatabaseHas('counselor_messages', [
            'student_id' => $student->id,
            'message' => 'I am feeling very anxious about my exams'
        ]);
        
        // Verify emotional score created
        $this->assertDatabaseHas('emotional_scores', [
            'user_id' => $student->id,
            'content_type' => 'message',
            'emotion_label' => 'anxious'
        ]);
        
        $score = EmotionalScore::where('user_id', $student->id)->first();
        $this->assertEquals(-3, $score->original_score);
    }
    
    /** @test */
    public function it_generates_daily_summary_and_detects_patterns()
    {
        $student = User::factory()->create(['role' => 'student']);
        $date = Carbon::today();
        
        // Create multiple negative scores throughout the day
        for ($i = 0; $i < 5; $i++) {
            EmotionalScore::factory()->create([
                'user_id' => $student->id,
                'original_score' => -2,
                'weighted_score' => -2.0,
                'detected_at' => $date->copy()->addHours($i * 2)
            ]);
        }
        
        // Run aggregation
        $aggregator = new DailyAggregatorService();
        $aggregator->aggregateDaily($date);
        
        // Verify summary created
        $summary = DailySummary::where('user_id', $student->id)
            ->where('date', $date->toDateString())
            ->first();
        
        $this->assertNotNull($summary);
        $this->assertEquals(-10.0, $summary->total_score);
        $this->assertEquals('Low', $summary->status);
        
        // Create 2 more consecutive low days
        DailySummary::factory()->create([
            'user_id' => $student->id,
            'date' => $date->copy()->addDay(),
            'status' => 'Low',
            'total_score' => -8.0
        ]);
        
        DailySummary::factory()->create([
            'user_id' => $student->id,
            'date' => $date->copy()->addDays(2),
            'status' => 'Low',
            'total_score' => -9.0
        ]);
        
        // Run pattern detection
        $detector = new PatternDetectorService();
        $detector->analyzePatterns($date->copy()->addDays(2));
        
        // Verify alert created
        $this->assertDatabaseHas('pattern_alerts', [
            'user_id' => $student->id,
            'alert_type' => 'consecutive_low',
            'severity' => 'High'
        ]);
    }
}
```

### Performance Testing

**Scenario**: System handles 100 concurrent emotion scoring requests

**Test**:
```php
/** @test */
public function it_handles_concurrent_emotion_scoring()
{
    $students = User::factory()->count(100)->create(['role' => 'student']);
    
    $startTime = microtime(true);
    
    foreach ($students as $student) {
        dispatch(new ScoreEmotionJob($student->id, 'test message', 'message', 1));
    }
    
    // Wait for all jobs to complete
    Queue::assertPushed(ScoreEmotionJob::class, 100);
    
    $endTime = microtime(true);
    $duration = $endTime - $startTime;
    
    // Should complete within 10 seconds
    $this->assertLessThan(10, $duration);
}
```


## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property Reflection

After analyzing all acceptance criteria, I identified the following redundancies:

- **Emotion Score Range**: Properties 1.1 and 1.2 both test score range validation. Combined into single property covering all content types.
- **Status Classification**: Properties 2.3-2.6 test specific classification thresholds. These are better tested as examples rather than separate properties. The general classification logic is captured in one property.
- **Reliability Classification**: Properties 6.2-6.4 test specific reliability thresholds. Combined into examples. The general reliability logic is one property.
- **Time Weighting**: Properties 5.1 and 5.2 test late-night vs daytime weighting. Combined into single property covering all time periods.
- **Consecutive Days Detection**: Properties 3.2 and 3.3 both test consecutive day patterns. Combined into single property covering all consecutive patterns.
- **Data Persistence**: Properties 1.5, 2.7, 3.5, 5.6 all test that required fields are stored. Combined into properties specific to each table.
- **Alert Suppression**: Properties 9.5 and 15.2 are identical. Kept as single property.
- **Emotion Mapping**: Property 1.4 and 12.2 are the same. Kept as single property.

### Property 1: Emotion Score Range Validation

*For any* message or journal entry, when scored by the Emotion_Scorer, the resulting score SHALL be between -3 and +3 inclusive.

**Validates: Requirements 1.1, 1.2**

### Property 2: Emotion Label Mapping Consistency

*For any* emotion label detected by the ML service, the Emotion_Scorer SHALL consistently map it to the same numerical score according to the defined mapping (Happy: +2, Excited: +3, Calm: +1, Neutral: 0, Sad: -2, Stressed: -2, Anxious: -3, Angry: -3).

**Validates: Requirements 1.4**

### Property 3: Emotional Score Persistence

*For any* scored content (message or journal entry), the emotional_scores table SHALL contain a record with all required fields: user_id, content_type, content_id, emotion_label, original_score, weighted_score, and detected_at.

**Validates: Requirements 1.5**

### Property 4: Time Weighting Application

*For any* emotional score, if the content was created between 22:00 and 05:00, the weighted_score SHALL equal original_score × 1.5 (rounded to 1 decimal place); otherwise weighted_score SHALL equal original_score.

**Validates: Requirements 5.1, 5.2, 5.5**

### Property 5: Time Weighting Dual Storage

*For any* emotional score record, both original_score and weighted_score SHALL be stored in the database, and weighted_score SHALL always be greater than or equal to original_score in absolute value.

**Validates: Requirements 5.6**

### Property 6: Daily Score Aggregation Accuracy

*For any* student and date, the total_score in the daily_summaries table SHALL equal the sum of all weighted_scores from the emotional_scores table for that student on that date.

**Validates: Requirements 2.2**

### Property 7: Daily Summary Persistence

*For any* daily summary created, the daily_summaries table SHALL contain a record with all required fields: user_id, date, total_score, status, entry_count, and reliability_flag.

**Validates: Requirements 2.7**

### Property 8: Entry Count Accuracy

*For any* daily summary, the entry_count field SHALL equal the number of emotional_scores records for that user on that date.

**Validates: Requirements 6.1**

### Property 9: Status Classification Correctness

*For any* total_score value, the classified status SHALL be: "Stable" if score ≥ 0, "Slightly Low" if -5 ≤ score ≤ -1, "Low" if -10 ≤ score ≤ -6, or "Critical" if score ≤ -11.

**Validates: Requirements 2.3, 2.4, 2.5, 2.6**

### Property 10: Reliability Assessment Correctness

*For any* entry_count value, the reliability_flag SHALL be: "Low" if count < 2, "Medium" if 2 ≤ count ≤ 5, or "High" if count > 5.

**Validates: Requirements 6.2, 6.3, 6.4**

### Property 11: Consecutive Days Pattern Detection

*For any* student with 3 or more consecutive days of "Low" or "Critical" status, OR 5 or more consecutive days of "Slightly Low" status, the Pattern_Detector SHALL generate a pattern alert.

**Validates: Requirements 3.2, 3.3**

### Property 12: Single-Day Negative Status Ignored

*For any* student with a single day of negative status (Low, Critical, or Slightly Low) surrounded by Stable days, the Pattern_Detector SHALL NOT generate a consecutive days alert.

**Validates: Requirements 3.4**

### Property 13: Pattern Alert Persistence

*For any* generated pattern alert, the pattern_alerts table SHALL contain a record with all required fields: user_id, alert_type, severity, pattern_description, detection_date, and status.

**Validates: Requirements 3.5**

### Property 14: Rolling Average Calculation

*For any* 3-day window of daily summaries, the calculated rolling average SHALL equal the arithmetic mean of the three total_score values.

**Validates: Requirements 4.1**

### Property 15: Sudden Drop Detection

*For any* student whose current day total_score is 10 or more points below their 3-day rolling average, the Pattern_Detector SHALL generate a "sudden_drop" alert with "High" severity.

**Validates: Requirements 4.2, 4.4**

### Property 16: Sudden Drop Description Completeness

*For any* sudden drop alert, the pattern_description field SHALL contain both the previous 3-day average and the current day score.

**Validates: Requirements 4.5**

### Property 17: Trend Indicator Calculation

*For any* two consecutive journal entries, the trend indicator SHALL be "up" if the later entry has a higher score, "down" if lower, or "neutral" if equal.

**Validates: Requirements 7.4**

### Property 18: Trajectory Data Completeness

*For any* requested date range (7, 30, 90 days), the trajectory data SHALL include arrays of equal length for dates, scores, statuses, and reliability flags, with one element per day in the range.

**Validates: Requirements 8.4, 8.6**

### Property 19: Alert Sorting Correctness

*For any* set of unresolved alerts, when sorted by severity then date, all "High" severity alerts SHALL appear before "Medium" severity alerts, and within each severity level, alerts SHALL be ordered by detection_date descending (newest first).

**Validates: Requirements 9.1**

### Property 20: Alert Status Transition

*For any* pattern alert, when its status is updated to "Acknowledged", "In Progress", or "Resolved", the status field SHALL be updated and if status is "Resolved", both resolved_at and counselor_id SHALL be set.

**Validates: Requirements 9.3, 9.4**

### Property 21: Duplicate Alert Suppression

*For any* student with an existing unresolved alert of a specific type, if a new alert of the same type is detected within 7 days, the Alert_Manager SHALL NOT create a duplicate alert.

**Validates: Requirements 9.5**

### Property 22: Alert Severity Escalation

*For any* student with an existing unresolved alert, if a new alert of the same type is detected with higher severity, the existing alert's severity SHALL be updated to the higher value.

**Validates: Requirements 15.3**

### Property 23: Maximum Active Alerts Per Student

*For any* student at any point in time, the number of unresolved pattern alerts SHALL NOT exceed 3.

**Validates: Requirements 15.4**

### Property 24: Alert Prioritization at Limit

*For any* student with 3 active alerts, when a new "High" severity alert is detected, if the lowest existing alert is "Medium" severity, the "Medium" alert SHALL be suppressed and the new "High" alert SHALL be created.

**Validates: Requirements 15.5**

### Property 25: Suppressed Alert Logging

*For any* alert that is suppressed (either due to duplication or limit), an audit log entry SHALL be created recording the suppression.

**Validates: Requirements 15.6**

### Property 26: Batch Processing Completeness

*For any* batch processing operation, all chat_messages and journal_entries without corresponding emotional_scores records SHALL be processed and scored.

**Validates: Requirements 10.2**

### Property 27: Batch Processing Chronological Order

*For any* batch processing operation, entries SHALL be processed in chronological order based on their created_at timestamp, from oldest to newest.

**Validates: Requirements 10.3**

### Property 28: Batch Processing Report Accuracy

*For any* completed batch processing operation, the summary report's total_processed count SHALL equal the number of new emotional_scores records created.

**Validates: Requirements 10.5**

### Property 29: Historical Time Weighting

*For any* historical entry processed in batch mode, the time weighting SHALL be calculated based on the entry's original created_at timestamp, not the batch processing time.

**Validates: Requirements 10.6**

### Property 30: Access Audit Logging

*For any* access to the pattern_alerts table (view, update, delete), an audit log entry SHALL be created containing user_id, timestamp, action, and alert_id.

**Validates: Requirements 13.4**

### Property 31: Cascade Deletion

*For any* deleted user account, all associated records in emotional_scores, daily_summaries, and pattern_alerts tables SHALL be deleted.

**Validates: Requirements 13.6**

### Property 32: JSON Export Validity

*For any* export operation, the generated output SHALL be valid JSON that can be parsed without errors.

**Validates: Requirements 16.2**

### Property 33: JSON Export Schema Inclusion

*For any* exported JSON file, the file SHALL include a schema definition in the header describing the structure of the exported data.

**Validates: Requirements 16.3**

### Property 34: JSON Import/Export Round-Trip

*For any* valid emotional monitoring data, exporting to JSON then importing back into the database SHALL produce equivalent records (same user_id, date, scores, statuses, and alert types).

**Validates: Requirements 16.5**

