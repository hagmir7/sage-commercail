<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;

class LogViewerController extends Controller
{
    public function show()
    {
        $logPath = storage_path('logs/laravel.log');

        if (!File::exists($logPath)) {
            $logContent = "No log file found at: storage/logs/laravel.log";
        } else {
            $logContent = File::get($logPath); // ← show ALL logs
        }

        return view('logs', compact('logContent'));
    }

    public function clear(Request $request)
    {
        $logPath = storage_path('logs/laravel.log');

        if (File::exists($logPath)) {
            File::put($logPath, ""); // safely truncate file
            return redirect()->route('logs.show')->with('status', '✅ Logs cleared successfully.');
        }

        return redirect()->route('logs.show')->with('status', '⚠️ No log file found.');
    }
}
