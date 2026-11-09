@extends('layouts.app')

@section('title', 'Community Moderation - MAGIREM AI')

@section('content')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800&family=Inter:wght@400;500;600&display=swap" onload="this.onload=null;this.rel='stylesheet'">

<div class="starry-background">
    <div class="stars-layer stars-layer-1"></div>
    <div class="stars-layer stars-layer-2"></div>
    <div class="stars-layer stars-layer-3"></div>
    <div class="nebula-glow"></div>
</div>

<div class="moderation-content">

<div class="moderation-header">
    <div>
        <h1 class="welcome-heading">Community Moderation</h1>
        <p class="welcome-subheading">Review community posts, reports and moderation history</p>
    </div>
    <a href="{{ route('student.community') }}" class="back-button">Open Community feed</a>
    <a href="{{ route('admin.dashboard') }}" class="back-button">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
        </svg>
        Back to Dashboard
    </a>
</div>

<!-- Stats Section -->
<div class="section-container">
    <div class="section-header">
        <h2 class="section-title">
            <svg class="section-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
            </svg>
            Moderation Statistics
        </h2>
    </div>

    <div class="stats-grid">
        <div class="stat-card stat-card-blue">
            <div class="stat-icon-wrapper">
                <svg class="stat-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <p class="stat-label">Pending Review</p>
                <div class="stat-value">{{ $stats['pending_moderation'] }}</div>
                <div class="stat-trend stat-trend-neutral">
                    <span>Awaiting approval</span>
                </div>
            </div>
        </div>

        <div class="stat-card stat-card-green">
            <div class="stat-icon-wrapper">
                <svg class="stat-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <p class="stat-label">Approved Posts</p>
                <div class="stat-value">{{ $stats['total_messages'] }}</div>
                <div class="stat-trend stat-trend-up">
                    <span>Published</span>
                </div>
            </div>
        </div>

        <div class="stat-card stat-card-purple">
            <div class="stat-icon-wrapper">
                <svg class="stat-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v12a2 2 0 01-2 2h-3l-4 4z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <p class="stat-label">Total Posts</p>
                <div class="stat-value">{{ $stats['total_messages'] + $stats['pending_moderation'] }}</div>
                <div class="stat-trend stat-trend-neutral">
                    <span>All time</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Pending Posts Section -->
<div class="section-container">
    <div class="section-header">
        <h2 class="section-title">
            <svg class="section-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m7-4a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Pending Posts for Review
        </h2>
        <span class="section-subtitle">{{ $pendingMessages->total() }} posts awaiting approval</span>
    </div>

    @if($pendingMessages->count() > 0)
        <div class="posts-container">
            @foreach($pendingMessages as $post)
                <div class="post-card">
                    <div class="post-header">
                        <div class="post-meta">
                            <div class="post-author">
                                @if($post->is_anonymous)
                                    <span class="author-name">Anonymous</span>
                                @else
                                    <span class="author-name">{{ $post->user->name ?? 'Unknown' }}</span>
                                @endif
                                <span class="post-id">#{{ $post->id }}</span>
                            </div>
                            <span class="post-date">{{ $post->created_at->format('M d, Y • H:i') }}</span>
                        </div>
                        <div class="post-category">
                            <span class="category-badge" style="background-color: {{ $post->getCategoryColor() }};">
                                {{ $post->getCategoryIcon() }} {{ ucfirst(str_replace('-', ' ', $post->category)) }}
                            </span>
                        </div>
                    </div>

                    @if($post->moderation_status === 'flagged')
                        <div class="moderation-hold-badge">Safety review hold</div>
                    @endif
                    @if($post->post_format === 'visual')
                        <div class="moderation-hold-badge" style="background:#eef4ff;color:#47678c;">Visual post — server-rendered preview</div>
                    @endif
                    @if($post->visual_media_path || $post->image_path)
                        <img class="moderation-media" src="{{ route('admin.community-moderation.image', $post->id) }}" alt="Private photo attached to this post" loading="lazy">
                    @endif
                    <div class="post-content">
                        <p>{{ $post->post_format === 'visual' ? ($post->visual_text ?: $post->message) : $post->message }}</p>
                    </div>

                    <div class="post-actions">
                        <!-- Approve Button -->
                        <form action="{{ route('admin.community-moderation.approve', $post->id) }}" method="POST" style="display: inline;">
                            @csrf
                            <button type="submit" class="action-button approve-button">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Approve
                            </button>
                        </form>

                        <!-- Reject Button (opens modal) -->
                        <button type="button" class="action-button reject-button" onclick="openRejectModal({{ $post->id }})">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Reject
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="pagination-container">
            {{ $pendingMessages->links() }}
        </div>
    @else
        <div class="empty-state">
            <svg class="empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <h3 class="empty-title">No Pending Posts</h3>
            <p class="empty-description">All community posts have been reviewed!</p>
        </div>
    @endif
</div>

<div class="section-container">
    <div class="section-header">
        <h2 class="section-title">Published posts and pinning</h2>
        <span class="section-subtitle">Pin information or remove a post from the feed. Actions are recorded; content is retained.</span>
    </div>
    @forelse($publishedMessages as $post)
        <div class="post-card" style="margin-bottom:12px">
            <div class="post-meta">
                <div class="post-author">
                    <span class="author-name">{{ $post->is_anonymous && $post->user?->role === 'student' ? 'Anonymous student' : ($post->user->name ?? 'Community member') }}</span>
                    @if($post->is_anonymous && $post->user?->role === 'student')<span class="post-id">(moderator-only identity: {{ $post->user->name ?? 'unavailable' }})</span>@endif
                    @if($post->user?->role === 'counselor')<span class="category-badge">Authorized Counselor</span>@elseif($post->user?->role === 'admin')<span class="category-badge">Guidance Admin</span>@endif
                    <span class="post-id">#{{ $post->id }}</span>
                </div>
                <span class="post-date">{{ $post->created_at->format('M d, Y H:i') }}</span>
            </div>
            @if($post->title)<h3>{{ $post->title }}</h3>@endif
            <p>{{ \Illuminate\Support\Str::limit($post->post_format === 'visual' ? ($post->visual_text ?: $post->message) : $post->message, 600) }}</p>
            <form action="{{ route('admin.community-moderation.pin', $post->id) }}" method="POST">
                @csrf
                <input type="hidden" name="pinned" value="{{ $post->is_pinned ? 0 : 1 }}">
                <button type="submit" class="action-button {{ $post->is_pinned ? 'reject-button' : 'approve-button' }}">{{ $post->is_pinned ? 'Unpin' : 'Pin to top' }}</button>
                @if($post->is_pinned)<span class="badge badge-pinned">Currently pinned</span>@endif
            </form>
            <form action="{{ route('admin.community-moderation.hide', $post->id) }}" method="POST" style="margin-top:12px">
                @csrf
                <label for="hide-reason-{{ $post->id }}">Reason for removal from the feed</label>
                <input id="hide-reason-{{ $post->id }}" name="reason" type="text" maxlength="500" required style="display:block;width:100%;max-width:600px;padding:10px;margin:6px 0" placeholder="Brief moderation reason">
                <button type="submit" class="action-button reject-button" onclick="return confirm('Remove this post from the community feed? Its content and media will be retained for review.')">Remove from feed</button>
            </form>
        </div>
    @empty
        <p class="empty-description">There are no published posts to pin yet.</p>
    @endforelse
    @if($publishedMessages->hasPages())<div class="pagination-container">{{ $publishedMessages->links() }}</div>@endif
</div>

<div class="section-container">
    <div class="section-header">
        <h2 class="section-title">Removed from feed</h2>
        <span class="section-subtitle">Hidden posts are retained and can be restored after review.</span>
    </div>
    @forelse($hiddenMessages as $post)
        <div class="post-card" style="margin-bottom:12px">
            <strong>Post #{{ $post->id }} · {{ $post->is_anonymous && $post->user?->role === 'student' ? 'Anonymous student' : ($post->user->name ?? 'Community member') }}</strong>
            <p>{{ \Illuminate\Support\Str::limit($post->post_format === 'visual' ? ($post->visual_text ?: $post->message) : $post->message, 600) }}</p>
            <p><strong>Removal reason:</strong> {{ $post->moderation_notes }}</p>
            <form action="{{ route('admin.community-moderation.restore', $post->id) }}" method="POST">
                @csrf
                <button type="submit" class="action-button approve-button">Restore to feed</button>
            </form>
        </div>
    @empty
        <p class="empty-description">No posts are currently removed from the feed.</p>
    @endforelse
    @if($hiddenMessages->hasPages())<div class="pagination-container">{{ $hiddenMessages->links() }}</div>@endif
</div>

<div class="section-container">
    <div class="section-header">
        <h2 class="section-title">Moderation history</h2>
        <span class="section-subtitle">Recorded actions are visible only to administrators.</span>
    </div>
    @forelse($moderationAudits as $audit)
        <div class="post-card" style="margin-bottom:9px">
            <strong>{{ str_replace('_', ' ', ucfirst($audit->action)) }}</strong>
            <span class="post-date">{{ $audit->created_at->format('M d, Y H:i') }}</span>
            <p style="margin:6px 0">Post #{{ $audit->community_message_id ?? 'removed' }} · {{ $audit->moderator->name ?? $audit->actor->name ?? 'System review' }}</p>
            @if($audit->notes)<small>{{ $audit->notes }}</small>@endif
        </div>
    @empty
        <p class="empty-description">No moderation actions have been recorded yet.</p>
    @endforelse
    @if($moderationAudits->hasPages())<div class="pagination-container">{{ $moderationAudits->links() }}</div>@endif
</div>

</div>

<!-- Reject Modal -->
<div id="rejectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Reject Post</h2>
            <button type="button" class="modal-close" onclick="closeRejectModal()">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <form id="rejectForm" method="POST">
            @csrf
            <div class="form-group">
                <label for="reason" class="form-label">Rejection Reason</label>
                <textarea 
                    id="reason" 
                    name="reason" 
                    class="form-textarea" 
                    placeholder="Explain why this post is being rejected..."
                    required
                    maxlength="500"
                ></textarea>
                <span class="char-count"><span id="charCount">0</span>/500</span>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">Reject Post</button>
            </div>
        </form>
    </div>
</div>

<style>
.starry-background{position:fixed;top:0;left:0;width:100%;height:100%;background:linear-gradient(180deg,#0a0e27 0%,#1a1f3a 50%,#2d1b4e 100%);z-index:-1;overflow:hidden}
.stars-layer{position:absolute;width:100%;height:100%;background-image:radial-gradient(2px 2px at 20% 30%,white,transparent),radial-gradient(2px 2px at 60% 70%,white,transparent),radial-gradient(1px 1px at 50% 50%,white,transparent),radial-gradient(1px 1px at 80% 10%,white,transparent),radial-gradient(2px 2px at 90% 60%,white,transparent),radial-gradient(1px 1px at 33% 80%,white,transparent),radial-gradient(1px 1px at 15% 90%,white,transparent);background-size:200% 200%;background-position:0% 0%}
.stars-layer-1{animation:twinkle 3s ease-in-out infinite;opacity:0.8}
.stars-layer-2{animation:twinkle 4s ease-in-out infinite 1s;opacity:0.6}
.stars-layer-3{animation:twinkle 5s ease-in-out infinite 2s;opacity:0.4}
@keyframes twinkle{0%,100%{opacity:0.3}50%{opacity:1}}
.nebula-glow{position:absolute;width:100%;height:100%;background:radial-gradient(ellipse at 20% 30%,rgba(139,92,246,0.15) 0%,transparent 50%),radial-gradient(ellipse at 80% 70%,rgba(99,102,241,0.1) 0%,transparent 50%),radial-gradient(ellipse at 50% 50%,rgba(236,72,153,0.08) 0%,transparent 50%);animation:nebulaPulse 8s ease-in-out infinite}
@keyframes nebulaPulse{0%,100%{opacity:0.5}50%{opacity:0.8}}
.moderation-content{position:relative;z-index:1;padding:2rem;max-width:1200px;margin:0 auto}
.moderation-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;padding:1.5rem;background:rgba(255,255,255,0.05);backdrop-filter:blur(10px);border-radius:16px;border:1px solid rgba(255,255,255,0.1)}
.welcome-heading{font-family:'Montserrat',sans-serif;font-weight:700;font-size:2rem;color:#ffffff;margin:0;letter-spacing:-0.5px}
.welcome-subheading{font-family:'Inter',sans-serif;font-size:0.95rem;color:rgba(255,255,255,0.7);margin:0.5rem 0 0 0}
.back-button{display:flex;align-items:center;gap:0.5rem;padding:0.75rem 1.5rem;background:rgba(139,92,246,0.2);border:1px solid rgba(139,92,246,0.5);border-radius:8px;color:#8b5cf6;text-decoration:none;font-weight:600;transition:all 0.2s}
.back-button:hover{background:rgba(139,92,246,0.3);border-color:rgba(139,92,246,0.8)}
.back-button svg{width:1.25rem;height:1.25rem}
.section-container{background:rgba(255,255,255,0.05);backdrop-filter:blur(10px);border-radius:16px;padding:1.5rem;margin-bottom:1.5rem;border:1px solid rgba(255,255,255,0.1)}
.section-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem}
.section-title{font-family:'Montserrat',sans-serif;font-weight:700;font-size:1.25rem;color:#ffffff;display:flex;align-items:center;gap:0.5rem;margin:0}
.section-icon{width:1.5rem;height:1.5rem;color:#8b5cf6}
.section-subtitle{font-family:'Inter',sans-serif;font-size:0.875rem;color:rgba(255,255,255,0.6)}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1.5rem}
.stat-card{background:rgba(255,255,255,0.05);border-radius:12px;padding:1.5rem;border:1px solid rgba(255,255,255,0.1);display:flex;gap:1rem;transition:transform 0.2s,box-shadow 0.2s}
.stat-card:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,0.2)}
.stat-card-blue{border-left:3px solid #3b82f6}
.stat-card-green{border-left:3px solid #10b981}
.stat-card-purple{border-left:3px solid #8b5cf6}
.stat-icon-wrapper{width:3rem;height:3rem;border-radius:12px;background:rgba(139,92,246,0.2);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.stat-icon{width:1.5rem;height:1.5rem;color:#8b5cf6}
.stat-content{flex:1}
.stat-label{font-family:'Inter',sans-serif;font-size:0.875rem;color:rgba(255,255,255,0.7);margin:0 0 0.5rem 0}
.stat-value{font-family:'Montserrat',sans-serif;font-weight:700;font-size:2rem;color:#ffffff;margin:0 0 0.5rem 0}
.stat-trend{font-family:'Inter',sans-serif;font-size:0.75rem;display:flex;align-items:center;gap:0.25rem}
.stat-trend-up{color:#10b981}
.stat-trend-neutral{color:rgba(255,255,255,0.6)}
.posts-container{display:flex;flex-direction:column;gap:1rem}
.post-card{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.1);border-radius:12px;padding:1.5rem;transition:all 0.2s}
.post-card:hover{background:rgba(255,255,255,0.05);border-color:rgba(255,255,255,0.2)}
.post-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1rem;gap:1rem}
.post-meta{flex:1}
.post-author{display:flex;align-items:center;gap:0.5rem;margin-bottom:0.5rem}
.author-name{font-family:'Inter',sans-serif;font-weight:600;color:#ffffff;font-size:0.95rem}
.post-id{font-family:'Inter',sans-serif;font-size:0.75rem;color:rgba(255,255,255,0.5);background:rgba(255,255,255,0.1);padding:0.25rem 0.5rem;border-radius:4px}
.post-date{font-family:'Inter',sans-serif;font-size:0.85rem;color:rgba(255,255,255,0.6)}
.post-category{display:flex;gap:0.5rem}
.category-badge{display:inline-flex;align-items:center;gap:0.5rem;padding:0.5rem 1rem;border-radius:20px;color:#ffffff;font-weight:600;font-size:0.85rem}
.moderation-hold-badge{display:inline-flex;margin:0 0 12px;padding:6px 10px;border-radius:999px;background:#fff4d6;color:#80621c;font-size:.78rem;font-weight:700}.moderation-media{display:block;max-width:360px;max-height:240px;margin:0 0 1rem;border-radius:10px;object-fit:cover;border:1px solid rgba(255,255,255,.2)}
.post-content{margin-bottom:1.5rem}
.post-content p{font-family:'Inter',sans-serif;color:rgba(255,255,255,0.9);line-height:1.6;margin:0;word-wrap:break-word}
.post-actions{display:flex;gap:1rem;flex-wrap:wrap}
.action-button{padding:0.75rem 1.5rem;border:none;border-radius:8px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:0.5rem;transition:all 0.2s;font-family:'Inter',sans-serif;font-size:0.9rem}
.approve-button{background:#10b981;color:#ffffff}
.approve-button:hover{background:#059669;transform:translateY(-2px)}
.reject-button{background:#ef4444;color:#ffffff}
.reject-button:hover{background:#dc2626;transform:translateY(-2px)}
.action-button svg{width:1.25rem;height:1.25rem}
.empty-state{text-align:center;padding:3rem 1rem}
.empty-icon{width:4rem;height:4rem;color:rgba(139,92,246,0.5);margin:0 auto 1rem}
.empty-title{font-family:'Montserrat',sans-serif;font-weight:700;font-size:1.5rem;color:#ffffff;margin:0 0 0.5rem 0}
.empty-description{font-family:'Inter',sans-serif;color:rgba(255,255,255,0.6);margin:0}
.pagination-container{display:flex;justify-content:center;margin-top:2rem}
.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:1000;align-items:center;justify-content:center}
.modal.active{display:flex}
.modal-content{background:rgba(15,23,42,0.95);border:1px solid rgba(255,255,255,0.1);border-radius:16px;padding:2rem;max-width:500px;width:90%;backdrop-filter:blur(10px)}
.modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem}
.modal-header h2{font-family:'Montserrat',sans-serif;font-weight:700;font-size:1.5rem;color:#ffffff;margin:0}
.modal-close{background:none;border:none;color:rgba(255,255,255,0.7);cursor:pointer;width:2rem;height:2rem;display:flex;align-items:center;justify-content:center;transition:color 0.2s}
.modal-close:hover{color:#ffffff}
.modal-close svg{width:1.5rem;height:1.5rem}
.form-group{margin-bottom:1.5rem}
.form-label{display:block;font-family:'Inter',sans-serif;font-weight:600;color:#ffffff;margin-bottom:0.5rem}
.form-textarea{width:100%;padding:0.75rem;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:#ffffff;font-family:'Inter',sans-serif;font-size:0.9rem;resize:vertical;min-height:120px;transition:border-color 0.2s}
.form-textarea:focus{outline:none;border-color:rgba(139,92,246,0.5);background:rgba(255,255,255,0.08)}
.form-textarea::placeholder{color:rgba(255,255,255,0.4)}
.char-count{display:block;font-family:'Inter',sans-serif;font-size:0.75rem;color:rgba(255,255,255,0.5);margin-top:0.25rem;text-align:right}
.modal-actions{display:flex;gap:1rem;justify-content:flex-end}
.btn{padding:0.75rem 1.5rem;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-family:'Inter',sans-serif;transition:all 0.2s}
.btn-secondary{background:rgba(255,255,255,0.1);color:#ffffff;border:1px solid rgba(255,255,255,0.2)}
.btn-secondary:hover{background:rgba(255,255,255,0.15)}
.btn-danger{background:#ef4444;color:#ffffff}
.btn-danger:hover{background:#dc2626}
@media (max-width:768px){.moderation-content{padding:1rem}.moderation-header{flex-direction:column;align-items:flex-start;gap:1rem}.welcome-heading{font-size:1.5rem}.post-header{flex-direction:column}.post-actions{flex-direction:column}.action-button{width:100%;justify-content:center}}
</style>

<script>
function openRejectModal(postId) {
    const modal = document.getElementById('rejectModal');
    const form = document.getElementById('rejectForm');
    form.action = `/admin/community-moderation/${postId}/reject`;
    form.reset();
    document.getElementById('charCount').textContent = '0';
    modal.classList.add('active');
}

function closeRejectModal() {
    const modal = document.getElementById('rejectModal');
    modal.classList.remove('active');
}

// Character counter
document.getElementById('reason')?.addEventListener('input', function() {
    document.getElementById('charCount').textContent = this.value.length;
});

// Close modal when clicking outside
document.getElementById('rejectModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeRejectModal();
    }
});
</script>

@endsection
