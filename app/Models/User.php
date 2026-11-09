<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'student_id',
        'year_level',
        'program',
        'specialization',
        'years_of_experience',
        'availability_schedule',
        'status',
        'bio',
        'profile_picture',
        'community_verified_at',
        'community_verified_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'microsoft_tenant_id',
        'microsoft_object_id',
        'institutional_email',
        'notification_email',
        'verification_document_path',
        'internal_review_reason',
        'information_request',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
            'community_verified_at' => 'datetime',
            'community_verified_by' => 'integer',
            'institutional_verified_at' => 'datetime',
            'approval_submitted_at' => 'datetime',
            'notification_email_verified_at' => 'datetime',
            'notification_email_enabled' => 'boolean',
            'verification_document_submitted_at' => 'datetime',
            'alternative_review_requested_at' => 'datetime',
            'privacy_consent_at' => 'datetime',
            'approval_reviewer_id' => 'integer',
            'approval_reviewed_at' => 'datetime',
            'password_setup_completed_at' => 'datetime',
            'owner_onboarding_required' => 'boolean',
            'notification_email_confirmed_at' => 'datetime',
            'notification_email_confirmed_by' => 'integer',
        ];
    }

    public function emotionalEntries()
    {
        return $this->hasMany(EmotionalEntry::class);
    }

    public function sentMessages()
    {
        return $this->hasMany(CounselorMessage::class, 'sender_id');
    }

    public function receivedMessages()
    {
        return $this->hasMany(CounselorMessage::class, 'student_id');
    }

    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    /** Account review is a separate privilege from general administrator access. */
    public function canReviewAccountVerification(): bool
    {
        $reviewerIds = config('services.account_verification.reviewer_user_ids', []);

        return $this->isAdmin()
            && (bool) $this->is_active
            && $this->isApproved()
            && in_array((int) $this->getKey(), array_map('intval', (array) $reviewerIds), true);
    }

    public function isCounselor()
    {
        return $this->role === 'counselor';
    }

    public function isStudent()
    {
        return $this->role === 'student';
    }

    public function isApproved(): bool
    {
        return ($this->approval_status ?? 'approved') === 'approved';
    }

    public function verificationAudits()
    {
        return $this->hasMany(AccountVerificationAudit::class, 'application_user_id');
    }

    public function verificationEmail()
    {
        return $this->hasOne(AccountEmailVerification::class);
    }

    public function verificationEmailDeliveries()
    {
        return $this->hasMany(AccountVerificationEmailDelivery::class);
    }

    public function accountInvitations()
    {
        return $this->hasMany(AccountInvitation::class);
    }

    public function requiresOwnerOnboarding(): bool
    {
        return (bool) $this->owner_onboarding_required;
    }

    public function wellbeingProgress()
    {
        return $this->hasMany(WellbeingProgress::class);
    }

    public function privacySetting()
    {
        return $this->hasOne(PrivacySetting::class);
    }

    // Appointment Relationships
    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'student_id');
    }

    // For counselors: appointments where they are the counselor
    public function counselorAppointments()
    {
        return $this->hasMany(Appointment::class, 'counselor_id');
    }

    // Social Platform Relationships
    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    public function followers()
    {
        return $this->hasMany(UserFollow::class, 'following_id');
    }

    public function following()
    {
        return $this->hasMany(UserFollow::class, 'user_id');
    }

    public function communityMessages()
    {
        return $this->hasMany(CommunityMessage::class);
    }

    public function postComments()
    {
        return $this->hasMany(PostComment::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function sentNotifications()
    {
        return $this->hasMany(Notification::class, 'from_user_id');
    }
}
