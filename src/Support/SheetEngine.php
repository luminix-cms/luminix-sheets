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
use Luminix\Sheets\Exceptions\ImportRowLimitException;
use Luminix\Sheets\Exceptions\ImportValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SheetEngine
{
    /**
     * Process an uploaded spreadsheet file and persist rows to the database.
     *
     * Rows are read, mapped, validated and persisted one batch at a time, so
     * nothing larger than a single batch is ever held: an import costs the same
     * memory whether the file carries a hundred rows or a hundred thousand.
     *
     * @param  string  $modelClass  Fully-qualified Eloquent model class
     * @return int How many rows were persisted
     *
     * @throws ImportValidationException
     * @throws ImportRowLimitException
     */
    public static function import(
        string $modelClass,
        ImportsFromSheet $handler,
        UploadedFile $file
    ): int {
        $handler->beforeImport($file);

        $run = fn (): int => static::read($modelClass, $handler, $file);

        $imported = $handler->useTransaction()
            ? DB::transaction($run)
            : $run();

        $handler->afterImport($imported);

        return $imported;
    }

    /**
     * The streaming pass: every row is mapped, validated and buffered until the
     * buffer reaches a batch, then written and dropped.
     *
     * The whole file is read even after the first invalid row, so the caller
     * gets every bad row at once rather than one per upload. Persistence stops
     * at that first error: inside a transaction the batches already written are
     * rolled back by the throw, and without one they are all that survives —
     * which is what running an import without a transaction means.
     *
     * @throws ImportValidationException
     * @throws ImportRowLimitException
     */
    protected static function read(
        string $modelClass,
        ImportsFromSheet $handler,
        UploadedFile $file
    ): int {
        $headingRow = max(1, $handler->headingRow());
        $format = strtolower($file->getClientOriginalExtension() ?: 'xlsx');

        $chunk = max(1, (int) config('luminix.sheets.import.chunk_size', 500));
        $maxRows = config('luminix.sheets.import.max_rows');

        $headers = null;
        $errors = [];
        $buffer = [];
        $index = 0;
        $read = 0;
        $imported = 0;

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

            if ($maxRows !== null && ++$read > (int) $maxRows) {
                throw new ImportRowLimitException((int) $maxRows);
            }

            $rules = $handler->rules();

            if (! empty($rules)) {
                $validator = Validator::make($data, $rules, $handler->messages());

                if ($validator->fails()) {
                    $errors[$number] = $validator->errors()->toArray();

                    continue;
                }
            }

            if ($errors !== []) {
                continue;
            }

            $buffer[] = $data;

            if (count($buffer) >= $chunk) {
                $imported += static::persist($modelClass, $handler, $buffer);
            }
        }

        if ($errors === [] && $buffer !== []) {
            $imported += static::persist($modelClass, $handler, $buffer);
        }

        if ($errors !== []) {
            throw new ImportValidationException($errors);
        }

        return $imported;
    }

    /**
     * Saves one batch and hands it to the handler, then empties the buffer.
     *
     * Rows are saved one by one rather than mass-inserted so model events,
     * casts and timestamps keep working; what the batching buys is memory, not
     * round-trips.
     *
     * @param  array<int, array<string, mixed>>  $buffer  Emptied in place
     */
    protected static function persist(
        string $modelClass,
        ImportsFromSheet $handler,
        array &$buffer
    ): int {
        /** @var Collection<int, Model> $imported */
        $imported = new Collection;

        foreach ($buffer as $attributes) {
            /** @var Model $model */
            $model = new $modelClass;
            $model->fill($attributes);
            $model->save();
            $imported->push($model);
        }

        $buffer = [];

        $handler->afterChunk($imported);

        return $imported->count();
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
                // PHP kills the script the moment the client hangs up during
                // output, and this unlink is the only thing that removes the
                // file — an aborted download would leave it in the system temp
                // dir for good. Finishing the callback costs nothing: writes to
                // a closed socket fail immediately.
                ignore_user_abort(true);

                try {
                    readfile($path);
                } finally {
                    @unlink($path);
                }
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
        $headers = array_values($handler->headers());

        $writer->writeHeader($headers, $handler->widths());

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
