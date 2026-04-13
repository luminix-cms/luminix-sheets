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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Ods;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SheetEngine
{
    /**
     * Process an uploaded spreadsheet file and persist rows to the database.
     *
     * @param  string  $modelClass  Fully-qualified Eloquent model class
     * @param  ImportsFromSheet  $handler
     * @param  UploadedFile  $file
     * @return Collection<int, Model>  The successfully imported models
     *
     * @throws ImportValidationException
     */
    public static function import(
        string $modelClass,
        ImportsFromSheet $handler,
        UploadedFile $file
    ): Collection {

        $handler->beforeImport($file);

        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        // Determine header row index (0-based internal index)
        $headingRow = max(1, $handler->headingRow());
        $headerIndex = $headingRow - 1;

        // Skip rows before the heading
        $rows = array_values($rows);
        $headers = array_map('strval', $rows[$headerIndex] ?? []);

        $dataRows = array_slice($rows, $headingRow); // everything after the header

        $errors = [];
        $mapped = [];

        foreach ($dataRows as $rowIndex => $raw) {
            $row = array_combine($headers, $raw);

            $data = $handler->map($row, $rowIndex);

            if ($data === null) {
                continue; // handler explicitly skipped this row
            }

            $rules = $handler->rules();

            if (!empty($rules)) {
                $validator = Validator::make($data, $rules, $handler->messages());

                if ($validator->fails()) {
                    $errors[$rowIndex + $headingRow + 1] = $validator->errors()->toArray();
                    continue;
                }
            }

            $mapped[] = $data;
        }

        if (!empty($errors)) {
            throw new ImportValidationException($errors);
        }

        $imported = new Collection();

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
     * @param  string  $modelClass
     * @param  ExportsFromSheet  $handler
     * @param  Builder  $query  Already permission-scoped base query
     */
    public static function export(
        string $modelClass,
        ExportsFromSheet $handler,
        Builder $query
    ): StreamedResponse {

        $query = $handler->query($query);
        $rows = $query->get();

        $handler->beforeExport($rows);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = null;
        $rowNum = 1;

        foreach ($rows as $model) {
            $mapped = $handler->map($model);

            // Header (primeira linha)
            if ($headers === null) {
                $headers = array_keys($mapped);

                $col = 1;
                foreach ($headers as $header) {
                    $columnLetter = Coordinate::stringFromColumnIndex($col);
                    $sheet->setCellValue($columnLetter . '1', $header);
                    $col++;
                }

                // Bold no header
                $sheet->getStyle('1:1')->getFont()->setBold(true);

                $rowNum = 2;
            }

            // Dados
            $col = 1;
            foreach ($mapped as $value) {
                $columnLetter = Coordinate::stringFromColumnIndex($col);
                $sheet->setCellValue($columnLetter . $rowNum, $value);
                $col++;
            }

            $rowNum++;
        }

        // Auto-size columns
        if ($headers !== null) {
            foreach (range(1, count($headers)) as $colIndex) {
                $sheet->getColumnDimensionByColumn($colIndex)->setAutoSize(true);
            }
        }

        $format = strtolower($handler->format());
        $fileName = $handler->fileName() . '.' . $format;
        $mimeType = self::mimeType($format);

        $handler->afterExport();

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = self::makeWriter($spreadsheet, $format);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type'        => $mimeType,
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    protected static function makeWriter(Spreadsheet $spreadsheet, string $format): object
    {
        return match ($format) {
            'csv' => new Csv($spreadsheet),
            'ods' => new Ods($spreadsheet),
            default => new Xlsx($spreadsheet),
        };
    }

    protected static function mimeType(string $format): string
    {
        return match ($format) {
            'csv' => 'text/csv',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            default => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };
    }
}
