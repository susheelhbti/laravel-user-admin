<?php

namespace Susheelhbti\LaravelUserAdmin\Services;

use Susheelhbti\LaravelUserAdmin\Events\UserAdminEvents;
use Susheelhbti\LaravelUserAdmin\Models\Role;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

class ImportService
{
    /**
     * Import users from a CSV or JSON file.
     * Returns ['imported' => int, 'skipped' => int, 'errors' => array]
     */
    public function import(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());

        $rows = match ($ext) {
            'csv'  => $this->parseCsv($file->getRealPath()),
            'json' => $this->parseJson($file->getRealPath()),
            default => throw new \InvalidArgumentException("Unsupported file type: {$ext}. Use CSV or JSON."),
        };

        $model     = config('user_admin.user_model', \App\Models\User::class);
        $imported  = 0;
        $skipped   = 0;
        $errors    = [];

        foreach ($rows as $lineNo => $row) {
            $v = Validator::make($row, [
                'name'  => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'role'  => 'nullable|string',
            ]);

            if ($v->fails()) {
                $errors[] = ['line' => $lineNo + 1, 'email' => $row['email'] ?? '?', 'reason' => $v->errors()->first()];
                $skipped++;
                continue;
            }

            if ($model::where('email', $row['email'])->exists()) {
                $errors[] = ['line' => $lineNo + 1, 'email' => $row['email'], 'reason' => 'Email already exists'];
                $skipped++;
                continue;
            }

            $user = $model::create([
                'name'   => $row['name'],
                'email'  => $row['email'],
                'status' => $row['status'] ?? 'active',
            ]);

            $roleSlug = $row['role'] ?? config('user_admin.default_role_slug', 'user');
            $role     = Role::where('slug', $roleSlug)->first();
            if ($role) {
                $user->otpRoles()->attach($role);
            }

            UserAdminEvents::fire(UserAdminEvents::USER_CREATED, [
                'user_id' => $user->id,
                'email'   => $user->email,
                'source'  => 'import',
            ]);

            $imported++;
        }

        UserAdminEvents::fire(UserAdminEvents::BULK_USERS_IMPORTED, [
            'imported' => $imported,
            'skipped'  => $skipped,
        ]);

        return compact('imported', 'skipped', 'errors');
    }

    private function parseCsv(string $path): array
    {
        $rows    = [];
        $headers = null;

        if (($handle = fopen($path, 'r')) === false) {
            throw new \RuntimeException('Could not open file.');
        }

        while (($line = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                $headers = array_map('trim', $line);
                continue;
            }
            if (count($line) === count($headers)) {
                $rows[] = array_combine($headers, $line);
            }
        }

        fclose($handle);
        return $rows;
    }

    private function parseJson(string $path): array
    {
        $data = json_decode(file_get_contents($path), true);

        if (!is_array($data)) {
            throw new \InvalidArgumentException('JSON file must contain an array of user objects.');
        }

        return $data;
    }
}
