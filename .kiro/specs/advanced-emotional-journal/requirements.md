# Requirements Document: Advanced Emotional Journal

## 1. Functional Requirements

### 1.1 Journal Entry Management

**1.1.1** The system SHALL allow users to create journal entries with the following fields:
- Title (optional, max 200 characters)
- Content (required, 10-10000 characters)
- Journaling mode (required: quick_vent, deep_reflection, or gratitude)
- Primary mood (required)
- Mood intensity (required, 1-10 scale)
- Sub-emotions (optional, max 5)
- Tags (optional, multiple allowed)

**1.1.2** The system SHALL generate a title automatically from content if user does not provide one

**1.1.3** The system SHALL calculate and store word count for each entry

**1.1.4** The system SHALL allow users to view, edit, and delete their own journal entries

**1.1.5** The system SHALL prevent users from accessing other users' journal entries

**1.1.6** The system SHALL support pagination of journal entries (20 per page)

### 1.2 AI-Powered Features

**1.2.1** The system SHALL generate AI reflection for each completed entry

**1.2.2** The system SHALL provide thought reframing suggestions using cognitive behavioral techniques

**1.2.3** The system SHALL generate contextual advice based on entry content and mood

**1.2.4** The system SHALL provide three perspective types:
- Friend perspective (supportive and empathetic)
- Future self perspective (long-term outlook)
- Mentor perspective (constructive and educational)



**1.2.5** The system SHALL handle ML service failures gracefully by:
- Displaying fallback message to user
- Queuing entry for background AI processing
- Notifying user when AI responses become available

### 1.3 Guided Writing Prompts

**1.3.1** The system SHALL provide mode-specific writing prompts:
- Quick vent prompts (immediate emotional release)
- Deep reflection prompts (introspective questions)
- Gratitude prompts (positive focus)

**1.3.2** The system SHALL implement "Mood Shuffle" feature to generate alternative prompts

**1.3.3** The system SHALL ensure shuffled prompts are different from current prompt

**1.3.4** The system SHALL support personalized prompts based on user history

### 1.4 Advanced Mood Tracking

**1.4.1** The system SHALL record mood with intensity level (1-10 scale)

**1.4.2** The system SHALL support multiple sub-emotions per entry (max 5)

**1.4.3** The system SHALL link mood records to journal entries

**1.4.4** The system SHALL analyze mood patterns over time (minimum 7 days of data)

**1.4.5** The system SHALL provide mood insights and trends

**1.4.6** The system SHALL visualize mood distribution with charts



### 1.5 Smart Tagging System

**1.5.1** The system SHALL allow users to add multiple tags to entries

**1.5.2** The system SHALL validate tag names (max 30 characters, alphanumeric with spaces)

**1.5.3** The system SHALL generate color codes for tags automatically

**1.5.4** The system SHALL allow filtering entries by tags

**1.5.5** The system SHALL suggest frequently used tags to users

### 1.6 Auto-Save Functionality

**1.6.1** The system SHALL auto-save draft entries every 30 seconds while user is typing

**1.6.2** The system SHALL maintain only one draft per user at any time

**1.6.3** The system SHALL restore draft when user returns to journal

**1.6.4** The system SHALL delete draft after successful entry submission

**1.6.5** The system SHALL detect and handle multi-tab editing conflicts

**1.6.6** The system SHALL display last saved timestamp to user

### 1.7 Voice Input Support

**1.7.1** The system SHALL accept audio file uploads in formats: mp3, wav, m4a, webm

**1.7.2** The system SHALL enforce audio file size limit of 10MB

**1.7.3** The system SHALL enforce audio duration limit of 5 minutes

**1.7.4** The system SHALL transcribe audio to text using speech-to-text service

**1.7.5** The system SHALL handle transcription failures with user-friendly error messages

**1.7.6** The system SHALL allow users to edit transcribed text before submission



### 1.8 Message to Future Me

**1.8.1** The system SHALL allow users to create messages for future delivery

**1.8.2** The system SHALL validate that delivery date is in the future

**1.8.3** The system SHALL enforce message content limits (10-5000 characters)

**1.8.4** The system SHALL deliver messages on scheduled date via:
- In-app notification
- Email notification

**1.8.5** The system SHALL retry failed email deliveries up to 3 times

**1.8.6** The system SHALL allow users to view pending future messages

**1.8.7** The system SHALL allow users to cancel undelivered future messages

**1.8.8** The system SHALL mark messages as delivered with timestamp

### 1.9 Entry Reactions

**1.9.1** The system SHALL support three reaction types: like, helpful, insightful

**1.9.2** The system SHALL enforce one reaction per type per user per entry

**1.9.3** The system SHALL allow users to remove their reactions

**1.9.4** The system SHALL display reaction counts on entries

### 1.10 Reflection Updates

**1.10.1** The system SHALL allow users to add reflection updates to past entries

**1.10.2** The system SHALL validate reflection content (10-2000 characters)

**1.10.3** The system SHALL timestamp each reflection update

**1.10.4** The system SHALL display reflection updates chronologically



## 2. Non-Functional Requirements

### 2.1 Performance

**2.1.1** The system SHALL complete entry creation within 2 seconds (including AI generation)

**2.1.2** The system SHALL complete auto-save operations within 500ms

**2.1.3** The system SHALL complete prompt shuffle within 200ms

**2.1.4** The system SHALL complete voice transcription within 5 seconds for 1-minute audio

**2.1.5** The system SHALL complete mood pattern analysis within 1 second for 90 days of data

**2.1.6** The system SHALL cache writing prompts with 1-hour TTL

**2.1.7** The system SHALL use database indexing on user_id, created_at, mode, and primary_mood columns

### 2.2 Security

**2.2.1** The system SHALL encrypt journal entry content at rest using AES-256

**2.2.2** The system SHALL encrypt AI responses and mood data

**2.2.3** The system SHALL verify user authentication on all journal endpoints

**2.2.4** The system SHALL implement CSRF protection on all state-changing requests

**2.2.5** The system SHALL sanitize all user input to prevent XSS attacks

**2.2.6** The system SHALL validate file uploads for type, size, and content

**2.2.7** The system SHALL implement rate limiting (10 requests/minute for entry creation)

**2.2.8** The system SHALL enforce server-side validation of all input constraints

**2.2.9** The system SHALL log all entry operations with timestamps for audit purposes



### 2.3 Reliability

**2.3.1** The system SHALL handle ML service failures without data loss

**2.3.2** The system SHALL queue failed operations for retry with exponential backoff

**2.3.3** The system SHALL cache entry data in Redis if database is unavailable

**2.3.4** The system SHALL implement soft deletes with 30-day recovery window

**2.3.5** The system SHALL maintain data integrity during concurrent operations

### 2.4 Usability

**2.4.1** The system SHALL provide visual feedback for auto-save operations

**2.4.2** The system SHALL display user-friendly error messages for all failure scenarios

**2.4.3** The system SHALL use mood-based color gradients for visual appeal

**2.4.4** The system SHALL implement smooth animations for UI transitions

**2.4.5** The system SHALL maintain clean, professional design focused on emotional support

**2.4.6** The system SHALL be responsive and functional on mobile devices

### 2.5 Scalability

**2.5.1** The system SHALL support horizontal scaling via database partitioning by user_id

**2.5.2** The system SHALL use read replicas for analytics queries

**2.5.3** The system SHALL enforce rate limit of 100 entries per user per day

**2.5.4** The system SHALL archive entries older than 2 years to cold storage



### 2.6 Compliance

**2.6.1** The system SHALL comply with GDPR requirements for data privacy

**2.6.2** The system SHALL allow users to permanently delete their entries

**2.6.3** The system SHALL anonymize deleted user data after 90 days

**2.6.4** The system SHALL provide data export functionality in JSON format

**2.6.5** The system SHALL obtain explicit user consent before sharing entries with counselors

## 3. Data Requirements

### 3.1 Database Tables

**3.1.1** The system SHALL maintain the following database tables:
- journal_entries (main entry storage)
- mood_records (mood tracking data)
- entry_tags (tag associations)
- writing_prompts (prompt library)
- future_messages (scheduled messages)
- entry_reactions (user reactions)
- entry_reflections (reflection updates)
- drafts (auto-saved drafts)

**3.1.2** The system SHALL enforce referential integrity between related tables

**3.1.3** The system SHALL use appropriate data types and constraints for all columns

### 3.2 Data Validation

**3.2.1** The system SHALL validate all data constraints at both client and server side

**3.2.2** The system SHALL enforce the following constraints:
- Entry content: 10-10000 characters
- Title: max 200 characters
- Mood intensity: 1-10 integer
- Sub-emotions: max 5 items
- Tags: max 30 characters each
- Future message: delivery date must be future
- Reaction type: must be 'like', 'helpful', or 'insightful'



## 4. Integration Requirements

### 4.1 ML Service Integration

**4.1.1** The system SHALL integrate with existing ML service API for emotion detection

**4.1.2** The system SHALL integrate with AI service for response generation

**4.1.3** The system SHALL handle ML service timeouts (max 5 seconds wait)

**4.1.4** The system SHALL implement circuit breaker pattern for ML service calls

### 4.2 Speech-to-Text Integration

**4.2.1** The system SHALL integrate with speech-to-text service (Google Cloud Speech-to-Text, AWS Transcribe, or Azure Speech)

**4.2.2** The system SHALL support multiple languages for transcription

**4.2.3** The system SHALL handle transcription service errors gracefully

### 4.3 Email Service Integration

**4.3.1** The system SHALL integrate with email service (SendGrid, Mailgun, or AWS SES)

**4.3.2** The system SHALL send email notifications for future message delivery

**4.3.3** The system SHALL track email delivery status

### 4.4 Queue System Integration

**4.4.1** The system SHALL use Laravel Queue for background job processing

**4.4.2** The system SHALL queue AI response generation for async processing

**4.4.3** The system SHALL queue future message delivery checks

**4.4.4** The system SHALL use Laravel Horizon for queue monitoring



## 5. User Interface Requirements

### 5.1 Journal Entry Interface

**5.1.1** The system SHALL provide a rich text editor for entry composition

**5.1.2** The system SHALL display character count and word count in real-time

**5.1.3** The system SHALL show auto-save indicator with last saved timestamp

**5.1.4** The system SHALL provide mode selection buttons (quick vent, deep reflection, gratitude)

**5.1.5** The system SHALL display contextual writing prompt based on selected mode

**5.1.6** The system SHALL provide "Shuffle Prompt" button for alternative suggestions

**5.1.7** The system SHALL display mood selector with intensity slider (1-10)

**5.1.8** The system SHALL provide sub-emotion multi-select (max 5)

**5.1.9** The system SHALL provide tag input with autocomplete suggestions

**5.1.10** The system SHALL display voice input button with recording indicator

### 5.2 Entry Display Interface

**5.2.1** The system SHALL display entries with mood-based color gradients

**5.2.2** The system SHALL show entry metadata (date, time, mood, intensity, tags)

**5.2.3** The system SHALL display AI reflection in collapsible section

**5.2.4** The system SHALL display thought reframing in expandable card

**5.2.5** The system SHALL display AI advice in highlighted section

**5.2.6** The system SHALL provide buttons to view different perspectives (friend, future self, mentor)

**5.2.7** The system SHALL display reaction buttons (like, helpful, insightful) with counts

**5.2.8** The system SHALL provide "Add Reflection" button for past entries



### 5.3 Dashboard Interface

**5.3.1** The system SHALL display statistics cards:
- Total entries count
- Entries this week
- Most common emotion
- Current streak (consecutive days)

**5.3.2** The system SHALL display emotion distribution chart

**5.3.3** The system SHALL display mood trends over time (line chart)

**5.3.4** The system SHALL display recent entries list (last 10)

**5.3.5** The system SHALL provide filter options (by date, mood, tag, mode)

### 5.4 Future Message Interface

**5.4.1** The system SHALL provide form to create future message with:
- Content textarea
- Delivery date picker (future dates only)
- Preview section

**5.4.2** The system SHALL display list of pending future messages

**5.4.3** The system SHALL show countdown to delivery date

**5.4.4** The system SHALL provide cancel button for pending messages

### 5.5 Animation and Visual Effects

**5.5.1** The system SHALL use smooth transitions for page navigation (300ms)

**5.5.2** The system SHALL animate mood-based gradients on entry cards

**5.5.3** The system SHALL provide loading animations for AI response generation

**5.5.4** The system SHALL use fade-in animations for newly created entries

**5.5.5** The system SHALL provide subtle hover effects on interactive elements



## 6. Testing Requirements

### 6.1 Unit Testing

**6.1.1** The system SHALL achieve minimum 80% code coverage for service classes

**6.1.2** The system SHALL test all validation rules with valid and invalid inputs

**6.1.3** The system SHALL test boundary conditions for all numeric constraints

**6.1.4** The system SHALL mock external service dependencies in unit tests

### 6.2 Integration Testing

**6.2.1** The system SHALL test complete entry creation flow end-to-end

**6.2.2** The system SHALL test auto-save and recovery scenarios

**6.2.3** The system SHALL test future message delivery flow with time mocking

**6.2.4** The system SHALL test voice input integration with mock transcription service

**6.2.5** The system SHALL test multi-tab conflict scenarios

**6.2.6** The system SHALL test ML service fallback and recovery

### 6.3 Property-Based Testing

**6.3.1** The system SHALL verify entry content length property (10-10000 characters)

**6.3.2** The system SHALL verify mood intensity boundary property (1-10 range)

**6.3.3** The system SHALL verify draft uniqueness property (one per user)

**6.3.4** The system SHALL verify future message date property (delivery > creation)

**6.3.5** The system SHALL verify tag association property (no orphaned tags)

**6.3.6** The system SHALL verify auto-save idempotency property



## 7. Deployment Requirements

### 7.1 Environment Configuration

**7.1.1** The system SHALL support configuration via environment variables

**7.1.2** The system SHALL require the following environment variables:
- Database credentials
- Redis connection details
- ML service API endpoint and key
- Speech-to-text service credentials
- Email service credentials
- Queue driver configuration

**7.1.3** The system SHALL validate required environment variables on startup

### 7.2 Database Migration

**7.2.1** The system SHALL provide database migrations for all new tables

**7.2.2** The system SHALL provide rollback migrations for safe deployment

**7.2.3** The system SHALL seed initial writing prompts for all modes

### 7.3 Queue Workers

**7.3.1** The system SHALL run Laravel queue workers for background processing

**7.3.2** The system SHALL configure queue workers with appropriate concurrency

**7.3.3** The system SHALL monitor queue health via Laravel Horizon

### 7.4 Scheduled Tasks

**7.4.1** The system SHALL configure Laravel scheduler for future message delivery

**7.4.2** The system SHALL run delivery check daily at midnight

**7.4.3** The system SHALL configure scheduler for cache cleanup tasks



## 8. Acceptance Criteria

### 8.1 Core Functionality

**8.1.1** GIVEN a logged-in student user WHEN they navigate to /student/my-entries THEN they see the advanced journal interface

**8.1.2** GIVEN a user selects a journaling mode WHEN they click "Get Prompt" THEN they see a relevant writing prompt

**8.1.3** GIVEN a user is typing an entry WHEN 30 seconds pass THEN the system auto-saves the draft

**8.1.4** GIVEN a user submits a complete entry WHEN processing completes THEN they see AI reflection, reframing, and advice

**8.1.5** GIVEN a user records voice input WHEN transcription completes THEN the text appears in the editor

**8.1.6** GIVEN a user creates a future message WHEN the delivery date arrives THEN they receive a notification and email

### 8.2 Mood Tracking

**8.2.1** GIVEN a user selects a mood and intensity WHEN they submit an entry THEN a mood record is created and linked

**8.2.2** GIVEN a user has 7+ days of entries WHEN they view mood insights THEN they see pattern analysis and trends

**8.2.3** GIVEN a user views the dashboard WHEN mood data is available THEN they see emotion distribution chart

### 8.3 AI Features

**8.3.1** GIVEN an entry is submitted WHEN AI processing completes THEN reflection, reframing, and advice are displayed

**8.3.2** GIVEN a user clicks a perspective button WHEN generation completes THEN they see friend/future self/mentor perspective

**8.3.3** GIVEN ML service is unavailable WHEN entry is submitted THEN user sees fallback message and entry is queued



### 8.4 User Experience

**8.4.1** GIVEN a user is on the journal page WHEN they interact with UI elements THEN they see smooth animations (< 300ms)

**8.4.2** GIVEN a user submits an entry WHEN processing completes THEN response time is under 2 seconds

**8.4.3** GIVEN a user clicks "Shuffle Prompt" WHEN processing completes THEN they see a different prompt within 200ms

**8.4.4** GIVEN a user adds a reaction WHEN they click again THEN the reaction is removed (toggle behavior)

**8.4.5** GIVEN a user has a draft WHEN they return to the journal THEN the draft is restored automatically

### 8.5 Security and Privacy

**8.5.1** GIVEN a user creates an entry WHEN stored in database THEN content is encrypted at rest

**8.5.2** GIVEN a user tries to access another user's entry WHEN authorization check runs THEN access is denied

**8.5.3** GIVEN a user submits malicious input WHEN validation runs THEN input is sanitized and XSS is prevented

**8.5.4** GIVEN a user makes rapid requests WHEN rate limit is reached THEN further requests are throttled

### 8.6 Error Handling

**8.6.1** GIVEN ML service fails WHEN entry is submitted THEN user sees graceful error message and entry is saved

**8.6.2** GIVEN voice transcription fails WHEN user uploads audio THEN they see helpful error message with retry option

**8.6.3** GIVEN database is unavailable WHEN entry is submitted THEN data is cached and user is notified

**8.6.4** GIVEN invalid mood intensity WHEN form is submitted THEN validation error is displayed with specific message
