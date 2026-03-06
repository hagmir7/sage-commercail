<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">

  <div class="bg-white rounded-2xl shadow-md p-8 w-full max-w-sm">

    <h1 class="text-2xl font-bold text-gray-800 mb-1">Welcome back</h1>
    <p class="text-sm text-gray-500 mb-6">Sign in to your account</p>

    {{-- Session Error --}}
    @if (session('error'))
      <div class="mb-4 text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-4 py-2">
        {{ session('error') }}
      </div>
    @endif

    <form action="/login" method="POST">
      @csrf

      {{-- Email --}}
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
        <input
    type="text"
    name="login"
    value="{{ old('login') }}"
    placeholder="you@example.com or username"
    class="w-full px-4 py-2.5 border @error('login') border-red-400 @else border-gray-300 @enderror rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
  />
        @error('email')
          <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
        @enderror
      </div>

      {{-- Password --}}
      <div class="mb-6">
        <div class="flex justify-between items-center mb-1">
          <label class="block text-sm font-medium text-gray-700">Password</label>
        </div>
        <input
          type="password"
          name="password"
          placeholder="••••••••"
          class="w-full px-4 py-2.5 border @error('password') border-red-400 @else border-gray-300 @enderror rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
        />
        @error('password')
          <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
        @enderror
      </div>

      {{-- Remember Me --}}
      <div class="flex items-center mb-6">
        <input type="checkbox" name="remember" id="remember" class="mr-2 accent-blue-600" />
        <label for="remember" class="text-sm text-gray-600">Remember me</label>
      </div>

      {{-- Submit --}}
      <button
        type="submit"
        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-lg text-sm transition"
      >
        Sign in
      </button>

    </form>

  </div>

</body>
</html>