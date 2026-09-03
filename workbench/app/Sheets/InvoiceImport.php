<?php

namespace Workbench\App\Sheets;

use Luminix\Sheets\Default\DefaultImportable;

class InvoiceImport extends DefaultImportable
{
    /** Flipped by the tests covering rollback vs. partial persistence. */
    public static bool $transactional = true;

    public static function reset(): void
    {
        static::$transactional = true;
    }

    /**
     * The sheet carries a title line, so the header is the second row.
     */
    public function headingRow(): int
    {
        return 2;
    }

    /**
     * The header labels are the Portuguese ones InvoiceExport writes, so a file
     * produced by the export can be fed straight back into the import.
     *
     * `id` is mapped on purpose: allowedColumns() must be what drops it, and the
     * engine must apply that even though this handler does not.
     */
    public function map(array $row, int $rowIndex): ?array
    {
        if (($row['Número'] ?? null) === null) {
            return null;
        }

        return [
            'id' => $row['Id'] ?? null,
            'number' => $row['Número'],
            'customer' => $row['Cliente'] ?? null,
            'total' => $this->decimal($row['Total'] ?? null),
        ];
    }

    /**
     * "1.234,56" -> 1234.56
     */
    protected function decimal(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return str_replace(',', '.', str_replace('.', '', $value));
    }

    public function rules(): array
    {
        return [
            'number' => ['required', 'string'],
            'customer' => ['required', 'string'],
            'total' => ['required', 'numeric'],
        ];
    }

    public function messages(): array
    {
        return [
            'total.numeric' => 'O total precisa ser um número.',
        ];
    }

    public function allowedColumns(): ?array
    {
        return ['number', 'customer', 'total'];
    }

    public function useTransaction(): bool
    {
        return static::$transactional;
    }
}
