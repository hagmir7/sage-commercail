@extends('layouts.app')

@section('title', 'Laravel Logs')
@section('header', 'Application Logs')

@section('content')
<div class="max-w-6xl mx-auto bg-white rounded-2xl shadow-lg border border-gray-100 p-6">
    {{-- Status Message --}}
    @if(session('status'))
        <div class="mb-4 p-3 text-sm rounded-lg {{ Str::contains(session('status'), '✅') ? 'bg-green-50 text-green-800' : 'bg-yellow-50 text-yellow-800' }}">
            {{ session('status') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 7h18M3 12h18M3 17h18" />
            </svg>
            Laravel Log Viewer
        </h1>

        {{-- Buttons --}}
        <div class="flex gap-3">
            <form method="GET" action="{{ route('logs.show') }}">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition">
                    Refresh
                </button>
            </form>

            <button id="copyLog" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm rounded-lg border hover:bg-gray-200 transition">
                Copy
            </button>

            <form method="POST" action="{{ route('logs.clear') }}" onsubmit="return confirmClear();">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 transition">
                    Clear Logs
                </button>
            </form>
        </div>
    </div>

    {{-- CodeMirror Log Viewer --}}
    <div class="border border-gray-300 rounded-lg overflow-hidden shadow-inner">
        <textarea id="logViewer" readonly>{{ $logContent }}</textarea>
    </div>
</div>

{{-- CodeMirror (dark mode) --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.12/codemirror.min.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.12/theme/material-palenight.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.12/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.12/mode/clike/clike.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const textarea = document.getElementById('logViewer');
    const editor = CodeMirror.fromTextArea(textarea, {
        mode: 'text/plain',
        lineNumbers: true,
        theme: 'material-palenight',
        readOnly: true,
        lineWrapping: true,
        scrollbarStyle: "native",
    });

    editor.scrollTo(null, editor.getScrollInfo().height);

    // Copy button logic
    document.getElementById('copyLog').addEventListener('click', function() {
        navigator.clipboard.writeText(editor.getValue());
        this.textContent = 'Copied!';
        this.classList.add('bg-green-600', 'text-white');
        setTimeout(() => {
            this.textContent = 'Copy';
            this.classList.remove('bg-green-600', 'text-white');
        }, 1500);
    });
});

// Confirm before clearing logs
function confirmClear() {
    return confirm("⚠️ Are you sure you want to clear all logs? This action cannot be undone.");
}
</script>
@endsection
