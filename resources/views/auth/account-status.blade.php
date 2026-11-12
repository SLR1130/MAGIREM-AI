@extends('layouts.app')

@section('title', 'Application status · MAGIREM AI')

@section('content')
<style>
.ast,.ast *{box-sizing:border-box}.ast{min-height:100vh;padding:32px 20px 46px;background:radial-gradient(circle at 93% 3%,rgba(230,241,235,.75),transparent 25rem),#f8faf7;color:#263a33;font-family:Inter,"Plus Jakarta Sans",-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.ast-shell{width:min(900px,100%);margin:auto}.ast-top{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:20px}.ast-top form{margin:0}.ast-logout{border:1px solid rgba(16,63,50,.13);border-radius:10px;background:#fff;color:#103f32;padding:10px 13px;font:inherit;font-size:12px;font-weight:700;cursor:pointer}.ast-panel{overflow:hidden;border:1px solid rgba(16,63,50,.12);border-radius:21px;background:#fff;box-shadow:0 18px 54px rgba(16,63,50,.07)}.ast-head{padding:24px 28px;background:linear-gradient(110deg,#fff,#f2f8f4);border-bottom:1px solid rgba(16,63,50,.09)}.ast-kicker{color:#236b55;font-size:10px;font-weight:850;letter-spacing:.15em;text-transform:uppercase}.ast-head h1{margin:9px 0 7px;color:#103f32;font-size:clamp(27px,4vw,36px);letter-spacing:-.04em}.ast-head p{margin:0;color:#71837a;font-size:13px;line-height:1.6}.ast-body{padding:24px 28px 30px}.ast-flash{margin:0 0 14px;padding:12px 14px;border:1px solid #c9e6d5;border-radius:11px;background:#f0faf4;color:#216e55;font-size:12px;line-height:1.55}.ast-flash.warning{border-color:#f1d39d;background:#fff9e9;color:#73520c}.ast-errors{margin:0 0 14px;padding:12px 14px;border:1px solid #efc8c3;border-radius:11px;background:#fff5f3;color:#a8433d;font-size:12px}.ast-errors ul{margin:6px 0 0;padding-left:19px}.ast-status-row{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:15px 16px;border:1px solid rgba(16,63,50,.1);border-radius:13px;background:#fbfdfb}.ast-pill{display:inline-flex;align-items:center;gap:7px;padding:7px 10px;border-radius:999px;background:#fff4d8;color:#805c12;font-size:10px;font-weight:850;text-transform:uppercase;letter-spacing:.04em}.ast-pill:before{content:"";width:7px;height:7px;border-radius:50%;background:#e0aa27}.ast-pill--needs_information{background:#edf5f7;color:#3e6573}.ast-pill--needs_information:before{background:#4b7e8f}.ast-pill--rejected,.ast-pill--suspended{background:#fff0ee;color:#9b443e}.ast-pill--rejected:before,.ast-pill--suspended:before{background:#bf5e56}.ast-summary{margin:15px 0 0;color:#71837a;font-size:12px;line-height:1.65}.ast-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:16px}.ast-fact{padding:13px;border:1px solid rgba(16,63,50,.09);border-radius:11px;background:#fff}.ast-fact small{display:block;margin-bottom:4px;color:#71837a;font-size:9px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.ast-fact strong{color:#103f32;font-size:12px;overflow-wrap:anywhere}.ast-section{margin-top:18px;padding:18px;border:1px solid rgba(16,63,50,.1);border-radius:14px;background:#fff}.ast-section h2{margin:0;color:#103f32;font-size:16px}.ast-section>p{margin:6px 0 13px;color:#71837a;font-size:11px;line-height:1.55}.ast-info-request{padding:13px;border-left:3px solid #4b7e8f;border-radius:0 9px 9px 0;background:#f2f7f8;color:#365b68;font-size:12px;line-height:1.6;white-space:pre-line}.ast-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}.ast-field{min-width:0;display:flex;flex-direction:column;gap:6px}.ast-field--wide{grid-column:1/-1}.ast-field label,.ast-label{color:#263a33;font-size:11px;font-weight:750}.ast-field input,.ast-field select{width:100%;height:41px;padding:0 11px;border:1px solid rgba(16,63,50,.16);border-radius:9px;background:#fbfcfb;color:#263a33;font:inherit;font-size:12px}.ast-field input:focus,.ast-field select:focus{outline:3px solid rgba(35,107,85,.13);border-color:#236b55}.ast-help{color:#71837a;font-size:10px;line-height:1.5}.ast-error{color:#a8433d;font-size:10px}.ast-file{height:auto!important;padding:9px!important;background:#fff!important}.ast-check{display:flex;align-items:flex-start;gap:9px;color:#53675e;font-size:11px;line-height:1.5}.ast-check input{width:16px;height:16px;flex:0 0 16px;margin-top:1px;accent-color:#236b55}.ast-button{min-height:40px;display:inline-flex;align-items:center;justify-content:center;padding:0 14px;border:1px solid transparent;border-radius:10px;background:#103f32;color:#fff;font:inherit;font-size:11px;font-weight:780;cursor:pointer;text-decoration:none}.ast-button:hover{background:#174f3f}.ast-button--secondary{border-color:rgba(16,63,50,.13);background:#fff;color:#103f32}.ast-button--secondary:hover{background:#f8faf7}.ast-actions{display:flex;align-items:center;justify-content:flex-end;gap:9px;margin-top:14px}.ast-privacy{margin-top:14px;padding:12px;border-radius:10px;background:#f3f8f5;color:#71837a;font-size:10px;line-height:1.55}.ast-privacy strong{color:#103f32}.ast-unavailable{padding:12px 14px;border:1px solid #f1d39d;border-radius:11px;background:#fff9e9;color:#73520c;font-size:11px;line-height:1.55}.ast-footer{margin-top:14px;color:#71837a;font-size:10px;line-height:1.55;text-align:center}
@media(max-width:620px){.ast{padding:16px 10px 28px}.ast-panel{border-radius:16px}.ast-head{padding:20px 17px}.ast-body{padding:16px 13px 21px}.ast-top{align-items:flex-start}.ast-grid,.ast-fields{grid-template-columns:1fr}.ast-field--wide{grid-column:auto}.ast-section{padding:14px}.ast-status-row{align-items:flex-start;flex-direction:column}}
@media(prefers-reduced-motion:reduce){.ast *{scroll-behavior:auto!important;transition:none!important;animation:none!important}}
</style>
<style>
/* The status route is authenticated but intentionally has no sidebar shell.
   Reset the app grid/scroll container so this page owns the full viewport. */
body.account-status-layout {
    display: block;
    width: 100%;
    min-width: 0;
    min-height: 100vh;
    overflow-x: hidden;
    overflow-y: auto;
}
body.account-status-layout .account-status-main,
body.account-status-layout .app-main {
    display: block;
    width: 100%;
    min-width: 0;
    max-width: none;
    min-height: 100vh;
    height: auto;
    margin: 0;
    padding: 0;
    overflow: visible;
}
body.account-status-layout .content-wrapper {
    width: 100%;
    min-width: 0;
    max-width: none;
    margin: 0;
    padding: 0;
    overflow: visible;
}
.ast-top .magirem-logo--full {
    display: block;
    width: auto;
    height: 52px;
    max-width: min(220px, 60vw);
    object-fit: contain;
}
/* Keep the status workspace full-width inside authenticated shells. */
.ast {
    width: 100%;
    display: flex;
    justify-content: center;
    min-width: 0;
}
.ast-shell,
.ast-panel,
.ast-body,
.ast-section,
.ast-fields,
.ast-grid,
.ast-body form {
    width: 100%;
    min-width: 0;
}
.ast-top form {
    width: auto;
    min-width: 0;
}
.ast-shell {
    width: min(960px, 100%);
    max-width: 960px;
}
.ast-fields,
.ast-grid {
    align-items: start;
}
.ast-check span {
    min-width: 0;
    flex: 1 1 auto;
}
@media (max-width: 620px) {
    .ast {
        padding-inline: 12px;
    }
    .ast-shell {
        max-width: none;
    }
}
</style>
<main class="ast"><div class="ast-shell">
    <div class="ast-top"><x-brand-logo variant="full"/><form method="POST" action="{{ route('logout') }}">@csrf<button class="ast-logout" type="submit">Sign out</button></form></div>
    <section class="ast-panel" aria-labelledby="ast-title">
        <header class="ast-head"><span class="ast-kicker">{{ $ownerOnboarding ? 'Internal account setup' : ($adminCreated ? 'Internal account status' : 'Student application') }}</span><h1 id="ast-title">{{ $ownerOnboarding ? 'Finish setting up your account' : ($adminCreated ? 'Your internal account status' : 'Your student application status') }}</h1><p>@if($ownerOnboarding)This account was provisioned and approved through authorized internal setup. Complete your first-use password setup and verify your personal Gmail; no public application review is needed again.@elseif($adminCreated)This account was created internally. Access remains restricted until the authorized internal reviewer completes the required checks. It is not a public student application.@else Your student application remains limited until authorized reviewers independently confirm your institutional identity and eligibility.@endif</p></header>
        <div class="ast-body">
            @foreach(['success'=>'success','warning'=>'warning','info'=>'success'] as $key=>$class)
                @if(session($key))<div class="ast-flash {{ $class }}" role="status">{{ session($key) }}</div>@endif
            @endforeach
            @if($errors->any())<div class="ast-errors" role="alert"><strong>There are items to check.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            @php($statusClass = in_array($user->approval_status, ['needs_information','rejected','suspended'], true) ? 'ast-pill--'.$user->approval_status : '')
            <div class="ast-status-row"><div><strong>{{ $user->name }}</strong><div class="ast-summary">{{ ucfirst($user->requested_role) }} {{ $adminCreated ? 'administrator-created account' : 'application' }}@if($user->approval_submitted_at) · submitted {{ $user->approval_submitted_at->format('M j, Y · g:i A') }}@endif</div></div><span class="ast-pill {{ $statusClass }}">{{ str_replace('_',' ', $user->approval_status) }}</span></div>
            <div class="ast-grid">
                <div class="ast-fact"><small>MinSU institutional account</small><strong>{{ $user->institutional_email }}</strong></div>
                <div class="ast-fact"><small>Institutional identity</small><strong>{{ $user->institutional_verified_at ? 'Confirmed by authorized reviewer' : 'Awaiting independent reviewer confirmation' }}</strong></div>
                <div class="ast-fact"><small>Personal notification email</small><strong>{{ $user->notification_email ?: 'Not added yet' }}</strong></div>
                <div class="ast-fact"><small>Email verification</small><strong>{{ $user->notification_email_verified_at ? 'Verified' : 'Not verified' }}</strong></div>
                <div class="ast-fact"><small>Requested role</small><strong>{{ ucfirst($user->requested_role) }}</strong></div>
                <div class="ast-fact"><small>Campus / office</small><strong>{{ $user->campus }}</strong></div>
            </div>

            @if($ownerOnboarding)
                <section class="ast-section"><h2>Owner setup checklist</h2><p>Your role, institutional verification, and Guidance approval are not changed by this setup. Access stays restricted until all owner steps are complete.</p><div class="ast-grid"><div class="ast-fact"><small>Your password</small><strong>{{ $user->password_setup_completed_at ? 'Set by you' : 'Invitation required' }}</strong></div><div class="ast-fact"><small>Gmail recovery</small><strong>{{ $user->notification_email_verified_at ? 'Verified' : 'Needs one-time code' }}</strong></div></div><p class="ast-help"><strong>For counselor sign-in:</strong> use your @minsu.edu.ph institutional email and the password you set. Personal Gmail is for the one-time verification code and password recovery only. There is no separate password-change page; to change or reset your password later, use “Forgot password?” on the sign-in page. The reset link goes to your verified personal Gmail.</p></section>
            @elseif($user->approval_status === 'needs_information' && $user->information_request)
                <section class="ast-section"><h2>Additional information requested</h2><p>Please read the request below, update the application, and submit it again. Internal review notes are not shown here.</p><div class="ast-info-request">{{ $user->information_request }}</div></section>
            @elseif($user->approval_status === 'rejected')
                <section class="ast-section"><h2>Review complete</h2><p>This application was not approved. Internal reviewer notes are restricted. Contact the MAGIREM AI administrator through the official MinSU channel if you believe further action is appropriate.</p></section>
            @elseif($user->approval_status === 'pending')
                <p class="ast-summary">@if($adminCreated)This internally provisioned account is waiting for authorized internal verification and approval. Follow the setup instructions from the administrator who provisioned it.@elseYour student application is waiting for authorized review. Reviewers must independently confirm your institutional email, ID or approved alternative, and current enrollment. The @minsu.edu.ph domain, an ID image, and Gmail OTP are not proof by themselves. A verified personal Gmail address is also required before approval.@endif</p>
            @endif

            @if(!$user->notification_email_verified_at && !$user->notification_email && $ownerOnboarding)
                <section class="ast-section"><h2>Add your personal Gmail</h2><p>Enter the personal Gmail you control. We will send a one-time code; the address remains unverified until you enter it.</p>
                    @if($mailConfigured)
                        <form method="POST" action="{{ route('account.onboarding.email') }}">@csrf<div class="ast-fields"><div class="ast-field ast-field--wide"><label for="owner-gmail">Personal Gmail</label><input id="owner-gmail" name="notification_email" type="email" value="{{ old('notification_email') }}" autocomplete="email" inputmode="email" placeholder="you@gmail.com" required>@error('notification_email')<span class="ast-error">{{ $message }}</span>@enderror</div></div><div class="ast-actions"><button class="ast-button" type="submit">Send verification code</button></div></form>
                    @else
                        <div class="ast-unavailable">Email delivery is not configured. Your Gmail will not be marked verified, and account access remains restricted.</div>
                    @endif
                </section>
            @endif

            @if(!$user->notification_email_verified_at && $user->notification_email)
                <section class="ast-section"><h2>Verify your personal Gmail</h2><p>Enter the one-time code sent to your personal notification address. Codes expire after 30 minutes and have a limited number of attempts.</p>
                    @if($mailConfigured)
                        <form method="POST" action="{{ route('account.email.confirm') }}">@csrf<div class="ast-fields"><div class="ast-field"><label for="verification-code">6-digit verification code</label><input id="verification-code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>@error('code')<span class="ast-error">{{ $message }}</span>@enderror</div></div><div class="ast-actions"><button class="ast-button" type="submit">Verify email</button></div></form>
                        <form method="POST" action="{{ route('account.email.resend') }}" class="ast-actions">@csrf<button class="ast-button ast-button--secondary" type="submit">Resend code</button></form>
                    @else
                        <div class="ast-unavailable">Email delivery is not configured. No code was sent, and the application cannot be approved until an administrator configures a mail transport or arranges an authorized alternative.</div>
                    @endif
                </section>
            @endif

            @if(!$adminCreated && in_array($user->approval_status, ['pending','needs_information'], true))
                <section class="ast-section"><h2>{{ $user->approval_status === 'needs_information' ? 'Update your application' : 'Application details' }}</h2><p>Update information only if it needs correction or the administrator asked for it. Re-submission returns the application to pending review.</p>
                    <form method="POST" action="{{ route('account.resubmit') }}" enctype="multipart/form-data" id="resubmit-form">@csrf
                        <div class="ast-fields">
                            <div class="ast-field"><label for="resubmit-name">Full name</label><input id="resubmit-name" name="name" value="{{ old('name',$user->name) }}" maxlength="255" required>@error('name')<span class="ast-error">{{ $message }}</span>@enderror</div>
                            <div class="ast-field"><label for="resubmit-campus">MinSU campus</label><select id="resubmit-campus" name="campus" required><option value="">Choose campus</option>@foreach(['Bongabong Campus','Calapan Campus','Victoria Campus'] as $campus)<option value="{{ $campus }}" @selected(old('campus',$user->campus)===$campus)>{{ $campus }}</option>@endforeach</select>@error('campus')<span class="ast-error">{{ $message }}</span>@enderror</div>
                            @if($user->requested_role === 'student')
                                <div class="ast-field"><label for="resubmit-student-id">Student ID</label><input id="resubmit-student-id" name="student_id" value="{{ old('student_id',$user->student_id) }}" maxlength="80" required>@error('student_id')<span class="ast-error">{{ $message }}</span>@enderror</div>
                                <div class="ast-field"><label for="resubmit-program">Program</label><input id="resubmit-program" name="program" value="{{ old('program',$user->program) }}" maxlength="160" required>@error('program')<span class="ast-error">{{ $message }}</span>@enderror</div>
                                <div class="ast-field"><label for="resubmit-year">Year level</label><select id="resubmit-year" name="year_level" required><option value="">Choose year</option>@foreach(['1st Year','2nd Year','3rd Year','4th Year'] as $year)<option value="{{ $year }}" @selected(old('year_level',$user->year_level)===$year)>{{ $year }}</option>@endforeach</select>@error('year_level')<span class="ast-error">{{ $message }}</span>@enderror</div>
                            @else
                                <div class="ast-field"><label for="resubmit-position">Staff position / Guidance Office</label><input id="resubmit-position" name="staff_position" value="{{ old('staff_position',$user->staff_position) }}" maxlength="160" required>@error('staff_position')<span class="ast-error">{{ $message }}</span>@enderror</div>
                                <div class="ast-field"><label for="resubmit-specialization">Professional specialization</label><input id="resubmit-specialization" name="specialization" value="{{ old('specialization',$user->specialization) }}" maxlength="160">@error('specialization')<span class="ast-error">{{ $message }}</span>@enderror</div>
                            @endif
                            <div class="ast-field ast-field--wide"><label for="resubmit-email">Personal Gmail for notifications</label><input id="resubmit-email" name="notification_email" type="email" value="{{ old('notification_email',$user->notification_email) }}" autocomplete="email" required>@error('notification_email')<span class="ast-error">{{ $message }}</span>@enderror<p class="ast-help">Changing this address requires new email verification.</p></div>
                            <div class="ast-field ast-field--wide"><label for="resubmit-photo">Replace or add ID photo (optional)</label><input class="ast-file" id="resubmit-photo" name="id_photo" type="file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp">@error('id_photo')<span class="ast-error">{{ $message }}</span>@enderror<p class="ast-help">Private upload, maximum 5 MB. Existing image stays on file unless replaced or you explicitly request the alternative review method.</p></div>
                            <div class="ast-field ast-field--wide"><label class="ast-check"><input name="alternative_review" value="1" type="checkbox" @checked(old('alternative_review'))><span>I cannot submit an ID image and request the accessible alternative review method. If selected, the current image (if any) will be removed.</span></label>@error('alternative_review')<span class="ast-error">{{ $message }}</span>@enderror</div>
                            <div class="ast-field ast-field--wide"><label class="ast-check"><input name="privacy_acknowledgement" value="1" type="checkbox" required @checked(old('privacy_acknowledgement'))><span>I have read the privacy notice and consent to using this information for account review. No face matching or AI analysis is performed. Retention schedule: <strong>{{ $retentionSchedule ?: 'not yet configured' }}</strong>. Privacy contact: <strong>{{ $privacyContact ?: 'MAGIREM AI administrator via the official MinSU channel' }}</strong>.</span></label>@error('privacy_acknowledgement')<span class="ast-error">{{ $message }}</span>@enderror</div>
                        </div><div class="ast-actions"><button class="ast-button" type="submit">Submit updated application</button></div>
                    </form>
                </section>
            @endif
            <p class="ast-footer">Your ID image remains in private storage and is only accessible through the authorized administrator review page.</p>
        </div>
    </section>
</div></main>
@endsection
