<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SqlEditorController extends Controller
{
    public function show()
    {
        // Optional: prevent on production
        if (app()->environment('production')) {
            abort(403, 'SQL editor disabled on production.');
        }

        return view('sql_editor');
    }

    public function execute(Request $request)
    {
        if (app()->environment('production')) {
            return back()->withErrors(['env' => 'SQL editor disabled on production.']);
        }

        $request->validate([
            'sql' => 'required|string',
            // optionally limit length
        ]);

        $sql = $request->input('sql');

        // Basic safety: reject multi-statement batches and dangerous keywords if you want
        // Example simple blacklist:
        $dangerous = ['drop ', 'truncate ', 'alter ', 'create user', 'grant ', 'revoke '];
        $lower = strtolower($sql);
        foreach ($dangerous as $d) {
            if (str_contains($lower, $d)) {
                return back()->withErrors(['sql' => 'This operation is not allowed via the web editor.']);
            }
        }

        // Detect SELECT vs other — very simple detector
        $isSelect = preg_match('/^\s*(select|with)\b/i', $sql) === 1;

        try {
            if ($isSelect) {
                // Use select so results are returned
                $rows = DB::select($sql);
                // Convert to array of associative arrays for blade table rendering
                $data = array_map(function ($r) { return (array)$r; }, $rows);
                $columns = count($data) ? array_keys($data[0]) : [];
                return view('sql_editor', [
                    'sql' => $sql,
                    'resultRows' => $data,
                    'resultColumns' => $columns,
                    'message' => count($data) . ' row(s) returned',
                ]);
            } else {
                // Non-select: execute and return affected rows if possible
                $affected = DB::affectingStatement($sql); // returns number of affected rows
                return view('sql_editor', [
                    'sql' => $sql,
                    'message' => ($affected === null ? 'Query executed.' : "$affected row(s) affected"),
                ]);
            }
        } catch (\Throwable $e) {
            // Log the exception for admins
            Log::error('SQL Editor error: ' . $e->getMessage(), [
                'sql' => $sql,
                'user_id' => $request->user()?->id,
            ]);

            return back()->withInput()->withErrors(['sql' => 'Error executing SQL: ' . $e->getMessage()]);
        }
    }
}
