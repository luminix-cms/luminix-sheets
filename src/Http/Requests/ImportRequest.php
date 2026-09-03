<?php

namespace Luminix\Sheets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the ResourceController macro gates before resolving this
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'extensions:'.implode(',', $this->formats()),
                'max:'.config('luminix.sheets.import.max_file_size_kb', 10240),
            ],
        ];
    }

    public function messages(): array
    {
        $formats = implode(', ', $this->formats());

        return [
            'file.required' => 'Please upload a spreadsheet file.',
            'file.extensions' => "Only {$formats} files are accepted. Re-save the file in one of these formats and try again.",
            'file.max' => 'The uploaded file must not exceed :max KB.',
        ];
    }

    /**
     * @return string[]
     */
    protected function formats(): array
    {
        return (array) config('luminix.sheets.import.formats', ['xlsx']);
    }
}
