<?php

namespace Luminix\Sheets\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\LazyCollection;

interface ExportsFromSheet
{
    /**
     * Columns to include in the exported file.
     * Return null to resolve them from the model's fillable minus its hidden ones.
     *
     * @return string[]|null
     */
    public function columns(): ?array;

    /**
     * Column labels, in order, written as the first row.
     *
     * This — not the first mapped row — decides the shape of the file, so an
     * export with zero results still carries a header.
     *
     * @return string[]
     */
    public function headers(): array;

    /**
     * Column widths, keyed by the labels returned from headers().
     * Missing entries fall back to a default width.
     *
     * @return array<string, int>
     */
    public function widths(): array;

    /**
     * Map a single model instance to a row, keyed by the labels from headers().
     * A key absent from headers() is not written; a header absent here is blank.
     *
     * @return array<string, mixed>
     */
    public function map(Model $model): array;

    /**
     * Apply additional constraints to the base query before exporting.
     * Receives the already-scoped query (permissions and listing filters applied).
     */
    public function query(Builder $query): Builder;

    /**
     * The file name (without extension) for the download.
     */
    public function fileName(): string;

    /**
     * The worksheet tab name inside the file.
     */
    public function sheetName(): string;

    /**
     * The disk format for the exported file.
     * Supported values: 'xlsx', 'csv', 'ods'
     */
    public function format(): string;

    /**
     * Called once before the first row is written.
     *
     * Receives the lazy result set. Iterating it here loads every row into
     * memory and defeats the streaming export — read from it only when the
     * handler genuinely needs a second pass.
     *
     * @param  LazyCollection<int, Model>  $rows
     */
    public function beforeExport(LazyCollection $rows): void;

    /**
     * Called once after the file has been written to disk and before the
     * response starts streaming it.
     */
    public function afterExport(): void;
}
