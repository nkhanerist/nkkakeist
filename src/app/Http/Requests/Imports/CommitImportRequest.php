<?php

namespace App\Http\Requests\Imports;

use App\Models\Import;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CommitImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $import = $this->route('import');

        return $import instanceof Import
            && ($this->user()?->can('commit', $import) ?? false);
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [];
    }
}
