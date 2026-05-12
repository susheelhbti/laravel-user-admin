<?php

namespace Susheelhbti\LaravelUserAdmin\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Susheelhbti\LaravelUserAdmin\Services\ImportService;

class ImportController extends Controller
{
    public function __construct(protected ImportService $importService) {}

    /**
     * POST /api/admin/users/import
     * Accepts: multipart/form-data with file field 'file' (.csv or .json)
     *
     * CSV expected columns: name, email, role (optional), status (optional)
     * JSON expected: array of objects with same keys
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,json|max:5120',   // 5 MB
        ]);

        try {
            $result = $this->importService->import($request->file('file'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message'  => "Import complete: {$result['imported']} imported, {$result['skipped']} skipped.",
            'imported' => $result['imported'],
            'skipped'  => $result['skipped'],
            'errors'   => $result['errors'],
        ], $result['skipped'] > 0 ? 207 : 200);   // 207 Multi-Status when partial
    }
}
