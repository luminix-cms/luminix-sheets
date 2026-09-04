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
                // Both, and deliberately: `extensions` reads the name the client
                // chose, `mimes` sniffs the content. Laravel's own guidance is
                // never to trust the first on its own.
                'extensions:'.implode(',', $this->formats()),
                'mimes:'.implode(',', $this->mimeExtensions()),
                'max:'.config('luminix.sheets.import.max_file_size_kb', 10240),
            ],
        ];
    }

    public function messages(): array
    {
        $formats = implode(', ', $this->formats());

        return [
            'file.required' => __('Please upload a spreadsheet file.'),
            'file.extensions' => __(
                'Only :formats files are accepted. Re-save the file in one of these formats and try again.',
                ['formats' => $formats]
            ),
            'file.mimes' => __(
                'Only :formats files are accepted. Re-save the file in one of these formats and try again.',
                ['formats' => $formats]
            ),
            'file.max' => __('The uploaded file must not exceed :max KB.'),
        ];
    }

    /**
     * The formats the upload rules accept, as the person uploading names them.
     *
     * @return string[]
     */
    protected function formats(): array
    {
        return (array) config('luminix.sheets.import.formats', ['xlsx']);
    }

    /**
     * The same formats, widened to what content sniffing actually reports.
     *
     * `mimes` compares against the extension guessed from the bytes, and for two
     * of the three formats that is not the extension itself: a csv carries no
     * signature and comes back as `text/plain`, and an xlsx is a zip that only
     * a current magic database identifies as a spreadsheet. Listing the literal
     * format alone would turn away legitimate files. What is left still rejects
     * every disguise that matters — text, images, documents, executables — and
     * a zip that is not a spreadsheet dies at the reader with the same 422.
     *
     * @return string[]
     */
    protected function mimeExtensions(): array
    {
        $sniffed = [
            'xlsx' => ['xlsx', 'zip'],
            'ods' => ['ods'],
            'csv' => ['csv', 'txt'],
        ];

        $extensions = [];

        foreach ($this->formats() as $format) {
            $extensions = [...$extensions, ...($sniffed[$format] ?? [$format])];
        }

        return array_values(array_unique($extensions));
    }
}
