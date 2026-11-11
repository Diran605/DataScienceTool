<?php

namespace App\Http\Requests;

use App\Models\Run;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class StoreRunRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Run::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:102400', // 100MB
                'extensions:csv,xlsx,xls,parquet,json,pdf,txt,doc,docx',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.max' => 'The file must not exceed 100MB.',
            'file.extensions' => 'Supported file extensions: CSV, Excel, Parquet, JSON, PDF, TXT, DOC, DOCX.',
        ];
    }

    public function withValidator($validator)
    {
        $raw_files = $_FILES ?? [];
        $file_error = $raw_files['file']['error'] ?? 'missing';

        Log::info('Raw Upload Data:', [
            'raw_files' => $raw_files,
            'file_error' => $file_error,
        ]);
    }
}
