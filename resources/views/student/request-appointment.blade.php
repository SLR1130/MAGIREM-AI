@extends('layouts.app')

@section('title', 'Request Appointment · MAGIREM AI')

@section('content')

<style>
.ra-page,
.ra-page *,
.ra-page *::before,
.ra-page *::after { box-sizing:border-box; }

.ra-page {
    --ra-forest:#103F32;
    --ra-forest-2:#174F3F;
    --ra-emerald:#236B55;
    --ra-mint:#E6F1EB;
    --ra-mint-2:#D8EBE2;
    --ra-ivory:#F8FAF7;
    --ra-white:#FFFFFF;
    --ra-gold:#F4C84E;
    --ra-text:#263A33;
    --ra-muted:#71837A;
    --ra-line:rgba(16,63,50,.10);
    --ra-shadow:0 18px 48px rgba(16,63,50,.07);

    width:100%;
    max-width:1500px;
    margin:0 auto;
    padding:10px 18px 26px;
    color:var(--ra-text);
    font-family:Inter,"Plus Jakarta Sans",ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
}

.ra-head {
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:24px;
    margin-bottom:16px;
}

.ra-eyebrow {
    display:inline-flex;
    align-items:center;
    gap:9px;
    margin-bottom:7px;
    color:var(--ra-emerald);
    font-size:10px;
    font-weight:850;
    letter-spacing:.14em;
    text-transform:uppercase;
}

.ra-eyebrow::before {
    content:"";
    width:20px;
    height:2px;
    border-radius:999px;
    background:var(--ra-gold);
}

.ra-head h1 {
    margin:0;
    color:var(--ra-forest);
    font-size:clamp(31px,3vw,46px);
    line-height:1;
    letter-spacing:-.045em;
    font-weight:840;
}

.ra-head p {
    max-width:760px;
    margin:10px 0 0;
    color:var(--ra-muted);
    font-size:12px;
    line-height:1.65;
}

.ra-actions {
    display:flex;
    align-items:center;
    justify-content:flex-end;
    gap:8px;
    flex-wrap:wrap;
}

.ra-btn {
    min-height:40px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    padding:0 13px;
    border:1px solid var(--ra-line);
    border-radius:11px;
    background:#fff;
    color:var(--ra-forest)!important;
    text-decoration:none;
    font-size:10px;
    font-weight:800;
}

.ra-btn.primary {
    border-color:var(--ra-forest);
    background:var(--ra-forest);
    color:#fff!important;
}

.ra-btn svg {
    width:15px;
    height:15px;
}

.ra-alert {
    display:flex;
    align-items:flex-start;
    gap:10px;
    margin-bottom:12px;
    padding:12px 14px;
    border-radius:12px;
    font-size:9.5px;
    line-height:1.55;
}

.ra-alert svg {
    width:17px;
    height:17px;
    flex:0 0 17px;
    margin-top:1px;
}

.ra-alert.success {
    border:1px solid rgba(57,168,121,.18);
    background:#EFF9F4;
    color:#226B50;
}

.ra-alert.error {
    border:1px solid rgba(184,102,94,.16);
    background:#FFF4F3;
    color:#934D47;
}

.ra-alert p {
    margin:0 0 3px;
}

.ra-grid {
    display:grid;
    grid-template-columns:minmax(0,1.25fr) minmax(300px,.75fr);
    gap:14px;
    align-items:start;
}

.ra-card {
    border:1px solid var(--ra-line);
    border-radius:18px;
    background:#fff;
    box-shadow:var(--ra-shadow);
}

.ra-form-card {
    padding:20px;
}

.ra-form-intro {
    display:flex;
    align-items:flex-start;
    gap:12px;
    margin-bottom:18px;
    padding-bottom:16px;
    border-bottom:1px solid var(--ra-line);
}

.ra-form-icon {
    width:46px;
    height:46px;
    flex:0 0 46px;
    display:grid;
    place-items:center;
    border-radius:14px;
    background:var(--ra-mint);
    color:var(--ra-forest);
}

.ra-form-icon svg {
    width:21px;
    height:21px;
}

.ra-form-intro h2 {
    margin:0;
    color:var(--ra-forest);
    font-size:17px;
    font-weight:820;
    letter-spacing:-.02em;
}

.ra-form-intro p {
    margin:5px 0 0;
    color:var(--ra-muted);
    font-size:9.5px;
    line-height:1.6;
}

.ra-form {
    display:grid;
    gap:14px;
}

.ra-field {
    min-width:0;
}

.ra-field label {
    display:flex;
    align-items:center;
    gap:7px;
    margin-bottom:7px;
    color:var(--ra-forest);
    font-size:9.5px;
    font-weight:800;
}

.ra-field label svg {
    width:14px;
    height:14px;
    color:var(--ra-emerald);
}

.ra-required { color:#B8665E; }

.ra-field input,
.ra-field select,
.ra-field textarea {
    width:100%;
    border:1px solid var(--ra-line);
    border-radius:11px;
    background:var(--ra-ivory);
    color:var(--ra-text);
    font:inherit;
    font-size:10px;
    transition:.16s ease;
}

.ra-field input,
.ra-field select {
    min-height:43px;
    padding:0 12px;
}

.ra-field textarea {
    min-height:126px;
    padding:11px 12px;
    line-height:1.55;
    resize:vertical;
}

.ra-field input:focus,
.ra-field select:focus,
.ra-field textarea:focus {
    outline:none;
    border-color:rgba(35,107,85,.38);
    background:#fff;
    box-shadow:0 0 0 3px rgba(35,107,85,.07);
}

.ra-field small {
    display:block;
    margin-top:6px;
    color:#8B9A93;
    font-size:8px;
    line-height:1.5;
}

.ra-field-row {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:10px;
}

.ra-privacy {
    display:grid;
    grid-template-columns:38px minmax(0,1fr);
    gap:11px;
    align-items:start;
    padding:13px;
    border:1px solid rgba(35,107,85,.10);
    border-radius:12px;
    background:var(--ra-mint);
}

.ra-privacy-icon {
    width:38px;
    height:38px;
    display:grid;
    place-items:center;
    border-radius:11px;
    background:#fff;
    color:var(--ra-forest);
}

.ra-privacy-icon svg {
    width:17px;
    height:17px;
}

.ra-privacy strong {
    display:block;
    color:var(--ra-forest);
    font-size:9.5px;
    font-weight:820;
}

.ra-privacy p {
    margin:4px 0 0;
    color:var(--ra-muted);
    font-size:8.5px;
    line-height:1.55;
}

.ra-form-actions {
    display:flex;
    justify-content:flex-end;
    gap:8px;
    padding-top:2px;
}

.ra-submit,
.ra-cancel {
    min-height:41px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    padding:0 14px;
    border-radius:10px;
    font:inherit;
    font-size:9px;
    font-weight:820;
    cursor:pointer;
    text-decoration:none;
}

.ra-submit {
    border:1px solid var(--ra-forest);
    background:var(--ra-forest);
    color:#fff;
}

.ra-submit:hover { background:var(--ra-forest-2); }

.ra-cancel {
    border:1px solid var(--ra-line);
    background:#fff;
    color:var(--ra-forest);
}

.ra-submit svg,
.ra-cancel svg {
    width:14px;
    height:14px;
}

.ra-side {
    display:grid;
    gap:10px;
}

.ra-side-card {
    padding:16px;
    border:1px solid var(--ra-line);
    border-radius:16px;
    background:#fff;
}

.ra-side-card.highlight {
    background:
        radial-gradient(circle at 100% 0%,rgba(216,235,226,.75),transparent 13rem),
        #fff;
}

.ra-side-icon {
    width:38px;
    height:38px;
    display:grid;
    place-items:center;
    margin-bottom:11px;
    border-radius:11px;
    background:var(--ra-mint);
    color:var(--ra-forest);
}

.ra-side-icon svg {
    width:17px;
    height:17px;
}

.ra-side-card strong {
    display:block;
    color:var(--ra-forest);
    font-size:10.5px;
    font-weight:820;
}

.ra-side-card p {
    margin:6px 0 0;
    color:var(--ra-muted);
    font-size:8.8px;
    line-height:1.6;
}

.ra-process {
    margin-top:12px;
    display:grid;
    gap:8px;
}

.ra-process-step {
    display:grid;
    grid-template-columns:26px minmax(0,1fr);
    gap:9px;
    align-items:start;
}

.ra-process-step span:first-child {
    width:26px;
    height:26px;
    display:grid;
    place-items:center;
    border-radius:8px;
    background:var(--ra-forest);
    color:#fff;
    font-size:8px;
    font-weight:850;
}

.ra-process-step strong {
    font-size:9px;
}

.ra-process-step p {
    margin-top:2px;
    font-size:8px;
}

.ra-mini-links {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:7px;
    margin-top:10px;
}

.ra-mini-links a {
    min-height:34px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    border:1px solid var(--ra-line);
    border-radius:9px;
    background:var(--ra-ivory);
    color:var(--ra-forest);
    font-size:8px;
    font-weight:800;
    text-decoration:none;
}

@media(max-width:980px) {
    .ra-grid { grid-template-columns:1fr; }
}

@media(max-width:760px) {
    .ra-page { padding-inline:12px; }
    .ra-head { align-items:flex-start; flex-direction:column; }
    .ra-actions { justify-content:flex-start; }
}

@media(max-width:560px) {
    .ra-field-row { grid-template-columns:1fr; }
    .ra-form-actions { flex-direction:column-reverse; }
    .ra-submit,.ra-cancel { width:100%; }
}
</style>

<div class="ra-page">
    <header class="ra-head">
        <div>
            <span class="ra-eyebrow">Counseling support</span>
            <h1>Request Appointment</h1>
            <p>Submit a private counseling request, suggest a preferred date and time, and let the counselor confirm the final schedule.</p>
        </div>

        <div class="ra-actions">
            <a href="{{ route('student.counselor-directory') }}" class="ra-btn">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zm13 10v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
                </svg>
                Counselor directory
            </a>

            <a href="{{ route('student.appointments') }}" class="ra-btn primary">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3M5 11h14M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/>
                </svg>
                My appointments
            </a>
        </div>
    </header>

    @if(session('success'))
        <div class="ra-alert success">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 13l4 4L19 7"/>
            </svg>
            <div>{{ session('success') }}</div>
        </div>
    @endif

    @if($errors->any())
        <div class="ra-alert error">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v3m0 4h.01M10.3 4.4L2.9 17.2A2 2 0 004.6 20h14.8a2 2 0 001.7-2.8L13.7 4.4a2 2 0 00-3.4 0z"/>
            </svg>
            <div>
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        </div>
    @endif

    <div class="ra-grid">
        <section class="ra-card ra-form-card">
            <div class="ra-form-intro">
                <span class="ra-form-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3M5 11h14M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/>
                    </svg>
                </span>

                <div>
                    <h2>Appointment request form</h2>
                    <p>Only the information needed to review your request is collected here. Your counselor will confirm the final date and time.</p>
                </div>
            </div>

            <form action="{{ route('student.request-appointment.submit') }}" method="POST" class="ra-form">
                @csrf

                <div class="ra-field">
                    <label for="category">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 7h.01M7 3h5c.5 0 1 .2 1.4.6l7 7a2 2 0 010 2.8l-7 7a2 2 0 01-2.8 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/>
                        </svg>
                        Category <span class="ra-required">*</span>
                    </label>

                    <select name="category" id="category" required>
                        <option value="">Select a category</option>
                        <option value="academic" @selected(old('category') === 'academic')>Academic Stress</option>
                        <option value="anxiety" @selected(old('category') === 'anxiety')>Anxiety & Depression</option>
                        <option value="personal" @selected(old('category') === 'personal')>Personal Issues</option>
                        <option value="emotional" @selected(old('category') === 'emotional')>Emotional Support</option>
                        <option value="crisis" @selected(old('category') === 'crisis')>Crisis Intervention</option>
                        <option value="other" @selected(old('category') === 'other')>Other</option>
                    </select>
                </div>

                <div class="ra-field-row">
                    <div class="ra-field">
                        <label for="preferred_date">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3M5 11h14M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/>
                            </svg>
                            Preferred date
                        </label>

                        <input
                            type="date"
                            name="preferred_date"
                            id="preferred_date"
                            min="{{ now()->addDay()->format('Y-m-d') }}"
                            value="{{ old('preferred_date') }}"
                        >
                        <small>Optional — the counselor confirms the final date.</small>
                    </div>

                    <div class="ra-field">
                        <label for="time_slot">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Preferred time
                        </label>

                        <select name="time_slot" id="time_slot">
                            <option value="">Select a time slot</option>
                            <option value="8:00 AM - 10:00 AM" @selected(old('time_slot') === '8:00 AM - 10:00 AM')>8:00 AM - 10:00 AM</option>
                            <option value="10:00 AM - 12:00 PM" @selected(old('time_slot') === '10:00 AM - 12:00 PM')>10:00 AM - 12:00 PM</option>
                            <option value="1:00 PM - 3:00 PM" @selected(old('time_slot') === '1:00 PM - 3:00 PM')>1:00 PM - 3:00 PM</option>
                            <option value="3:00 PM - 5:00 PM" @selected(old('time_slot') === '3:00 PM - 5:00 PM')>3:00 PM - 5:00 PM</option>
                        </select>
                        <small>Optional — the counselor confirms the final time.</small>
                    </div>
                </div>

                <div class="ra-field">
                    <label for="reason">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 4h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5a1 1 0 011-1zm3 4h8M8 12h8M8 16h5"/>
                        </svg>
                        Reason for appointment <span class="ra-required">*</span>
                    </label>

                    <textarea name="reason" id="reason" required placeholder="Briefly describe what you would like to discuss...">{{ old('reason') }}</textarea>
                    <small>This information is confidential and is intended only for counselor review.</small>
                </div>

                <div class="ra-privacy">
                    <span class="ra-privacy-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </span>

                    <div>
                        <strong>Privacy & anonymity</strong>
                        <p>Your request is linked using Student ID {{ auth()->user()->student_id ?: '—' }}. Personal account details remain protected according to the application's existing counseling workflow.</p>
                    </div>
                </div>

                <div class="ra-form-actions">
                    <a href="{{ route('student.appointments') }}" class="ra-cancel">Cancel</a>

                    <button type="submit" class="ra-submit">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/>
                        </svg>
                        Submit request
                    </button>
                </div>
            </form>
        </section>

        <aside class="ra-side">
            <section class="ra-side-card highlight">
                <span class="ra-side-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </span>
                <strong>What happens after you submit?</strong>
                <p>Your request enters the existing counselor review workflow. The final appointment date and time are confirmed after review.</p>

                <div class="ra-process">
                    <div class="ra-process-step">
                        <span>1</span>
                        <div>
                            <strong>Submit</strong>
                            <p>Send your category, preferred schedule, and reason.</p>
                        </div>
                    </div>

                    <div class="ra-process-step">
                        <span>2</span>
                        <div>
                            <strong>Review</strong>
                            <p>A counselor reviews the request and confirms availability.</p>
                        </div>
                    </div>

                    <div class="ra-process-step">
                        <span>3</span>
                        <div>
                            <strong>Confirmation</strong>
                            <p>Your confirmed session appears in the Appointments area.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="ra-side-card">
                <span class="ra-side-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </span>
                <strong>Your request stays focused on support</strong>
                <p>The page keeps sensitive details visually restrained and places privacy context beside the form instead of burying it in decorative UI.</p>

                <div class="ra-mini-links">
                    <a href="{{ route('student.counselor-directory') }}">Find counselor</a>
                    <a href="{{ route('student.counselor-chat') }}">Counselor chat</a>
                </div>
            </section>
        </aside>
    </div>
</div>

@endsection
