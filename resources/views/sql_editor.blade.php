@extends('layouts.app')

@section('title', 'SQL Editor')
@section('header', 'SQL Query Executor')

@section('content')
<div class="max-w-7xl mx-auto bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 9l4-4 4 4m0 6l-4 4-4-4" />
            </svg>
            SQL Editor
        </h1>
        <p class="text-sm text-gray-500">Execute SQL queries safely in your database.</p>
    </div>

    {{-- Alerts --}}
    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl mb-4">
            <ul class="list-disc ml-5 space-y-1">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(session('status'))
        <div class="bg-green-50 border border-green-200 text-green-700 p-4 rounded-xl mb-4">
            {{ session('status') }}
        </div>
    @endif

    {{-- SQL Editor --}}
    <form method="POST" action="{{ route('sql-editor.execute') }}" class="space-y-4">
        @csrf

        <label for="sql-editor" class="block text-gray-700 font-medium">SQL Query</label>

        <div class="border border-gray-300 rounded-lg overflow-hidden shadow-inner">
            <textarea id="sql-editor" name="sql" rows="10" 
                class="hidden">{{ old('sql', $sql ?? '') }}</textarea>
        </div>

        <div class="flex items-center gap-3 pt-3">
            <button type="submit"
                class="inline-flex items-center px-5 py-2.5 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 active:scale-[0.98] transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4" />
                </svg>
                Run Query
            </button>

            <button type="button" id="btn-clear"
                class="px-4 py-2.5 bg-gray-100 text-gray-700 rounded-lg border hover:bg-gray-200 active:scale-[0.98] transition">
                Clear
            </button>
        </div>
    </form>

    {{-- Query Result --}}
    @if(isset($message))
        <div class="mt-6 text-gray-700 text-sm border-t border-gray-200 pt-3">
            <span class="font-semibold text-gray-800">Result:</span> {{ $message }}
        </div>
    @endif

    @if(!empty($resultRows))
        <div class="mt-6 overflow-x-auto rounded-lg border border-gray-200 shadow-sm">
            <table class="min-w-full text-sm text-left text-gray-700">
                <thead class="bg-gray-50 text-gray-800 font-medium">
                    <tr>
                        @foreach($resultColumns as $col)
                            <th class="px-4 py-2 border-b border-gray-200 whitespace-nowrap">{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($resultRows as $row)
                        <tr class="hover:bg-gray-50 transition">
                            @foreach($resultColumns as $col)
                                <td class="px-4 py-2 text-gray-600 whitespace-nowrap">
                                    {{ is_null($row[$col]) ? 'NULL' : (string)$row[$col] }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- CodeMirror Integration --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.12/codemirror.min.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.12/theme/material-palenight.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.12/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.12/mode/sql/sql.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const textarea = document.getElementById('sql-editor');

    const editor = CodeMirror.fromTextArea(textarea, {
        mode: 'text/x-sql',
        lineNumbers: true,
        theme: 'material-palenight',
        indentWithTabs: true,
        smartIndent: true,
        matchBrackets: true,
        autofocus: true,
        lineWrapping: true,
    });

    // Add smooth focus shadow
    editor.on('focus', () => editor.getWrapperElement().classList.add('ring-2', 'ring-blue-500', 'ring-offset-1'));
    editor.on('blur', () => editor.getWrapperElement().classList.remove('ring-2', 'ring-blue-500', 'ring-offset-1'));

    document.getElementById('btn-clear').addEventListener('click', () => editor.setValue(''));
});
</script>
@endsection
