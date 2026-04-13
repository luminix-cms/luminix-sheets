<?php

namespace Luminix\Sheets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorization handled by ResourceController macro
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls,csv,ods',
                'max:' . config('luminix.sheets.import.max_file_size_kb', 10240),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please upload a spreadsheet file.',
            'file.mimes' => 'Only xlsx, xls, csv and ods files are accepted.',
            'file.max' => 'The uploaded file must not exceed :max KB.',
        ];
    }
}
