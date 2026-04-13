<?php

namespace Luminix\Sheets\Default;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Luminix\Sheets\Contracts\ExportsFromSheet;
use Luminix\Sheets\Support\HiddenColumns;

class DefaultExportable implements ExportsFromSheet
{
    protected string $modelClass;

    public function __construct(string $modelClass)
    {
        $this->modelClass = $modelClass;
    }

    /**
     * Returns null so the engine resolves columns from the model automatically.
     */
    public function columns(): ?array
    {
        return null;
    }

    /**
     * Maps a model to an associative row array.
     *
     * Only non-hidden fillable attributes are included by default.
     * Column headers are the human-readable version of each attribute name
     * (snake_case → "Title Case").
     */
    public function map(Model $model): array
    {
        $columns = $this->resolveColumns($model);

        $row = [];
        foreach ($columns as $column) {
            $header       = Str::of($column)->replace('_', ' ')->title()->toString();
            $row[$header] = $model->getAttribute($column);
        }

        return $row;
    }

    /**
     * No additional constraints by default; subclasses may override.
     */
    public function query(Builder $query): Builder
    {
        return $query;
    }

    public function fileName(): string
    {
        return Str::of($this->modelClass)->classBasename()->snake()->plural()->toString()
            . '_' . now()->format('Y_m_d_His');
    }

    public function format(): string
    {
        return 'xlsx';
    }

    public function beforeExport(Collection $rows): void
    {
        //
    }

    public function afterExport(): void
    {
        //
    }

    // Helpers
    protected function resolveColumns(Model $model): array
    {
        if ($this->columns() !== null) {
            return $this->columns();
        }

        $fillable = $model->getFillable();
        $hidden   = HiddenColumns::forExport($model);

        return array_values(array_diff($fillable, $hidden));
    }
}
