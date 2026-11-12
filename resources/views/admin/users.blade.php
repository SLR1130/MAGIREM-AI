@extends('layouts.app')

@section('title', 'Manage Users · MAGIREM AI')

@section('content')
<style>
.admin-users,
.admin-users *,
.admin-users *::before,
.admin-users *::after { box-sizing:border-box; }

.admin-users {
    --forest:#103F32;
    --forest-2:#174F3F;
    --emerald:#236B55;
    --mint:#E6F1EB;
    --ivory:#F8FAF7;
    --gold:#F4C84E;
    --text:#263A33;
    --muted:#71837A;
    --line:rgba(16,63,50,.10);
    --danger:#B95E57;
    --danger-bg:#FFF2F0;
    --blue:#4B7E8F;
    --blue-bg:#EDF5F7;

    width:100%;
    max-width:1480px;
    margin:0 auto;
    padding:18px 22px 36px;
    color:var(--text);
    font-family:Inter,"Plus Jakarta Sans",-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
}

.admin-users a { text-decoration:none; }

.au-head {
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:22px;
    margin-bottom:16px;
}
.au-eyebrow {
    display:inline-flex;
    align-items:center;
    gap:9px;
    margin-bottom:8px;
    color:var(--emerald);
    font-size:10px;
    font-weight:850;
    letter-spacing:.14em;
    text-transform:uppercase;
}
.au-eyebrow::before {
    content:"";
    width:20px;
    height:2px;
    border-radius:999px;
    background:var(--gold);
}
.au-head h1 {
    margin:0;
    color:var(--forest);
    font-size:clamp(34px,3.2vw,48px);
    line-height:1;
    letter-spacing:-.045em;
    font-weight:850;
}
.au-head p {
    max-width:740px;
    margin:10px 0 0;
    color:var(--muted);
    font-size:11px;
    line-height:1.6;
}
.au-create {
    min-height:42px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    padding:0 14px;
    border-radius:11px;
    background:var(--forest);
    color:#fff!important;
    font-size:8.5px;
    font-weight:820;
}
.au-create svg { width:15px; height:15px; }

.au-summary {
    display:grid;
    grid-template-columns:minmax(0,1.25fr) repeat(2,minmax(150px,.375fr));
    gap:10px;
    margin-bottom:12px;
}
.au-context,
.au-stat {
    border:1px solid var(--line);
    border-radius:15px;
    background:#fff;
}
.au-context {
    padding:17px;
    background:
        radial-gradient(circle at 100% 0%,rgba(216,235,226,.7),transparent 18rem),
        #fff;
}
.au-context small {
    color:var(--emerald);
    font-size:8px;
    font-weight:850;
    text-transform:uppercase;
    letter-spacing:.09em;
}
.au-context h2 {
    margin:7px 0 0;
    color:var(--forest);
    font-size:18px;
    line-height:1.2;
    letter-spacing:-.02em;
}
.au-context p {
    margin:6px 0 0;
    color:var(--muted);
    font-size:8.3px;
    line-height:1.55;
}
.au-stat {
    padding:13px;
}
.au-stat strong {
    display:block;
    color:var(--forest);
    font-size:24px;
    line-height:1;
    letter-spacing:-.03em;
}
.au-stat span {
    display:block;
    margin-top:5px;
    color:var(--forest);
    font-size:8px;
    font-weight:800;
}
.au-stat small {
    display:block;
    margin-top:3px;
    color:var(--muted);
    font-size:7px;
}

.au-success {
    display:flex;
    align-items:center;
    gap:8px;
    margin-bottom:12px;
    padding:10px 12px;
    border:1px solid rgba(47,135,103,.16);
    border-radius:11px;
    background:#EDF8F3;
    color:#216E55;
    font-size:8px;
    font-weight:700;
}
.au-success svg {
    width:15px;
    height:15px;
    flex:0 0 15px;
}

.au-controls {
    display:grid;
    grid-template-columns:minmax(240px,1fr) auto;
    gap:10px;
    align-items:center;
    margin-bottom:10px;
}
.au-search {
    position:relative;
}
.au-search svg {
    position:absolute;
    left:12px;
    top:50%;
    width:15px;
    height:15px;
    color:#81948A;
    transform:translateY(-50%);
}
.au-search input {
    width:100%;
    height:42px;
    padding:0 13px 0 37px;
    border:1px solid var(--line);
    border-radius:11px;
    outline:none;
    background:#fff;
    color:var(--forest);
    font:inherit;
    font-size:8.5px;
}
.au-search input:focus {
    border-color:rgba(35,107,85,.30);
    box-shadow:0 0 0 3px rgba(35,107,85,.06);
}
.au-tabs {
    display:flex;
    gap:5px;
    padding:5px;
    border:1px solid var(--line);
    border-radius:11px;
    background:#fff;
}
.au-tab {
    min-height:31px;
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:0 10px;
    border:0;
    border-radius:8px;
    background:transparent;
    color:var(--muted);
    font:inherit;
    font-size:7.5px;
    font-weight:800;
    cursor:pointer;
}
.au-tab.active {
    background:var(--mint);
    color:var(--forest);
}
.au-tab-count {
    min-width:17px;
    height:17px;
    display:grid;
    place-items:center;
    padding:0 4px;
    border-radius:999px;
    background:#fff;
    font-size:6.5px;
}

.au-directory {
    border:1px solid var(--line);
    border-radius:16px;
    background:#fff;
    overflow:hidden;
}
.au-directory-head {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:12px 14px;
    border-bottom:1px solid var(--line);
}
.au-directory-head strong {
    color:var(--forest);
    font-size:9.5px;
}
.au-directory-head span {
    color:var(--muted);
    font-size:7.2px;
}

.au-table-wrap {
    width:100%;
    overflow-x:auto;
}
.au-table {
    width:100%;
    border-collapse:collapse;
}
.au-table th {
    padding:9px 12px;
    border-bottom:1px solid var(--line);
    background:var(--ivory);
    color:#7D9187;
    font-size:7px;
    font-weight:850;
    letter-spacing:.07em;
    text-align:left;
    text-transform:uppercase;
}
.au-table td {
    padding:11px 12px;
    border-bottom:1px solid rgba(16,63,50,.07);
    color:var(--text);
    font-size:8px;
    vertical-align:middle;
}
.au-table tr:last-child td { border-bottom:0; }
.au-table tbody tr:hover { background:#FBFCFB; }

.au-person {
    display:flex;
    align-items:center;
    gap:9px;
    min-width:220px;
}
.au-avatar {
    width:36px;
    height:36px;
    flex:0 0 36px;
    display:grid;
    place-items:center;
    border-radius:10px;
    background:var(--mint);
    color:var(--forest);
    font-size:10px;
    font-weight:850;
}
.au-person-copy { min-width:0; }
.au-person-copy strong,
.au-person-copy span {
    display:block;
    overflow:hidden;
    white-space:nowrap;
    text-overflow:ellipsis;
}
.au-person-copy strong {
    max-width:240px;
    color:var(--forest);
    font-size:8.7px;
    font-weight:820;
}
.au-person-copy span {
    max-width:260px;
    margin-top:2px;
    color:var(--muted);
    font-size:7.2px;
}

.au-role {
    min-height:23px;
    display:inline-flex;
    align-items:center;
    padding:0 8px;
    border-radius:999px;
    font-size:6.5px;
    font-weight:850;
    text-transform:capitalize;
}
.au-role.student { background:var(--blue-bg); color:var(--blue); }
.au-role.counselor { background:#EDF8F3; color:#2F8767; }
.au-role.admin { background:#FFF8E8; color:#9D7422; }

.au-detail {
    color:var(--muted);
    font-size:7.4px;
}
.au-current {
    min-height:24px;
    display:inline-flex;
    align-items:center;
    padding:0 8px;
    border-radius:999px;
    background:var(--mint);
    color:var(--forest);
    font-size:6.6px;
    font-weight:850;
}
.au-delete {
    min-height:30px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    padding:0 9px;
    border:1px solid rgba(185,94,87,.18);
    border-radius:8px;
    background:var(--danger-bg);
    color:var(--danger);
    font:inherit;
    font-size:7px;
    font-weight:800;
    cursor:pointer;
}
.au-delete svg { width:12px; height:12px; }
.au-delete:hover { background:#FCE8E6; }

.au-empty-row {
    display:none;
    padding:50px 20px;
    text-align:center;
    color:var(--muted);
    font-size:8px;
}
.au-empty-row strong {
    display:block;
    margin-bottom:4px;
    color:var(--forest);
    font-size:10px;
}
.au-pagination {
    padding:11px 14px;
    border-top:1px solid var(--line);
    background:var(--ivory);
}

@media(max-width:1000px) {
    .au-summary { grid-template-columns:1fr 1fr; }
    .au-context { grid-column:1 / -1; }
    .au-controls { grid-template-columns:1fr; }
    .au-tabs { overflow-x:auto; }
}
@media(max-width:700px) {
    .admin-users { padding-inline:12px; }
    .au-head { align-items:flex-start; flex-direction:column; }
    .au-create { width:100%; }
    .au-summary { grid-template-columns:1fr; }
    .au-context { grid-column:auto; }
    .au-table th:nth-child(3),
    .au-table td:nth-child(3) { display:none; }
}
</style>
@include('admin.partials.workspace-polish')
<style>
/* The existing create-user link becomes a compact floating action on phones. */
@media (max-width:700px) {
    .admin-users { padding-bottom:calc(100px + env(safe-area-inset-bottom, 0px)); }
    .admin-users .au-head { animation:none; }
    .admin-users .au-create {
        position:fixed;
        right:max(16px, env(safe-area-inset-right, 0px));
        bottom:max(16px, env(safe-area-inset-bottom, 0px));
        z-index:900;
        width:auto;
        max-width:calc(100vw - 32px);
        min-height:48px;
        padding:0 18px;
        border:1px solid rgba(255,255,255,.18);
        border-radius:999px;
        box-shadow:0 10px 26px rgba(16,63,50,.24);
        transition:background-color 180ms ease, box-shadow 180ms ease, transform 180ms ease;
    }
    .admin-users .au-create svg { width:17px; height:17px; flex:0 0 17px; }
    .admin-users .au-create:hover { transform:translateY(-2px); }
    .admin-users .au-create:active { transform:scale(.98); }
}
@media (max-width:700px) and (prefers-reduced-motion:reduce) {
    .admin-users .au-create:hover, .admin-users .au-create:active { transform:none; }
}
</style>

@php
    $visibleUsers = $users->getCollection();
    $visibleStudents = $visibleUsers->where('role', 'student')->count();
    $visibleCounselors = $visibleUsers->where('role', 'counselor')->count();
    $visibleAdmins = $visibleUsers->where('role', 'admin')->count();
@endphp

<div class="admin-users">
    <header class="au-head">
        <div>
            <span class="au-eyebrow">Identity & access</span>
            <h1>User management</h1>
            <p>Find accounts, review their status, and manage users from the directory.</p>
        </div>

        <a href="{{ route('admin.create-user') }}" class="au-create">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 5v14m7-7H5"/></svg>
            Create new user
        </a>
    </header>

    <section class="au-summary">
        <article class="au-stat">
            <strong>{{ $users->total() }}</strong>
            <span>Total users</span>
            <small>Across all pages</small>
        </article>

        <article class="au-stat">
            <strong>{{ $users->count() }}</strong>
            <span>Visible now</span>
            <small>Current page</small>
        </article>
    </section>

    @if(session('success'))
        <div class="au-success">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 13l4 4L19 7"/></svg>
            {{ session('success') }}
        </div>
    @endif

    <section class="au-controls">
        <label class="au-search">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 21l-4.35-4.35m1.35-5.65a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input id="adminUserSearch" type="search" aria-label="Search users on this page" placeholder="Search name, email, ID, or specialization..." autocomplete="off">
        </label>

        <div class="au-tabs" aria-label="Filter users on this page by role">
            <button type="button" class="au-tab active" data-user-role="all" aria-pressed="true">All <span class="au-tab-count">{{ $users->count() }}</span></button>
            <button type="button" class="au-tab" data-user-role="student" aria-pressed="false">Students <span class="au-tab-count">{{ $visibleStudents }}</span></button>
            <button type="button" class="au-tab" data-user-role="counselor" aria-pressed="false">Counselors <span class="au-tab-count">{{ $visibleCounselors }}</span></button>
            <button type="button" class="au-tab" data-user-role="admin" aria-pressed="false">Admins <span class="au-tab-count">{{ $visibleAdmins }}</span></button>
        </div>
    </section>

    <section class="au-directory">
        <div class="au-directory-head">
            <strong>Account directory</strong>
            <span>Filters search the users currently loaded on this page.</span>
        </div>

        @if($users->count() > 0)
            <div class="au-table-wrap" role="region" aria-label="Account directory; scroll horizontally to view all columns" tabindex="0">
                <table class="au-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Context</th>
                            <th>Account</th>
                            <th style="text-align:right">Action</th>
                        </tr>
                    </thead>
                    <tbody id="adminUsersBody">
                        @foreach($users as $user)
                            <tr
                                data-user-row
                                data-role="{{ $user->role }}"
                                data-search="{{ strtolower(($user->name ?? '') . ' ' . ($user->email ?? '') . ' ' . ($user->student_id ?? '') . ' ' . ($user->specialization ?? '') . ' ' . ($user->role ?? '')) }}"
                            >
                                <td>
                                    <div class="au-person">
                                        <span class="au-avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                                        <span class="au-person-copy">
                                            <strong>{{ $user->name }}</strong>
                                            <span>{{ $user->email }}</span>
                                        </span>
                                    </div>
                                </td>

                                <td>
                                    <span class="au-role {{ $user->role }}">{{ ucfirst($user->role) }}</span>
                                </td>

                                <td>
                                    @if($user->student_id)
                                        <span class="au-detail">Student ID · {{ $user->student_id }}</span>
                                    @elseif($user->specialization)
                                        <span class="au-detail">{{ $user->specialization }}</span>
                                    @else
                                        <span class="au-detail">No additional profile detail</span>
                                    @endif
                                </td>

                                <td>
                                    @if($user->id === auth()->id())
                                        <span class="au-current">Current account</span>
                                    @else
                                        <span class="au-detail">Managed account</span>
                                    @endif
                                    @if(in_array($user->requested_role, ['student','counselor'], true))
                                        <span class="au-detail" style="display:block;margin-top:4px">{{ ucfirst(str_replace('_',' ',$user->approval_status ?? 'pending')) }} · {{ $user->notification_email_verified_at ? 'Gmail verified' : 'Gmail not verified' }}</span>
                                        @if($user->requiresOwnerOnboarding())<span class="au-detail" style="display:block;margin-top:3px">{{ $user->password_setup_completed_at ? 'Owner setup in progress' : 'Owner password setup required' }}</span>@endif
                                        @if($canReviewAccounts)<a class="au-detail" style="display:inline-block;margin-top:4px;color:#236b55;font-weight:800" href="{{ route('admin.account-verification.show',$user) }}">Review / onboarding details</a>@endif
                                    @endif
                                </td>

                                <td style="text-align:right">
                                    @if($user->id !== auth()->id())
                                        <form method="POST"
                                              action="{{ route('admin.delete-user', $user->id) }}"
                                              onsubmit="return confirm('Delete this user account? This action cannot be undone.');"
                                              style="display:inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="au-delete">
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 7l-.87 12.14A2 2 0 0116.14 21H7.86a2 2 0 01-1.99-1.86L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                Delete
                                            </button>
                                        </form>
                                    @else
                                        <span class="au-detail">Protected</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="au-empty-row" id="adminUsersEmpty">
                    <strong>No matching users</strong>
                    Try another role or search term.
                </div>
            </div>

            <div class="au-pagination">
                {{ $users->links() }}
            </div>
        @else
            <div class="au-empty-row" style="display:block">
                <strong>No users found</strong>
                Accounts will appear here when available.
            </div>
        @endif
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tabs = Array.from(document.querySelectorAll('[data-user-role]'));
    const rows = Array.from(document.querySelectorAll('[data-user-row]'));
    const input = document.getElementById('adminUserSearch');
    const empty = document.getElementById('adminUsersEmpty');

    let activeRole = 'all';

    function applyUserFilters() {
        const query = (input?.value || '').trim().toLowerCase();
        let visible = 0;

        rows.forEach(function (row) {
            const matchesRole = activeRole === 'all' || row.dataset.role === activeRole;
            const matchesSearch = !query || (row.dataset.search || '').includes(query);
            const show = matchesRole && matchesSearch;

            row.style.display = show ? '' : 'none';
            if (show) visible += 1;
        });

        if (empty) {
            empty.style.display = visible === 0 ? 'block' : 'none';
        }
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            activeRole = tab.dataset.userRole;

            tabs.forEach(function (item) {
                item.classList.toggle('active', item === tab);
                item.setAttribute('aria-pressed', String(item === tab));
            });

            applyUserFilters();
        });
    });

    input?.addEventListener('input', applyUserFilters);
});
</script>
@endsection
