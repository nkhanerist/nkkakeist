<?php

namespace App\Actions\Imports;

use App\Models\Import;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DeleteImportAction
{
    public function handle(Import $import): void
    {
        if (
            $import->source_name === 'jre_point'
            && $import->status === 'imported'
            && Import::query()
                ->where('user_id', $import->user_id)
                ->where('account_id', $import->account_id)
                ->where('source_name', 'jre_point')
                ->where('status', 'imported')
                ->where('id', '>', $import->id)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'import' => '後続のJRE POINT取込があるため、この取込は先に削除できません。新しい取込から順に削除してください。',
            ]);
        }

        DB::transaction(function () use ($import): void {
            $import->loadMissing('accountSnapshots.account');

            foreach ($import->accountSnapshots as $snapshot) {
                $metadata = $snapshot->metadata ?? [];

                if (
                    ($metadata['initial_balance_rebased'] ?? false) === true
                    && is_string($metadata['previous_initial_balance'] ?? null)
                    && $snapshot->account !== null
                ) {
                    $snapshot->account->update([
                        'initial_balance' => $metadata['previous_initial_balance'],
                    ]);
                }

                $snapshot->delete();
            }

            $import->transactions()
                ->withTrashed()
                ->get()
                ->each(function ($transaction): void {
                    if ($transaction->trashed()) {
                        $transaction->forceDelete();

                        return;
                    }

                    $transaction->delete();
                });
            $import->importRows()->delete();
            $import->delete();
        });

        if ($import->storage_path !== '') {
            Storage::disk('local')->delete($import->storage_path);
        }
    }
}
