@extends('layouts.app')

@section('title', 'Create new user · MAGIREM AI')

@section('content')
@php($selectedRole = old('role', ''))

<style>
.admin-create-user,
.admin-create-user *,
.admin-create-user *::before,
.admin-create-user *::after { box-sizing: border-box; }
.admin-create-user {
    --acu-forest: #103F32;
    --acu-forest-2: #174F3F;
    --acu-emerald: #236B55;
    --acu-mint: #E6F1EB;
    --acu-ivory: #F8FAF7;
    --acu-gold: #F4C84E;
    --acu-text: #263A33;
    --acu-muted: #71837A;
    --acu-line: rgba(16,63,50,.12);
    --acu-danger: #A8433D;
    width: 100%;
    max-width: 1480px;
    margin: 0 auto;
    padding: 20px 22px 40px;
    color: var(--acu-text);
    font-family: Inter, "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}
.admin-create-user a { color: inherit; text-decoration: none; }
.acu-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 20px;
}
.acu-header-main { display: flex; align-items: center; gap: 16px; min-width: 0; }
.acu-page-icon {
    width: 54px;
    height: 54px;
    flex: 0 0 54px;
    display: grid;
    place-items: center;
    border: 1px solid rgba(35,107,85,.09);
    border-radius: 17px;
    background: var(--acu-mint);
    color: var(--acu-emerald);
    overflow: hidden;
}
.acu-page-icon svg { width: 25px; height: 25px; }
.acu-page-icon .magirem-logo { width: 100%; height: 100%; }
.acu-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 7px;
    color: var(--acu-emerald);
    font-size: 10px;
    font-weight: 850;
    letter-spacing: .14em;
    text-transform: uppercase;
}
.acu-eyebrow::before { content: ""; width: 19px; height: 2px; border-radius: 99px; background: var(--acu-gold); }
.acu-header h1 {
    margin: 0;
    color: var(--acu-forest);
    font-size: clamp(30px, 3vw, 42px);
    line-height: 1.08;
    letter-spacing: -.045em;
    font-weight: 850;
}
.acu-header p { max-width: 760px; margin: 8px 0 0; color: var(--acu-muted); font-size: 13px; line-height: 1.55; }
.acu-back {
    min-height: 42px;
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 0 14px;
    border: 1px solid var(--acu-line);
    border-radius: 11px;
    background: #fff;
    color: var(--acu-forest)!important;
    font-size: 12px;
    font-weight: 780;
    transition: border-color .18s ease, background .18s ease, transform .18s ease;
}
.acu-back:hover { border-color: rgba(35,107,85,.27); background: var(--acu-ivory); transform: translateY(-1px); }
.acu-back svg { width: 16px; height: 16px; }
.acu-success, .acu-error-summary {
    display: flex;
    align-items: flex-start;
    gap: 11px;
    margin: 0 0 16px;
    padding: 13px 15px;
    border-radius: 12px;
    font-size: 13px;
    line-height: 1.5;
}
.acu-success { border: 1px solid #C6E5D5; background: #F0FAF4; color: #216E55; }
.acu-error-summary { border: 1px solid #F0C9C4; background: #FFF5F3; color: var(--acu-danger); }
.acu-success svg, .acu-error-summary svg { width: 18px; height: 18px; flex: 0 0 18px; margin-top: 1px; }
.acu-error-summary:focus { outline: 3px solid rgba(184,74,64,.16); outline-offset: 2px; }
.acu-error-summary strong { display: block; margin-bottom: 3px; }
.acu-error-summary ul { margin: 0; padding-left: 18px; }
.acu-form-grid { display: grid; grid-template-columns: minmax(0,1.08fr) minmax(340px,.92fr); gap: 14px; align-items: start; }
.acu-card {
    min-width: 0;
    border: 1px solid var(--acu-line);
    border-radius: 17px;
    background: #fff;
    box-shadow: 0 8px 26px rgba(16,63,50,.045);
    overflow: hidden;
}
.acu-card--basic, .acu-card--actions { grid-column: 1 / -1; }
.acu-card-head { display: flex; align-items: flex-start; gap: 11px; padding: 18px 20px 14px; border-bottom: 1px solid rgba(16,63,50,.08); }
.acu-section-icon {
    width: 34px; height: 34px; flex: 0 0 34px; display: grid; place-items: center;
    border-radius: 11px; background: var(--acu-mint); color: var(--acu-emerald);
}
.acu-section-icon svg { width: 17px; height: 17px; }
.acu-card-head h2 { margin: 0; color: var(--acu-forest); font-size: 16px; line-height: 1.25; font-weight: 820; letter-spacing: -.02em; }
.acu-card-head p { margin: 4px 0 0; color: var(--acu-muted); font-size: 11px; line-height: 1.45; }
.acu-card-body { padding: 18px 20px 20px; }
.acu-basic-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 17px 16px; }
.acu-field { min-width: 0; display: flex; flex-direction: column; gap: 7px; }
.acu-field--role { grid-column: 1 / -1; max-width: calc(50% - 8px); }
.acu-field label { color: var(--acu-text); font-size: 12px; line-height: 1.3; font-weight: 760; }
.acu-required { margin-left: 2px; color: var(--acu-emerald); }
.acu-control-wrap { position: relative; }
.acu-field input, .acu-field select {
    width: 100%; min-width: 0; height: 45px; padding: 0 13px;
    border: 1px solid rgba(16,63,50,.16); border-radius: 10px; outline: none;
    background: #FBFCFB; color: var(--acu-text); font: inherit; font-size: 13px;
    transition: border-color .16s ease, box-shadow .16s ease, background .16s ease;
}
.acu-field input::placeholder { color: #9BAAA2; }
.acu-field select { padding-right: 34px; cursor: pointer; }
.acu-field input:focus, .acu-field select:focus { border-color: var(--acu-emerald); background: #fff; box-shadow: 0 0 0 3px rgba(35,107,85,.11); }
.acu-field input[aria-invalid="true"], .acu-field select[aria-invalid="true"] { border-color: #C35D55; }
.acu-field input[aria-invalid="true"]:focus, .acu-field select[aria-invalid="true"]:focus { box-shadow: 0 0 0 3px rgba(195,93,85,.12); }
.acu-help { margin: -1px 0 0; color: var(--acu-muted); font-size: 10px; line-height: 1.45; }
.acu-field-error { margin: -1px 0 0; color: var(--acu-danger); font-size: 11px; line-height: 1.4; }
.acu-verification-note { padding: 13px 14px; border: 1px solid rgba(35,107,85,.14); border-radius: 11px; background: #f5f9f6; color: var(--acu-muted); font-size: 11px; line-height: 1.55; }
.acu-check { display: flex; align-items: flex-start; gap: 9px; color: var(--acu-text); font-size: 11px; line-height: 1.5; }
.acu-check input { width: 17px; height: 17px; flex: 0 0 17px; margin: 1px 0 0; accent-color: var(--acu-emerald); }
.acu-verification-fields { display: grid; gap: 12px; margin-top: 12px; }
.acu-verification-fields[hidden] { display: none!important; }
.acu-verification-fields textarea { width: 100%; min-height: 80px; padding: 11px 12px; border: 1px solid rgba(16,63,50,.16); border-radius: 10px; background: #fbfcfb; color: var(--acu-text); font: inherit; font-size: 12px; line-height: 1.5; resize: vertical; }
.acu-verification-fields textarea:focus { border-color: var(--acu-emerald); outline: 3px solid rgba(35,107,85,.11); }
.acu-sr-only { position:absolute!important; width:1px!important; height:1px!important; padding:0!important; margin:-1px!important; overflow:hidden!important; clip:rect(0,0,0,0)!important; white-space:nowrap!important; border:0!important; }
.acu-role-card { min-height: 100%; }
.acu-role-state { display: flex; align-items: flex-start; gap: 10px; padding: 13px; border: 1px dashed rgba(35,107,85,.22); border-radius: 11px; background: #FBFDFB; color: var(--acu-muted); font-size: 12px; line-height: 1.5; }
.acu-role-state svg { width: 17px; height: 17px; flex: 0 0 17px; color: var(--acu-emerald); }
.acu-role-fields { min-width: 0; border: 0; padding: 0; margin: 0; }
.acu-role-fields:disabled { opacity: .55; }
.acu-role-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 15px 14px; }
.acu-role-grid .acu-field--wide { grid-column: 1 / -1; }
.acu-password-stack { display: grid; grid-template-columns: 1fr; gap: 15px; }
.acu-password-input { padding-right: 68px!important; }
.acu-password-toggle {
    position: absolute; top: 50%; right: 8px; display: inline-flex; align-items: center; justify-content: center; gap: 4px;
    min-height: 29px; padding: 0 7px; border: 0; border-radius: 7px; background: transparent; color: var(--acu-emerald);
    font: inherit; font-size: 10px; font-weight: 760; cursor: pointer; transform: translateY(-50%);
}
.acu-password-toggle:hover { background: var(--acu-mint); }
.acu-password-toggle:focus-visible, .acu-back:focus-visible, .acu-button:focus-visible { outline: 3px solid rgba(35,107,85,.24); outline-offset: 2px; }
.acu-password-toggle svg { width: 15px; height: 15px; }
.acu-security-note { display: flex; align-items: flex-start; gap: 8px; margin-top: 15px; padding: 11px 12px; border-radius: 10px; background: #F5F8F5; color: var(--acu-muted); font-size: 10px; line-height: 1.5; }
.acu-security-note svg { width: 15px; height: 15px; flex: 0 0 15px; color: var(--acu-emerald); }
.acu-card--actions { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 14px 18px; }
.acu-actions-copy { color: var(--acu-muted); font-size: 11px; line-height: 1.45; }
.acu-actions { display: flex; align-items: center; justify-content: flex-end; gap: 9px; }
.acu-button { min-height: 42px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 0 15px; border: 1px solid transparent; border-radius: 10px; font: inherit; font-size: 12px; font-weight: 790; cursor: pointer; transition: transform .16s ease, box-shadow .16s ease, background .16s ease, border-color .16s ease; }
.acu-button:hover:not(:disabled) { transform: translateY(-1px); }
.acu-button:disabled { opacity: .72; cursor: wait; }
.acu-button--secondary { border-color: var(--acu-line); background: #fff; color: var(--acu-forest); }
.acu-button--secondary:hover { border-color: rgba(35,107,85,.28); background: var(--acu-ivory); }
.acu-button--primary { background: var(--acu-forest); color: #fff; box-shadow: 0 5px 14px rgba(16,63,50,.15); }
.acu-button--primary:hover:not(:disabled) { background: var(--acu-forest-2); box-shadow: 0 8px 18px rgba(16,63,50,.21); }
.acu-button svg { width: 16px; height: 16px; }
.acu-spinner { width: 15px!important; height: 15px!important; border: 2px solid rgba(255,255,255,.45); border-top-color:#fff; border-radius:50%; animation: acu-spin .7s linear infinite; }
@keyframes acu-spin { to { transform: rotate(360deg); } }
@media (max-width: 900px) {
    .admin-create-user { padding-inline: 16px; }
    .acu-form-grid { grid-template-columns: minmax(0,1fr) minmax(300px,.9fr); }
    .acu-card-head, .acu-card-body { padding-left: 16px; padding-right: 16px; }
}
@media (max-width: 700px) {
    .admin-create-user { padding: 14px 12px 28px; }
    .acu-header { align-items: flex-start; flex-direction: column; gap: 14px; margin-bottom: 15px; }
    .acu-header-main { align-items: flex-start; gap: 12px; }
    .acu-page-icon { width: 46px; height: 46px; flex-basis: 46px; border-radius: 14px; }
    .acu-page-icon svg { width: 22px; height: 22px; }
    .acu-header h1 { font-size: clamp(27px, 8vw, 36px); }
    .acu-header p { font-size: 12px; }
    .acu-back { width: 100%; }
    .acu-form-grid { grid-template-columns: minmax(0,1fr); gap: 11px; }
    .acu-card--basic, .acu-card--actions { grid-column: auto; }
    .acu-card-head { padding: 14px 14px 12px; }
    .acu-card-body { padding: 14px; }
    .acu-basic-grid, .acu-role-grid { grid-template-columns: minmax(0,1fr); gap: 14px; }
    .acu-field--role, .acu-role-grid .acu-field--wide { grid-column: auto; max-width: none; }
    .acu-card--actions { align-items: stretch; flex-direction: column; padding: 13px; }
    .acu-actions { display: grid; grid-template-columns: 1fr 1fr; }
    .acu-button { min-width: 0; padding-inline: 10px; }
}
@media (prefers-reduced-motion: reduce) {
    .admin-create-user *, .admin-create-user *::before, .admin-create-user *::after { scroll-behavior: auto!important; animation-duration: .01ms!important; animation-iteration-count: 1!important; transition-duration: .01ms!important; }
}
</style>

<div class="admin-create-user">
    <header class="acu-header">
        <div class="acu-header-main">
            <span class="acu-page-icon" aria-hidden="true">
                <x-brand-logo variant="emblem" alt="" />
            </span>
            <div>
                <span class="acu-eyebrow">Identity &amp; access</span>
                <h1>Create new user</h1>
                <p>Create platform accounts. Student and counselor accounts remain restricted until a designated Guidance reviewer records institutional verification.</p>
            </div>
        </div>
        <a class="acu-back" href="{{ route('admin.users') }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 12H5m7 7l-7-7 7-7"/></svg>
            Back to users
        </a>
    </header>

    @if(session('success'))
        <div class="acu-success" role="status">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="acu-error-summary" role="alert" tabindex="-1">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div>
                <strong>Please review the highlighted fields.</strong>
                <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        </div>
    @endif

    <form id="adminCreateUserForm" method="POST" action="{{ route('admin.store-user') }}" class="acu-form-grid">
        @csrf

        <section class="acu-card acu-card--basic" aria-labelledby="acu-basic-title">
            <div class="acu-card-head">
                <span class="acu-section-icon" aria-hidden="true"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2m12-13a4 4 0 11-8 0 4 4 0 018 0zm3 2v6m3-3h-6"/></svg></span>
                <div><h2 id="acu-basic-title">Basic information</h2><p>Enter the account holder’s name, sign-in email, and platform role.</p></div>
            </div>
            <div class="acu-card-body acu-basic-grid">
                <div class="acu-field">
                    <label for="name">Full name <span class="acu-required" aria-hidden="true">*</span></label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required maxlength="255" placeholder="e.g., Alex Santos" @if($errors->has('name')) aria-invalid="true" aria-describedby="name-error" @endif>
                    @error('name')<p class="acu-field-error" id="name-error">{{ $message }}</p>@enderror
                </div>
                <div class="acu-field">
                    <label for="email"><span id="email-label">Sign-in email</span> <span class="acu-required" aria-hidden="true">*</span></label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="off" autocapitalize="none" spellcheck="false" required maxlength="255" placeholder="name@minsu.edu.ph" @if($errors->has('email')) aria-invalid="true" aria-describedby="email-help email-error" @else aria-describedby="email-help" @endif>
                    <p class="acu-help" id="email-help">Student and counselor accounts must use a MinSU institutional @minsu.edu.ph login. Administrator account rules remain unchanged.</p>
                    @error('email')<p class="acu-field-error" id="email-error">{{ $message }}</p>@enderror
                </div>
                <div class="acu-field acu-field--role">
                    <label for="role">User role <span class="acu-required" aria-hidden="true">*</span></label>
                    <select id="role" name="role" required @if($errors->has('role')) aria-invalid="true" aria-describedby="role-help role-error" @else aria-describedby="role-help" @endif>
                        <option value="" @selected($selectedRole === '') disabled>Select an account role</option>
                        <option value="student" @selected($selectedRole === 'student')>Student</option>
                        <option value="counselor" @selected($selectedRole === 'counselor')>Counselor</option>
                        <option value="admin" @selected($selectedRole === 'admin')>Administrator</option>
                    </select>
                    <p class="acu-help" id="role-help">Role-specific fields appear below when applicable.</p>
                    @error('role')<p class="acu-field-error" id="role-error">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        <section class="acu-card acu-card--basic" id="acu-verification-card" aria-labelledby="acu-verification-title" @if(!in_array($selectedRole, ['student', 'counselor'], true)) hidden @endif>
            <div class="acu-card-head">
                <span class="acu-section-icon" aria-hidden="true"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3l8 3v5c0 5.1-3.5 8.5-8 10-4.5-1.5-8-4.9-8-10V6l8-3zm-3 9 2 2 4-4"/></svg></span>
                <div><h2 id="acu-verification-title">Institutional verification &amp; access</h2><p>This is a separate admin-created workflow, not a self-registration application.</p></div>
            </div>
            <div class="acu-card-body">
                <div class="acu-verification-note">Student and counselor owners create their own password through a one-time invitation after authorized approval. An administrator cannot set or view the owner's permanent password. Personal Gmail is only a contact until the designated reviewer confirms that it belongs to the account holder; it is not Gmail-verified until the owner completes OTP.</div>
                @if($canDirectlyApprove)
                    <div class="acu-field" style="margin-top:13px"><label for="notification_email">Owner's personal Gmail <span id="acu-gmail-required-mark" class="acu-required" aria-hidden="true" hidden>*</span></label><input id="notification_email" name="notification_email" type="email" value="{{ old('notification_email') }}" autocomplete="off" autocapitalize="none" spellcheck="false" placeholder="owner@gmail.com"><p class="acu-help">Optional for a pending account. Required for email invitation; an in-person one-time handoff is available. Never use this as the institutional login email.</p>@error('notification_email')<p class="acu-field-error">{{ $message }}</p>@enderror</div>
                    <label class="acu-check" style="margin-top:10px"><input id="notification_email_contact_confirmed" type="checkbox" name="notification_email_contact_confirmed" value="1" @checked(old('notification_email_contact_confirmed'))><span>I independently confirmed that this Gmail contact belongs to the account holder. This does not mark Gmail ownership verified; the owner must complete OTP after setup.</span></label>
                    @error('notification_email_contact_confirmed')<p class="acu-field-error">{{ $message }}</p>@enderror
                    <label class="acu-check" style="margin-top:13px"><input type="checkbox" id="approve_verified" name="approve_verified" value="1" @checked(old('approve_verified'))><span>I am explicitly approving this account after completing the required verification below. This records my reviewer ID and the approval timestamp.</span></label>
                    <div class="acu-verification-fields" id="acu-verification-fields" @if(!old('approve_verified')) hidden @endif>
                        <label class="acu-check"><input type="checkbox" name="institutional_identity_confirmed" value="1" @checked(old('institutional_identity_confirmed'))><span>I independently confirmed the applicant’s institutional identity and MinSU email using an approved official source. Domain match alone is insufficient.</span></label>
                        <label class="acu-check"><input type="checkbox" name="eligibility_confirmed" value="1" @checked(old('eligibility_confirmed'))><span>I independently confirmed current student enrollment or requested-role eligibility using an approved official source.</span></label>
                        <label class="acu-check" id="acu-counselor-authorization" @if($selectedRole !== 'counselor') hidden @endif><input type="checkbox" name="counselor_authorized" value="1" @checked(old('counselor_authorized'))><span>I also confirmed current counselor/staff or Guidance Office authorization through the appropriate official channel.</span></label>
                        <div class="acu-field"><label for="invitation_method">Password setup handoff <span class="acu-required" aria-hidden="true">*</span></label><select id="invitation_method" name="invitation_method" disabled><option value="">Choose an owner handoff</option><option value="email" @selected(old('invitation_method') === 'email')>Email one-time invitation to confirmed Gmail</option><option value="in_person" @selected(old('invitation_method') === 'in_person')>Show one-time link for in-person handoff</option></select><p class="acu-help">Email invitations go only to reviewer-confirmed Gmail. The in-person link is shown once; do not copy it into notes or email.</p>@error('invitation_method')<p class="acu-field-error">{{ $message }}</p>@enderror</div>
                        <div class="acu-field"><label for="verification_basis">Verification basis <span class="acu-required" aria-hidden="true">*</span></label><textarea id="verification_basis" name="verification_basis" maxlength="2000" minlength="8" placeholder="Record the approved official record or process used. Avoid unnecessary sensitive details.">{{ old('verification_basis') }}</textarea>@error('verification_basis')<p class="acu-field-error">{{ $message }}</p>@enderror</div>
                    </div>
                    @error('approve_verified')<p class="acu-field-error">{{ $message }}</p>@enderror
                    @error('institutional_identity_confirmed')<p class="acu-field-error">{{ $message }}</p>@enderror
                    @error('eligibility_confirmed')<p class="acu-field-error">{{ $message }}</p>@enderror
                    @error('counselor_authorized')<p class="acu-field-error">{{ $message }}</p>@enderror
                @else
                    <div class="acu-verification-note" role="status" style="margin-top:12px">Your administrator account is not on the Guidance reviewer allowlist. This account will be created restricted and can only be approved later by an authorized reviewer.</div>
                @endif
            </div>
        </section>

        <section class="acu-card acu-role-card" aria-labelledby="acu-role-title">
            <div class="acu-card-head">
                <span class="acu-section-icon" aria-hidden="true"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13c-1.168-.776-2.754-1.253-4.5-1.253s-3.332.477-4.5 1.253"/></svg></span>
                <div><h2 id="acu-role-title">Role-specific information</h2><p>Only the selected role’s existing profile fields are shown.</p></div>
            </div>
            <div class="acu-card-body">
                <div id="role-fields-empty" class="acu-role-state" @if(in_array($selectedRole, ['student', 'counselor'], true)) hidden @endif>
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span id="role-fields-message">{{ $selectedRole === 'admin' ? 'Administrator accounts do not require additional profile fields.' : 'Choose Student or Counselor above to see the relevant profile fields.' }}</span>
                </div>

                <div id="student-fields" @if($selectedRole !== 'student') hidden @endif>
                    <fieldset class="acu-role-fields" id="student-role-fields" @disabled($selectedRole !== 'student')>
                        <legend class="acu-sr-only">Student profile fields</legend>
                        <div class="acu-role-grid">
                            <div class="acu-field acu-field--wide">
                                <label for="student_id">Student ID <span class="acu-required" aria-hidden="true">*</span></label>
                                <input type="text" id="student_id" name="student_id" value="{{ old('student_id') }}" autocomplete="off" placeholder="Enter student ID" @if($errors->has('student_id')) aria-invalid="true" aria-describedby="student-id-error" @endif>
                                @error('student_id')<p class="acu-field-error" id="student-id-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="acu-field">
                                <label for="program">Program <span class="acu-required" aria-hidden="true">*</span></label>
                                <input type="text" id="program" name="program" value="{{ old('program') }}" placeholder="e.g., BSIT" @if($errors->has('program')) aria-invalid="true" aria-describedby="program-error" @endif>
                                @error('program')<p class="acu-field-error" id="program-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="acu-field">
                                <label for="year_level">Year level <span class="acu-required" aria-hidden="true">*</span></label>
                                <select id="year_level" name="year_level" @if($errors->has('year_level')) aria-invalid="true" aria-describedby="year-level-error" @endif>
                                    <option value="">Select year level</option>
                                    <option value="1st Year" @selected(old('year_level') === '1st Year')>1st Year</option>
                                    <option value="2nd Year" @selected(old('year_level') === '2nd Year')>2nd Year</option>
                                    <option value="3rd Year" @selected(old('year_level') === '3rd Year')>3rd Year</option>
                                    <option value="4th Year" @selected(old('year_level') === '4th Year')>4th Year</option>
                                </select>
                                @error('year_level')<p class="acu-field-error" id="year-level-error">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </fieldset>
                </div>

                <div id="counselor-fields" @if($selectedRole !== 'counselor') hidden @endif>
                    <fieldset class="acu-role-fields" id="counselor-role-fields" @disabled($selectedRole !== 'counselor')>
                        <legend class="acu-sr-only">Counselor profile fields</legend>
                        <div class="acu-role-grid">
                            <div class="acu-field acu-field--wide">
                                <label for="specialization">Specialization <span class="acu-required" aria-hidden="true">*</span></label>
                                <select id="specialization" name="specialization" @if($errors->has('specialization')) aria-invalid="true" aria-describedby="specialization-error" @endif>
                                    <option value="">Select specialization</option>
                                    @foreach(['Anxiety', 'Depression', 'Academic Stress', 'Relationship Issues', 'Career Counseling', 'Grief & Loss', 'Self-Esteem', 'General Wellness'] as $specialization)
                                        <option value="{{ $specialization }}" @selected(old('specialization') === $specialization)>{{ $specialization }}</option>
                                    @endforeach
                                </select>
                                @error('specialization')<p class="acu-field-error" id="specialization-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="acu-field">
                                <label for="years_of_experience">Years of experience <span class="acu-required" aria-hidden="true">*</span></label>
                                <input type="number" id="years_of_experience" name="years_of_experience" value="{{ old('years_of_experience') }}" min="0" max="60" step="1" inputmode="numeric" placeholder="e.g., 5" @if($errors->has('years_of_experience')) aria-invalid="true" aria-describedby="experience-error" @endif>
                                @error('years_of_experience')<p class="acu-field-error" id="experience-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="acu-field">
                                <label for="availability_schedule">Availability schedule <span class="acu-required" aria-hidden="true">*</span></label>
                                <select id="availability_schedule" name="availability_schedule" @if($errors->has('availability_schedule')) aria-invalid="true" aria-describedby="availability-error" @endif>
                                    <option value="">Select schedule</option>
                                    @foreach(['Mon-Fri 9AM-5PM', 'Mon-Fri 2PM-8PM', 'Weekends', 'Flexible', 'By Appointment'] as $schedule)
                                        <option value="{{ $schedule }}" @selected(old('availability_schedule') === $schedule)>{{ $schedule }}</option>
                                    @endforeach
                                </select>
                                @error('availability_schedule')<p class="acu-field-error" id="availability-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="acu-field acu-field--wide">
                                <label for="status">Availability status <span class="acu-required" aria-hidden="true">*</span></label>
                                <select id="status" name="status" @if($errors->has('status')) aria-invalid="true" aria-describedby="status-error" @endif>
                                    <option value="offline" @selected(old('status', 'offline') === 'offline')>Offline</option>
                                    <option value="online" @selected(old('status') === 'online')>Online</option>
                                    <option value="busy" @selected(old('status') === 'busy')>Busy</option>
                                    <option value="available_later" @selected(old('status') === 'available_later')>Available later</option>
                                </select>
                                @error('status')<p class="acu-field-error" id="status-error">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </fieldset>
                </div>
            </div>
        </section>

        <section class="acu-card" id="acu-security-card" aria-labelledby="acu-security-title" @if($selectedRole !== 'admin') hidden @endif>
            <div class="acu-card-head">
                <span class="acu-section-icon" aria-hidden="true"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 002-2v-7a2 2 0 00-2-2H6a2 2 0 00-2 2v7a2 2 0 002 2zm10-11V7a4 4 0 00-8 0v4h8z"/></svg></span>
                <div><h2 id="acu-security-title">Account security</h2><p>Set a new password for this account.</p></div>
            </div>
            <div class="acu-card-body">
                <div class="acu-password-stack" id="acu-admin-password-fields">
                    <div class="acu-field">
                        <label for="password">Password <span class="acu-required" aria-hidden="true">*</span></label>
                        <div class="acu-control-wrap">
                            <input class="acu-password-input" type="password" id="password" name="password" autocomplete="new-password" @required($selectedRole === 'admin') minlength="8" placeholder="At least 8 characters" @if($errors->has('password')) aria-invalid="true" aria-describedby="password-help password-error" @else aria-describedby="password-help" @endif>
                            <button class="acu-password-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.5 12s3.2-6 9.5-6 9.5 6 9.5 6-3.2 6-9.5 6-9.5-6-9.5-6z"/><circle cx="12" cy="12" r="2.5" stroke-width="1.8"/></svg><span>Show</span></button>
                        </div>
                        <p class="acu-help" id="password-help">Use at least 8 characters. Passwords are securely hashed before storage.</p>
                        @error('password')<p class="acu-field-error" id="password-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="acu-field">
                        <label for="password_confirmation">Confirm password <span class="acu-required" aria-hidden="true">*</span></label>
                        <div class="acu-control-wrap">
                            <input class="acu-password-input" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" @required($selectedRole === 'admin') minlength="8" placeholder="Enter the password again">
                            <button class="acu-password-toggle" type="button" data-password-toggle="password_confirmation" aria-label="Show confirmation password" aria-pressed="false"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.5 12s3.2-6 9.5-6 9.5 6 9.5 6-3.2 6-9.5 6-9.5-6-9.5-6z"/><circle cx="12" cy="12" r="2.5" stroke-width="1.8"/></svg><span>Show</span></button>
                        </div>
                        @error('password')<p class="acu-field-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="acu-security-note">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 11v5m0-9h.01M5 4h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5a1 1 0 011-1z"/></svg>
                    <span>Only administrator accounts set a password here. Student and counselor owners receive a one-time setup invitation and create their own password.</span>
                </div>
            </div>
        </section>

        <footer class="acu-card acu-card--actions">
            <p class="acu-actions-copy">The account will be created only after the server accepts the submitted details.</p>
            <div class="acu-actions">
                <a class="acu-button acu-button--secondary" href="{{ route('admin.users') }}">Cancel</a>
                <button class="acu-button acu-button--primary" id="createUserSubmit" type="submit">
                    <svg data-submit-icon fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M5 13l4 4L19 7"/></svg>
                    <span data-submit-label>Create user</span>
                </button>
            </div>
        </footer>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('adminCreateUserForm');
    const roleSelect = document.getElementById('role');
    const emailInput = document.getElementById('email');
    const emailLabel = document.getElementById('email-label');
    const emailHelp = document.getElementById('email-help');
    const verificationCard = document.getElementById('acu-verification-card');
    const verificationToggle = document.getElementById('approve_verified');
    const verificationFields = document.getElementById('acu-verification-fields');
    const counselorAuthorization = document.getElementById('acu-counselor-authorization');
    const studentPanel = document.getElementById('student-fields');
    const counselorPanel = document.getElementById('counselor-fields');
    const studentSet = document.getElementById('student-role-fields');
    const counselorSet = document.getElementById('counselor-role-fields');
    const emptyState = document.getElementById('role-fields-empty');
    const emptyMessage = document.getElementById('role-fields-message');
    const securityCard = document.getElementById('acu-security-card');
    const adminPasswordFields = document.getElementById('acu-admin-password-fields');
    const invitationMethod = document.getElementById('invitation_method');
    const gmailInput = document.getElementById('notification_email');
    const gmailConfirmation = document.getElementById('notification_email_contact_confirmed');
    const gmailRequiredMark = document.getElementById('acu-gmail-required-mark');

    function updateRoleFields() {
        const role = roleSelect.value;
        const isStudent = role === 'student';
        const isCounselor = role === 'counselor';
        const isApplicantRole = isStudent || isCounselor;
        studentPanel.hidden = !isStudent;
        counselorPanel.hidden = !isCounselor;
        verificationCard.hidden = !isApplicantRole;
        verificationCard.querySelectorAll('input,select,textarea').forEach(function (control) {
            if (control !== verificationToggle) control.disabled = !isApplicantRole;
        });
        securityCard.hidden = isApplicantRole;
        adminPasswordFields.querySelectorAll('input').forEach(function (control) {
            control.disabled = isApplicantRole;
            control.required = role === 'admin';
        });
        if (verificationToggle) {
            verificationToggle.disabled = !isApplicantRole;
            if (!isApplicantRole) verificationToggle.checked = false;
        }
        emailLabel.textContent = role === 'admin' ? 'Administrator sign-in email' : 'Institutional sign-in email';
        emailInput.placeholder = isApplicantRole ? 'name@minsu.edu.ph' : 'Administrator email';
        emailHelp.textContent = isApplicantRole
            ? 'Student and counselor accounts must use a MinSU institutional @minsu.edu.ph login. Administrator account rules remain unchanged.'
            : 'This creates a separate administrator account using existing administrator sign-in rules; no reviewer access is granted automatically.';
        const directApproval = Boolean(isApplicantRole && verificationToggle?.checked);
        const emailHandoff = directApproval && invitationMethod?.value === 'email';
        if (invitationMethod) {
            invitationMethod.disabled = !directApproval;
            invitationMethod.required = directApproval;
        }
        if (gmailInput) {
            gmailInput.required = emailHandoff;
            gmailInput.disabled = !isApplicantRole;
            gmailConfirmation.required = Boolean(gmailInput.value.trim()) || emailHandoff;
            gmailConfirmation.disabled = !isApplicantRole;
            gmailRequiredMark.hidden = !emailHandoff;
        }
        if (counselorAuthorization) counselorAuthorization.hidden = !isCounselor;
        studentSet.disabled = !isStudent;
        counselorSet.disabled = !isCounselor;
        emptyState.hidden = isStudent || isCounselor;
        emptyMessage.textContent = role === 'admin'
            ? 'Administrator accounts do not require additional profile fields.'
            : 'Choose Student or Counselor above to see the relevant profile fields.';

        ['student_id', 'program', 'year_level'].forEach(function (id) {
            document.getElementById(id).required = isStudent;
        });
        ['specialization', 'years_of_experience', 'availability_schedule', 'status'].forEach(function (id) {
            document.getElementById(id).required = isCounselor;
        });
    }

    roleSelect.addEventListener('change', updateRoleFields);
    updateRoleFields();

    if (verificationToggle && verificationFields) {
        const syncVerificationFields = () => {
            verificationFields.hidden = !verificationToggle.checked;
            verificationFields.querySelectorAll('input,textarea').forEach(control => { control.disabled = !verificationToggle.checked; });
            const counselorCheck = counselorAuthorization?.querySelector('input');
            if (counselorCheck) counselorCheck.disabled = !verificationToggle.checked || roleSelect.value !== 'counselor';
            updateRoleFields();
        };
        verificationToggle.addEventListener('change', syncVerificationFields);
        roleSelect.addEventListener('change', syncVerificationFields);
        syncVerificationFields();
    }
    if (invitationMethod) invitationMethod.addEventListener('change', updateRoleFields);
    if (gmailInput) gmailInput.addEventListener('input', updateRoleFields);

    document.querySelectorAll('[data-password-toggle]').forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            const input = document.getElementById(toggle.dataset.passwordToggle);
            const visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', visible ? 'true' : 'false');
            toggle.setAttribute('aria-label', (visible ? 'Hide ' : 'Show ') + (input.id === 'password' ? 'password' : 'confirmation password'));
            toggle.querySelector('span').textContent = visible ? 'Hide' : 'Show';
        });
    });

    form.addEventListener('submit', function (event) {
        if (form.dataset.submitting === 'true') {
            event.preventDefault();
            return;
        }
        form.dataset.submitting = 'true';
        form.setAttribute('aria-busy', 'true');
        const button = document.getElementById('createUserSubmit');
        button.disabled = true;
        button.querySelector('[data-submit-icon]').className = 'acu-spinner';
        button.querySelector('[data-submit-label]').textContent = 'Creating user…';
    });

    const errorSummary = document.querySelector('.acu-error-summary');
    if (errorSummary) errorSummary.focus({ preventScroll: true });
});
</script>
@endsection
