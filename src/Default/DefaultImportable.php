<?php

namespace Luminix\Sheets\Default;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Luminix\Sheets\Contracts\ImportsFromSheet;
use Luminix\Sheets\Support\HiddenColumns;

class DefaultImportable implements ImportsFromSheet
{
    protected string $modelClass;

    public function __construct(string $modelClass)
    {
        $this->modelClass = $modelClass;
    }

    /**
     * Maps a spreadsheet row to model attributes.
     *
     * Headers are normalised back to attribute names ("First Name" → first_name).
     * Filtering against allowedColumns() is the engine's job, so a handler that
     * overrides only one of the two still gets both applied.
     */
    public function map(array $row, int $rowIndex): ?array
    {
        $mapped = [];

        foreach ($row as $column => $value) {
            $mapped[$this->normalizeKey((string) $column)] = $value === '' ? null : $value;
        }

        return $mapped === [] ? null : $mapped;
    }

    /**
     * No default validation rules — subclasses should override this.
     */
    public function rules(): array
    {
        return [];
    }

    public function messages(): array
    {
        return [];
    }

    public function beforeImport(UploadedFile $file): void
    {
        //
    }

    public function afterChunk(Collection $imported): void
    {
        //
    }

    public function afterImport(int $imported): void
    {
        //
    }

    public function useTransaction(): bool
    {
        return true;
    }

    public function headingRow(): int
    {
        return 1;
    }

    /**
     * Fillable minus everything hidden for import, including the primary key.
     */
    public function allowedColumns(): ?array
    {
        /** @var Model $instance */
        $instance = new $this->modelClass;

        return array_values(array_diff(
            $instance->getFillable(),
            HiddenColumns::forImport($instance)
        ));
    }

    /**
     * "First Name" → "first_name"
     */
    protected function normalizeKey(string $key): string
    {
        return Str::of($key)->trim()->replace('*', '')->snake()->toString();
    }
}
