<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $pageTitle = trim($__env->yieldContent('title'));
        $brandName = config('branding.name');
        if ($pageTitle === '') {
            $pageTitle = $brandName;
        } elseif (stripos($pageTitle, $brandName) === false) {
            $pageTitle .= ' · ' . $brandName;
        }
    @endphp
    <title>{{ $pageTitle }}</title>
    <meta name="application-name" content="{{ config('branding.name') }}">
    <meta name="theme-color" content="#103f32">
    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset(config('branding.assets.favicon')) }}">
    <link rel="icon" type="image/x-icon" href="{{ asset(config('branding.assets.favicon_ico')) }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset(config('branding.assets.touch_icon')) }}">
    <link rel="stylesheet" href="{{ asset('css/branding.css') }}">
    
    <style>
        :root {
            /* Wisteria Purple Palette */
            --wisteria-50: #f5f3ff;
            --wisteria-100: #ede9fe;
            --wisteria-200: #ddd6fe;
            --wisteria-300: #c4b5fd;
            --wisteria-400: #a78bfa;
            --wisteria-500: #8b5cf6;
            --wisteria-600: #7c3aed;
            --wisteria-700: #6d28d9;
            --wisteria-800: #5b21b6;
            --wisteria-900: #4c1d95;
            
            /* Dark Blue-Violet */
            --dark-violet-600: #5b21b6;
            --dark-violet-700: #4c1d95;
            --dark-violet-800: #3f0f5c;
            --dark-violet-900: #2d0a47;
            
            /* Accent Colors */
            --accent-green: #10b981;
            --accent-pink: #ec4899;
            --accent-blue: #3b82f6;
            --accent-red: #ef4444;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #e9d5ff 0%, #ddd6fe 50%, #c4b5fd 100%);
            min-height: 100vh;
            color: #2d0a47;
            display: flex;
            flex-direction: column;
        }
        
        /* ===== TYPOGRAPHY ===== */
        h1 { font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem; }
        h2 { font-size: 1.5rem; font-weight: 700; margin-bottom: 1rem; }
        h3 { font-size: 1.25rem; font-weight: 600; margin-bottom: 0.75rem; }
        h4 { font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem; }
        p { line-height: 1.6; margin-bottom: 1rem; }
        
        /* ===== ICONS ===== */
        .icon {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            vertical-align: middle;
            margin-right: 0.3rem;
        }
        
        .icon-lg { width: 1.25rem; height: 1.25rem; }
        .icon-xl { width: 1.5rem; height: 1.5rem; }
        .icon-2xl { width: 2rem; height: 2rem; }
        
        /* ===== BUTTONS ===== */
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }
        
        .btn-primary {
            background-color: var(--wisteria-600);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--dark-violet-700);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(91, 33, 182, 0.4);
        }
        
        .btn-secondary {
            background-color: var(--wisteria-100);
            color: var(--dark-violet-700);
            border: 2px solid var(--wisteria-300);
        }
        
        .btn-secondary:hover {
            background-color: var(--wisteria-200);
            border-color: var(--wisteria-400);
        }
        
        .btn-success {
            background-color: var(--accent-green);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #059669;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .btn-danger {
            background-color: var(--accent-red);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
        
        .btn-sm { padding: 0.5rem 1rem; font-size: 0.875rem; }
        .btn-lg { padding: 1rem 2rem; font-size: 1.125rem; }
        
        /* ===== CARDS ===== */
        .card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(91, 33, 182, 0.15);
            margin-bottom: 1.5rem;
            border: 1px solid #ede9fe;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(91, 33, 182, 0.25);
        }
        
        .card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--wisteria-100);
        }
        
        .card-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .card-icon.primary { background: linear-gradient(135deg, var(--wisteria-500), var(--wisteria-700)); color: white; }
        .card-icon.success { background: linear-gradient(135deg, var(--accent-green), #059669); color: white; }
        .card-icon.pink { background: linear-gradient(135deg, var(--accent-pink), #be185d); color: white; }
        .card-icon.blue { background: linear-gradient(135deg, var(--accent-blue), #1e40af); color: white; }
        
        /* ===== VERTICAL NAVBAR (TOP) ===== */
        .navbar {
            background: linear-gradient(135deg, var(--dark-violet-700) 0%, var(--dark-violet-900) 100%);
            padding: 0.6rem 1.5rem;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(45, 10, 71, 0.3);
            position: sticky;
            top: 0;
            z-index: 100;
            width: 100%;
        }
        
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.1rem;
            font-weight: bold;
            text-decoration: none;
            color: white;
        }
        
        /* Circular Logo */
        .navbar-logo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1rem;
            color: white;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
            position: relative;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .navbar-logo svg {
            width: 100%;
            height: 100%;
            border-radius: 50%;
        }
        
        .navbar-nav {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex: 1;
            margin-left: 1.5rem;
        }
        
        .navbar a {
            color: #f5f3ff;
            text-decoration: none;
            transition: all 0.3s;
            font-weight: 500;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.4rem 0.8rem;
            border-radius: 0.5rem;
            white-space: nowrap;
        }
        
        .navbar a:hover {
            color: #c4b5fd;
            background-color: rgba(196, 181, 253, 0.1);
        }
        
        .navbar-user {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-left: auto;
        }
        
        .user-profile-btn {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s;
            padding: 0.4rem 0.8rem;
            border-radius: 0.5rem;
        }
        
        .user-profile-btn:hover {
            background-color: rgba(196, 181, 253, 0.1);
        }
        
        .user-dropdown {
            position: relative;
        }
        
        .user-dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s;
            padding: 0.4rem 0.8rem;
            border-radius: 0.5rem;
        }
        
        .user-dropdown-toggle:hover {
            background-color: rgba(196, 181, 253, 0.1);
        }
        
        .user-dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: #1a1b2e;
            border: 1px solid rgba(196, 181, 253, 0.2);
            border-radius: 0.5rem;
            min-width: 180px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            display: none;
            flex-direction: column;
            z-index: 1000;
            margin-top: 0.5rem;
        }
        
        .user-dropdown-menu.active {
            display: flex;
        }
        
        .user-dropdown-header {
            padding: 0.75rem;
            border-bottom: 1px solid rgba(196, 181, 253, 0.2);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .user-dropdown-header-info {
            flex: 1;
        }
        
        .user-dropdown-name {
            font-weight: 600;
            color: white;
            font-size: 0.9rem;
        }
        
        .user-dropdown-role {
            font-size: 0.75rem;
            color: #b39ddb;
        }
        
        .user-dropdown-item {
            padding: 0.6rem 0.8rem;
            color: white;
            text-decoration: none;
            transition: all 0.2s;
            border: none;
            background: none;
            cursor: pointer;
            width: 100%;
            text-align: left;
            font-size: 0.85rem;
        }
        
        .user-dropdown-item:hover {
            background-color: rgba(196, 181, 253, 0.1);
        }
        
        .user-dropdown-logout {
            padding: 0.6rem 0.8rem;
            color: #ff6b6b;
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            font-size: 0.85rem;
            transition: all 0.2s;
            border-top: 1px solid rgba(196, 181, 253, 0.2);
        }
        
        .user-dropdown-logout:hover {
            background-color: rgba(255, 107, 107, 0.1);
        }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #8b5cf6;
            border: 2px solid rgba(255, 255, 255, 0.3);
            font-size: 1rem;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        
        .navbar-logout {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s;
            padding: 0.4rem 0.8rem;
            border-radius: 0.5rem;
        }
        
        .navbar-logout:hover {
            background-color: rgba(196, 181, 253, 0.1);
        }
        
        /* ===== CONTAINER & LAYOUT ===== */
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
            flex: 1;
            width: 100%;
        }
        
        .grid {
            display: grid;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .grid-2 { grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); }
        .grid-3 { grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); }
        .grid-4 { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
        
        @media (max-width: 768px) {
            .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr; }
        }
        
        /* ===== ALERTS ===== */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: slideDown 0.3s ease-out;
        }
        
        @keyframes slideDown {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }
        
        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        
        .alert-info {
            background-color: #dbeafe;
            color: #0c4a6e;
            border: 1px solid #7dd3fc;
        }
        
        .alert-warning {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
        }
        
        /* ===== FORMS ===== */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark-violet-700);
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        input, textarea, select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #ddd6fe;
            border-radius: 0.5rem;
            transition: all 0.3s;
            background-color: #faf5ff;
            color: #2d0a47;
            font-family: inherit;
            font-size: 1rem;
        }
        
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: var(--wisteria-600);
            background-color: white;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }
        
        textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        /* ===== SECTIONS ===== */
        .section {
            margin-bottom: 3rem;
        }
        
        .section-title {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 3px solid var(--wisteria-300);
        }
        
        .section-title h2 {
            margin: 0;
            color: var(--dark-violet-700);
        }
        
        .section-icon {
            width: 2.5rem;
            height: 2.5rem;
            background: linear-gradient(135deg, var(--wisteria-500), var(--wisteria-700));
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 1rem;
            }
            
            .navbar-nav {
                flex-wrap: wrap;
                justify-content: center;
                gap: 1rem;
                margin-left: 0;
            }
            
            .navbar-user {
                margin-left: 0;
                width: 100%;
                justify-content: center;
            }
            
            .container {
                padding: 0 0.75rem;
            }
            
            h1 { font-size: 1.5rem; }
            h2 { font-size: 1.25rem; }
        }
    </style>
</head>
<body>
    @auth
    <nav class="navbar">
        <a href="{{ route(auth()->user()->role . '.dashboard') }}" class="navbar-brand">
            <div class="navbar-logo">
                <x-brand-logo variant="emblem" alt="" />
            </div>
            <span>{{ config('branding.name') }}</span>
        </a>
        
        <div class="navbar-nav">
            @yield('navbar-items')
        </div>
        
        <div class="navbar-user">
            <!-- Notification Dropdown -->
            @include('components.notification-dropdown')
            
            <!-- User Dropdown Menu -->
            <div class="user-dropdown">
                <button class="user-dropdown-toggle" onclick="toggleUserDropdown()">
                    <div class="user-avatar">{{ substr(auth()->user()->name, 0, 1) }}</div>
                    <span>{{ substr(auth()->user()->name, 0, 6) }}</span>
                    <svg style="width: 0.7rem; height: 0.7rem; margin-left: 0.2rem;" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                </button>
                <div class="user-dropdown-menu" id="userDropdownMenu">
                    <div class="user-dropdown-header">
                        <div class="user-avatar">{{ substr(auth()->user()->name, 0, 1) }}</div>
                        <div class="user-dropdown-header-info">
                            <div class="user-dropdown-name">{{ auth()->user()->name }}</div>
                            <div class="user-dropdown-role">{{ ucfirst(auth()->user()->role) }}</div>
                        </div>
                    </div>
                    <button type="button" class="user-dropdown-item" onclick="openProfileModal()">
                        <svg style="width: 0.9rem; height: 0.9rem; margin-right: 0.4rem; vertical-align: middle;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        View Profile
                    </button>
                    <form action="{{ route('logout') }}" method="POST" style="width: 100%;">
                        @csrf
                        <button type="submit" class="user-dropdown-logout">
                            <svg style="width: 0.9rem; height: 0.9rem; margin-right: 0.4rem; vertical-align: middle;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>
    @endauth

    <div class="container">
        @if(session('success'))
            <div class="alert alert-success">
                <svg class="icon" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-error">
                <svg class="icon" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
                <div>
                    <ul style="margin: 0; padding-left: 1.5rem;">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @yield('content')
    </div>

    <script>
        function openProfileModal() {
            // Profile modal functionality can be added here
            alert('Profile editing coming soon!');
        }

        function toggleUserDropdown() {
            const menu = document.getElementById('userDropdownMenu');
            menu.classList.toggle('active');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.querySelector('.user-dropdown');
            if (dropdown && !dropdown.contains(event.target)) {
                const menu = document.getElementById('userDropdownMenu');
                menu.classList.remove('active');
            }
        });
    </script>
</body>
</html>
