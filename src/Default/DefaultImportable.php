<?php

namespace Luminix\Sheets\Default;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
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
     * Only keys present in the model's fillable array (and not hidden for import)
     * are kept. All other keys are silently discarded.
     */
    public function map(array $row, int $rowIndex): ?array
    {
        /** @var Model $instance */
        $instance = new $this->modelClass;

        $allowed = $this->resolveAllowedColumns($instance);

        $mapped = [];
        foreach ($row as $column => $value) {
            $column = $this->normalizeKey($column);
            if (in_array($column, $allowed, true)) {
                $mapped[$column] = $value === '' ? null : $value;
            }
        }

        return empty($mapped) ? null : $mapped;
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

    public function afterImport(Collection $imported): void
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

    public function allowedColumns(): ?array
    {
        return null; // resolved dynamically from the model
    }

    // Helpers
    protected function resolveAllowedColumns(Model $instance): array
    {
        if ($this->allowedColumns() !== null) {
            return $this->allowedColumns();
        }

        $fillable = $instance->getFillable();
        $hidden   = HiddenColumns::forImport($instance);

        return array_values(array_diff($fillable, $hidden));
    }

    /**
     * Normalise a header string to snake_case so it matches attribute names.
     * e.g. "First Name" → "first_name"
     */
    protected function normalizeKey(string $key): string
    {
        return str($key)->lower()->replace(' ', '_')->toString();
    }
}
