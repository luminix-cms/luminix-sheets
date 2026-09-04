<?php

namespace Luminix\Sheets\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

interface ImportsFromSheet
{
    /**
     * Map a row from the spreadsheet to an array of model attributes.
     * Return null to skip the row.
     *
     * @param  array<string, mixed>  $row
     * @param  int  $rowIndex  Zero-based row index (excluding header)
     * @return array<string, mixed>|null
     */
    public function map(array $row, int $rowIndex): ?array;

    /**
     * Validation rules applied to each mapped row before persisting.
     * Return an empty array to skip row-level validation.
     *
     * @return array<string, mixed>
     */
    public function rules(): array;

    /**
     * Custom validation messages for the rules above.
     *
     * @return array<string, string>
     */
    public function messages(): array;

    /**
     * Called once before any rows are processed.
     * Useful for resetting state or preparing resources.
     */
    public function beforeImport(UploadedFile $file): void;

    /**
     * Called once for every batch the engine persists, with the models of that
     * batch alone.
     *
     * This is where per-record post-processing belongs. The engine never holds
     * more than one batch, so a handler that hoards what it receives here is
     * the one thing that can still make an import grow with the file.
     *
     * @param  Collection<int, Model>  $imported
     */
    public function afterChunk(Collection $imported): void;

    /**
     * Called once after every row has been persisted, with the total.
     *
     * It receives a count rather than the models: handing over the whole set
     * would mean keeping it in memory for the length of the import.
     */
    public function afterImport(int $imported): void;

    /**
     * Whether to wrap the entire import in a single database transaction.
     * When true, any row error rolls back all inserts.
     */
    public function useTransaction(): bool;

    /**
     * Number of rows to skip at the top of the sheet (e.g., extra header rows).
     * The first non-skipped row is always treated as the column header.
     */
    public function headingRow(): int;

    /**
     * Columns that are allowed during import.
     * Return null to allow all non-hidden columns.
     *
     * @return string[]|null
     */
    public function allowedColumns(): ?array;
}
