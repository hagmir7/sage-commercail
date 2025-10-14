<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', config('app.name', 'Laravel Admin'))</title>

    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Optional: Inter font for nice typography -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
        }
        .container-lg {
            max-width: 1200px;
            margin: auto;
        }
    </style>

    @stack('styles')
</head>

<body class="min-h-screen flex flex-col">

    <!-- Navbar -->
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="container-lg flex justify-between items-center px-4 py-3">
            <div class="flex items-center space-x-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <a href="{{ url('/') }}" class="text-xl font-semibold text-gray-800 hover:text-blue-600 transition">
                    {{ config('app.name', 'Laravel') }}
                </a>
            </div>

            <div class="flex items-center space-x-4">
                <a href="{{ url('/admin/sql-editor') }}" class="text-gray-600 hover:text-blue-600 font-medium transition">SQL Editor</a>
                <a href="#" class="text-gray-600 hover:text-blue-600 font-medium transition">Dashboard</a>
                <a href="#" class="text-gray-600 hover:text-blue-600 font-medium transition">Settings</a>

                @auth
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="ml-3 bg-red-500 text-white text-sm px-3 py-1.5 rounded hover:bg-red-600 transition">
                            Logout
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="bg-blue-600 text-white text-sm px-3 py-1.5 rounded hover:bg-blue-700 transition">
                        Login
                    </a>
                @endauth
            </div>
        </div>
    </nav>

    <!-- Page Header (optional) -->
    @hasSection('header')
        <header class="bg-gray-50 border-b border-gray-200 py-6">
            <div class="container-lg px-4">
                <h1 class="text-2xl font-semibold text-gray-800">@yield('header')</h1>
            </div>
        </header>
    @endif

    <!-- Page Content -->
    <main class="flex-grow px-4 py-6">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 py-4 text-center text-sm text-gray-500">
        © {{ date('Y') }} {{ config('app.name', 'Laravel') }} — All rights reserved.
    </footer>

    @stack('scripts')
</body>
</html>
