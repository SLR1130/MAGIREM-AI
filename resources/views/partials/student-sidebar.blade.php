<!-- Student Sidebar Navigation Partial -->



<a href="{{ route('home') }}" class="sidebar-brand">

    <div class="sidebar-brand-icon" aria-hidden="true">

        <x-brand-logo variant="emblem" alt="" />

    </div>



    <div class="sidebar-brand-text">

        <h3>{{ config('branding.name') }}</h3>

        <p>Student Wellness</p>

    </div>

</a>



<!-- Navigation -->

<nav class="sidebar-nav">



    <!-- MAIN -->

    <div class="sidebar-section">

        <div class="sidebar-section-title">Main</div>



        <!-- Home -->

        <div class="sidebar-item">

            <a

                href="{{ route('home') }}"

                class="sidebar-link {{ request()->routeIs('home') ? 'active' : '' }}"

            >

                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">

                    <path

                        stroke-linecap="round"

                        stroke-linejoin="round"

                        stroke-width="1.8"

                        d="M3 11.5L12 4l9 7.5M5.5 10v10h13V10M9 20v-6h6v6"

                    />

                </svg>



                <span class="sidebar-link-label">

                    Home

                </span>

            </a>

        </div>



        <!-- Insights -->

        <div class="sidebar-item">

            <a

                href="{{ route('student.insights') }}"

                class="sidebar-link {{ request()->routeIs('student.insights') ? 'active' : '' }}"

            >

                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">

                    <path

                        stroke-linecap="round"

                        stroke-linejoin="round"

                        stroke-width="1.8"

                        d="M4 19V9m5 10V5m5 14v-7m5 7V3"

                    />

                </svg>



                <span class="sidebar-link-label">

                    Insights

                </span>

            </a>

        </div>



        <!-- AI Companion -->

        <div class="sidebar-item">

            <a

                href="{{ route('student.ai-chat') }}"

                class="sidebar-link {{ request()->routeIs('student.ai-chat') ? 'active' : '' }}"

            >

                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">

                    <path

                        stroke-linecap="round"

                        stroke-linejoin="round"

                        stroke-width="1.8"

                        d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.949L3 20l1.395-3.72A7.71 7.71 0 013 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"

                    />

                </svg>



                <span class="sidebar-link-label">

                    AI Companion

                </span>

            </a>

        </div>



        <!-- Journal -->

        <div class="sidebar-item">

            <a

                href="{{ route('student.journal') }}"

                class="sidebar-link {{ request()->routeIs(

                    'student.journal',

                    'student.entries',

                    'student.create-entry'

                ) ? 'active' : '' }}"

            >

                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">

                    <path

                        stroke-linecap="round"

                        stroke-linejoin="round"

                        stroke-width="1.8"

                        d="M5 4h11a3 3 0 013 3v13H8a3 3 0 01-3-3V4zm3 0v16"

                    />

                </svg>



                <span class="sidebar-link-label">

                    Journal

                </span>

            </a>

        </div>



        <!-- Appointments -->

        <div class="sidebar-item">

            <a

                href="{{ route('student.appointments') }}"

                class="sidebar-link {{ request()->routeIs(

                    'student.appointments',

                    'student.request-appointment',

                    'student.counseling-requests'

                ) ? 'active' : '' }}"

            >

                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">

                    <path

                        stroke-linecap="round"

                        stroke-linejoin="round"

                        stroke-width="1.8"

                        d="M8 7V3m8 4V3M5 10h14M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"

                    />

                </svg>



                <span class="sidebar-link-label">

                    Appointments

                </span>

            </a>

        </div>

    </div>



    <!-- WELLNESS -->

    <div class="sidebar-section">

        <div class="sidebar-section-title">

            Wellness

        </div>



        <!-- Coping Tools -->

        <div class="sidebar-item">

            <a

                href="{{ route('student.coping-toolkit') }}"

                class="sidebar-link {{ request()->routeIs('student.coping-toolkit') ? 'active' : '' }}"

            >

                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">

                    <path

                        stroke-linecap="round"

                        stroke-linejoin="round"

                        stroke-width="1.8"

                        d="M9 3h6l1 3 3 1v6l-3 1-1 3H9l-1-3-3-1V7l3-1 1-3zm3 6a3 3 0 100 6 3 3 0 000-6z"

                    />

                </svg>



                <span class="sidebar-link-label">

                    Coping Toolkit

                </span>

            </a>

        </div>



        <!-- Resources -->

        <div class="sidebar-item">

            <a

                href="{{ route('student.resources') }}"

                class="sidebar-link {{ request()->routeIs('student.resources') ? 'active' : '' }}"

            >

                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">

                    <path

                        stroke-linecap="round"

                        stroke-linejoin="round"

                        stroke-width="1.8"

                        d="M4 5a2 2 0 012-2h5v16H6a2 2 0 00-2 2V5zm16 0a2 2 0 00-2-2h-5v16h5a2 2 0 012 2V5z"

                    />

                </svg>



                <span class="sidebar-link-label">

                    Wellness Resources

                </span>

            </a>

        </div>



        <!-- Community -->

        <div class="sidebar-item">

            <a

                href="{{ route('student.community') }}"

                class="sidebar-link {{ request()->routeIs('student.community') ? 'active' : '' }}"

            >

                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">

                    <path

                        stroke-linecap="round"

                        stroke-linejoin="round"

                        stroke-width="1.8"

                        d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2m7-10a4 4 0 100-8 4 4 0 000 8zm13 10v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"

                    />

                </svg>



                <span class="sidebar-link-label">

                    Community

                </span>

            </a>

        </div>

    </div>



    <!-- SUPPORT -->

    <div class="sidebar-section">

        <div class="sidebar-section-title">

            Support

        </div>



        <!-- Counselor -->

        <div class="sidebar-item">

            <a

                href="{{ route('student.counselor-chat') }}"

                class="sidebar-link {{ request()->routeIs(

                    'student.counselor-chat',

                    'student.counselor-directory'

                ) ? 'active' : '' }}"

            >

                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">

                    <path

                        stroke-linecap="round"

                        stroke-linejoin="round"

                        stroke-width="1.8"

                        d="M8 10h8M8 14h5m8-2a9 9 0 11-4.76-7.94A9 9 0 0121 12z"

                    />

                </svg>



                <span class="sidebar-link-label">

                    Counselor

                </span>

            </a>

        </div>



        <!-- Emergency -->

        <div class="sidebar-item">

            <a

                href="{{ route('student.emergency-resources') }}"

                class="sidebar-link {{ request()->routeIs('student.emergency-resources') ? 'active' : '' }}"

            >

                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">

                    <path

                        stroke-linecap="round"

                        stroke-linejoin="round"

                        stroke-width="1.8"

                        d="M12 9v4m0 4h.01M10.3 4.4L2.8 17.3A2 2 0 004.5 20h15a2 2 0 001.7-2.7L13.7 4.4a2 2 0 00-3.4 0z"

                    />

                </svg>



                <span class="sidebar-link-label">

                    Emergency Resources

                </span>

            </a>

        </div>

    </div>



</nav>



<div class="sidebar-footer">

    <button

        type="button"

        class="sidebar-user"

        data-account-menu-open

        aria-label="Open account menu"

    >



        <!-- Avatar -->

        <span class="sidebar-avatar">

            @if(auth()->user()->profile_picture)

                <img

                    src="{{ asset(auth()->user()->profile_picture) }}"

                    alt="{{ auth()->user()->name }}"

                >

            @else

                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}

            @endif

        </span>



        <!-- User Information -->

        <span class="sidebar-user-info">

            <span class="sidebar-user-name">

                {{ auth()->user()->name }}

            </span>



            <span class="sidebar-user-role">

                {{ ucfirst(auth()->user()->role) }}

            </span>

        </span>



        <!-- Chevron -->

        <svg

            class="sidebar-profile-chevron"

            fill="none"

            stroke="currentColor"

            viewBox="0 0 24 24"

            aria-hidden="true"

        >

            <path

                stroke-linecap="round"

                stroke-linejoin="round"

                stroke-width="1.8"

                d="M9 18l6-6-6-6"

            />

        </svg>



    </button>

</div>
