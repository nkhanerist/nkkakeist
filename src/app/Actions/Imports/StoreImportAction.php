<?php

namespace App\Actions\Imports;

use App\Models\Import;
use App\Models\User;
use Illuminate\Http\UploadedFile;

class StoreImportAction
{
    /**
     * @param  array{source_name?: string|null, account_id?: int|null, csv_file: UploadedFile}  $data
     */
    public function handle(User $user, array $data): Import
    {
        /** @var UploadedFile $file */
        $file = $data['csv_file'];
        $storagePath = $file->store('imports/'.$user->id, 'local');

        return $user->imports()->create([
            'account_id' => $data['account_id'] ?? null,
            'source_name' => $data['source_name'] ?? 'money_forward',
            'original_filename' => $file->getClientOriginalName(),
            'storage_path' => $storagePath,
            'status' => 'uploaded',
            'total_rows' => 0,
            'imported_rows' => 0,
            'skipped_rows' => 0,
            'duplicate_rows' => 0,
        ]);
    }
}
