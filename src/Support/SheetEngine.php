<?php

namespace Luminix\Sheets\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Luminix\Sheets\Contracts\ExportsFromSheet;
use Luminix\Sheets\Contracts\ImportsFromSheet;
use Luminix\Sheets\Exceptions\ImportValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SheetEngine
{
    /**
     * Process an uploaded spreadsheet file and persist rows to the database.
     *
     * @param  string  $modelClass  Fully-qualified Eloquent model class
     * @return Collection<int, Model> The successfully imported models
     *
     * @throws ImportValidationException
     */
    public static function import(
        string $modelClass,
        ImportsFromSheet $handler,
        UploadedFile $file
    ): Collection {
        $handler->beforeImport($file);

        $headingRow = max(1, $handler->headingRow());
        $format = strtolower($file->getClientOriginalExtension() ?: 'xlsx');

        $headers = null;
        $errors = [];
        $mapped = [];
        $index = 0;

        foreach (SpreadsheetReader::rows($file->getRealPath(), $format) as $number => $raw) {
            if ($number < $headingRow) {
                continue;
            }

            if ($number === $headingRow) {
                $headers = array_map(
                    fn ($header) => (string) $header,
                    $raw
                );

                continue;
            }

            $row = static::combine($headers ?? [], $raw);

            $data = $handler->map($row, $index++);

            if ($data === null) {
                continue;
            }

            // Enforced here, not only inside the default handler: a handler
            // written straight against the contract would otherwise bypass it.
            $allowed = $handler->allowedColumns();

            if ($allowed !== null) {
                $data = array_intersect_key($data, array_flip($allowed));
            }

            if ($data === []) {
                continue;
            }

            $rules = $handler->rules();

            if (! empty($rules)) {
                $validator = Validator::make($data, $rules, $handler->messages());

                if ($validator->fails()) {
                    $errors[$number] = $validator->errors()->toArray();

                    continue;
                }
            }

            $mapped[] = $data;
        }

        if (! empty($errors)) {
            throw new ImportValidationException($errors);
        }

        $imported = new Collection;

        $persist = function () use ($modelClass, $mapped, &$imported) {
            foreach ($mapped as $attributes) {
                /** @var Model $model */
                $model = new $modelClass;
                $model->fill($attributes);
                $model->save();
                $imported->push($model);
            }
        };

        if ($handler->useTransaction()) {
            DB::transaction($persist);
        } else {
            $persist();
        }

        $handler->afterImport($imported);

        return $imported;
    }

    /**
     * Build a streamed download response for the given query + handler.
     *
     * The file is written to a temporary path *before* the response is
     * returned: a failure halfway through the batches surfaces as a 500
     * rather than a 200 carrying a truncated attachment.
     *
     * @param  Builder  $query  Already permission-scoped base query
     */
    public static function export(
        string $modelClass,
        ExportsFromSheet $handler,
        Builder $query
    ): StreamedResponse {
        $format = strtolower($handler->format());
        $fileName = $handler->fileName().'.'.$format;

        $path = tempnam(sys_get_temp_dir(), 'luminix-sheet-');

        try {
            static::write($path, $format, $handler, $query);
        } catch (\Throwable $e) {
            @unlink($path);

            throw $e;
        }

        $handler->afterExport();

        return response()->stream(
            function () use ($path) {
                readfile($path);
                @unlink($path);
            },
            200,
            [
                'Content-Type' => SpreadsheetWriter::mimeType($format),
                'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]
        );
    }

    /**
     * Streams the query into the file one chunk at a time. Nothing larger than
     * a single chunk is ever held in memory.
     */
    protected static function write(
        string $path,
        string $format,
        ExportsFromSheet $handler,
        Builder $query
    ): void {
        $chunk = (int) config('luminix.sheets.export.chunk_size', 1000);
        $maxRows = config('luminix.sheets.export.max_rows');

        $writer = (new SpreadsheetWriter($format))->openToFile($path);
        $writer->name($handler->sheetName());

        $rows = $handler->query($query)->lazy(max(1, $chunk));

        $handler->beforeExport($rows);

        // The header comes from the handler, not from the first mapped row, so
        // an export with no results still produces a readable file.
        $headers = $handler->headers();
        $widths = $handler->widths();

        $writer->writeHeader(array_combine(
            $headers,
            array_map(fn ($header) => $widths[$header] ?? 20, $headers)
        ));

        $written = 0;

        foreach ($rows as $model) {
            if ($maxRows !== null && $written >= (int) $maxRows) {
                break;
            }

            $mapped = $handler->map($model);

            $writer->writeRow(array_map(
                fn ($header) => $mapped[$header] ?? null,
                $headers
            ));

            $written++;
        }

        $writer->close();
    }

    /**
     * Pairs a data row with the header row, tolerating the ragged rows real
     * spreadsheets produce: a short row pads with null, a long one is cut.
     * `array_combine` would fatal on both.
     *
     * @param  array<int, string>  $headers
     * @param  array<int, mixed>  $raw
     * @return array<string, mixed>
     */
    protected static function combine(array $headers, array $raw): array
    {
        $row = [];

        foreach (array_values($headers) as $position => $header) {
            if ($header === '') {
                continue;
            }

            $row[$header] = $raw[$position] ?? null;
        }

        return $row;
    }
}
