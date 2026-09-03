<?php

namespace Luminix\Sheets\Default;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Luminix\Sheets\Contracts\ExportsFromSheet;
use Luminix\Sheets\Support\HiddenColumns;

class DefaultExportable implements ExportsFromSheet
{
    protected string $modelClass;

    /** @var array<string, string>|null  Label => attribute name */
    protected ?array $resolved = null;

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

    public function headers(): array
    {
        return array_keys($this->labelled());
    }

    public function widths(): array
    {
        return [];
    }

    /**
     * Maps a model to a row keyed by the labels from headers().
     *
     * Only non-hidden fillable attributes are included by default. Labels are
     * the human-readable version of each attribute name (snake_case → "Title Case").
     */
    public function map(Model $model): array
    {
        $row = [];

        foreach ($this->labelled() as $label => $column) {
            $row[$label] = $this->value($model, $column);
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
        return $this->plural().'_'.now()->format('Y_m_d_His');
    }

    public function sheetName(): string
    {
        return Str::of($this->plural())->replace('_', ' ')->title()->toString();
    }

    public function format(): string
    {
        return (string) config('luminix.sheets.export.default_format', 'xlsx');
    }

    public function beforeExport(LazyCollection $rows): void
    {
        //
    }

    public function afterExport(): void
    {
        //
    }

    // Helpers

    /**
     * @return array<string, string> Label => attribute name
     */
    protected function labelled(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        /** @var Model $instance */
        $instance = new $this->modelClass;

        $labelled = [];

        foreach ($this->resolveColumns($instance) as $column) {
            $labelled[$this->label($column)] = $column;
        }

        return $this->resolved = $labelled;
    }

    protected function label(string $column): string
    {
        return Str::of($column)->replace('_', ' ')->title()->toString();
    }

    /**
     * Dates and enums reach the writer as strings; anything else keeps the raw
     * value, which the writer casts. Arrays would otherwise stringify to "Array".
     */
    protected function value(Model $model, string $column): mixed
    {
        $value = $model->getAttribute($column);

        return match (true) {
            $value instanceof \DateTimeInterface => $value->format('d/m/Y H:i'),
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \UnitEnum => $value->name,
            is_bool($value) => $value ? 'Sim' : 'Não',
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE),
            default => $value,
        };
    }

    protected function resolveColumns(Model $model): array
    {
        if ($this->columns() !== null) {
            return $this->columns();
        }

        $fillable = $model->getFillable();
        $hidden = HiddenColumns::forExport($model);

        return array_values(array_diff($fillable, $hidden));
    }

    protected function plural(): string
    {
        return Str::of($this->modelClass)->classBasename()->snake()->plural()->toString();
    }
}
