<?php

namespace Workbench\App\Sheets;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\LazyCollection;
use Luminix\Sheets\Default\DefaultExportable;

class InvoiceExport extends DefaultExportable
{
    /** Set by the tests to assert the hooks fire, and in which order. */
    public static array $calls = [];

    /** When set, query() narrows the export to this customer. */
    public static ?string $onlyCustomer = null;

    /** When set, map() throws once it reaches this 1-based row. */
    public static ?int $throwOnRow = null;

    /** When set, replaces the labels headers() declares. */
    public static ?array $labels = null;

    private int $mapped = 0;

    public static function reset(): void
    {
        static::$calls = [];
        static::$onlyCustomer = null;
        static::$throwOnRow = null;
        static::$labels = null;
    }

    public function headers(): array
    {
        return static::$labels ?? ['Número', 'Cliente', 'Total'];
    }

    public function widths(): array
    {
        return ['Número' => 18, 'Cliente' => 40];
    }

    public function map(Model $model): array
    {
        if (static::$throwOnRow !== null && ++$this->mapped >= static::$throwOnRow) {
            throw new \RuntimeException('mapping blew up mid-file');
        }

        return [
            'Número' => $model->number,
            'Cliente' => $model->customer,
            'Total' => number_format((float) $model->total, 2, ',', '.'),
        ];
    }

    public function query(Builder $query): Builder
    {
        if (static::$onlyCustomer !== null) {
            $query->where('customer', static::$onlyCustomer);
        }

        return $query;
    }

    public function fileName(): string
    {
        return 'faturas';
    }

    public function sheetName(): string
    {
        return 'Faturas';
    }

    public function beforeExport(LazyCollection $rows): void
    {
        static::$calls[] = 'before';
    }

    public function afterExport(): void
    {
        static::$calls[] = 'after';
    }
}
