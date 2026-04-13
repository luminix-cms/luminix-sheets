<?php

namespace Luminix\Sheets\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface ExportsFromSheet
{
    /**
     * Columns to include in the exported file.
     * By default, all non-hidden fillable columns are exported.
     * Return null to use the default column resolution.
     *
     * @return string[]|null
     */
    public function columns(): ?array;

    /**
     * Map a single model instance to a row array.
     * Keys become column headers (on first row).
     *
     * @return array<string, mixed>
     */
    public function map(Model $model): array;

    /**
     * Apply additional constraints to the base query before exporting.
     * Receives the already-scoped query (permissions applied).
     */
    public function query(Builder $query): Builder;

    /**
     * The file name (without extension) for the download.
     */
    public function fileName(): string;

    /**
     * The disk format for the exported file.
     * Supported values: 'xlsx', 'csv', 'ods'
     */
    public function format(): string;

    /**
     * Called once before the export file is written.
     *
     * @param  Collection<int, Model>  $rows
     */
    public function beforeExport(Collection $rows): void;

    /**
     * Called once after the export file has been written and is ready to stream.
     */
    public function afterExport(): void;
}
