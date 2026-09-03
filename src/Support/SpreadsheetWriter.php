<?php

namespace Luminix\Sheets\Support;

use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\ODS\Writer as OdsWriter;
use OpenSpout\Writer\WriterInterface;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * Row-at-a-time spreadsheet writer over OpenSpout.
 *
 * Every value is written as a string cell: a registration number such as
 * "007" keeps its leading zeros instead of being coerced to 7.
 */
class SpreadsheetWriter
{
    protected WriterInterface $writer;

    protected string $format;

    public function __construct(string $format = 'xlsx')
    {
        $this->format = static::supports($format) ? $format : 'xlsx';
        $this->writer = $this->makeWriter($this->format);
    }

    public static function supports(string $format): bool
    {
        return in_array($format, ['xlsx', 'csv', 'ods'], true);
    }

    public static function mimeType(string $format): string
    {
        return match ($format) {
            'csv' => 'text/csv',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            default => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };
    }

    public function openToFile(string $path): static
    {
        $this->writer->openToFile($path);

        return $this;
    }

    /**
     * @param  array<string, int>  $widths  Header label => column width
     */
    public function writeHeader(array $widths): static
    {
        $this->sheet(function ($sheet) use ($widths) {
            $position = 1;

            foreach ($widths as $width) {
                $sheet->setColumnWidth((float) $width, $position++);
            }

            $this->freezeHeader($sheet);
        });

        $this->writer->addRow($this->row(array_keys($widths), $this->boldStyle()));

        return $this;
    }

    /**
     * @param  array<int, string|null>  $values
     */
    public function writeRow(array $values): static
    {
        $this->writer->addRow($this->row($values));

        return $this;
    }

    public function name(string $title): static
    {
        $this->sheet(fn ($sheet) => $sheet->setName($title));

        return $this;
    }

    public function close(): void
    {
        $this->writer->close();
    }

    protected function makeWriter(string $format): WriterInterface
    {
        return match ($format) {
            'csv' => new CsvWriter,
            'ods' => new OdsWriter,
            default => new XlsxWriter,
        };
    }

    /**
     * The style goes on the cells, never on the Row: v4's second constructor
     * argument is the row style, v5's is the row height, and v5 dropped
     * row-level styling altogether. Styled cells mean the same thing to both.
     *
     * @param  array<int, string|null>  $values
     */
    protected function row(array $values, ?Style $style = null): Row
    {
        $cells = array_map(
            fn ($value) => new StringCell((string) ($value ?? ''), $style),
            array_values($values)
        );

        return new Row($cells);
    }

    /**
     * Sheet-level styling exists on the multi-sheet writers only; the CSV
     * writer has no sheet to configure.
     */
    protected function sheet(callable $callback): void
    {
        if (! method_exists($this->writer, 'getCurrentSheet')) {
            return;
        }

        $callback($this->writer->getCurrentSheet());
    }

    /**
     * OpenSpout renamed its fluent setters between v4 (`setFontBold`) and v5
     * (`withFontBold`). The package supports both majors because php ^8.2
     * resolves v4 while ^8.3 resolves v5.
     */
    protected function boldStyle(): Style
    {
        $style = new Style;

        if (method_exists($style, 'withFontBold')) {
            return $style->withFontBold(true);
        }

        return $style->setFontBold();
    }

    protected function freezeHeader(object $sheet): void
    {
        $class = '\OpenSpout\Writer\XLSX\Entity\SheetView';

        if ($this->format !== 'xlsx' || ! class_exists($class)) {
            return;
        }

        $view = new $class;

        $sheet->setSheetView(
            method_exists($view, 'withFreezeRow')
                ? $view->withFreezeRow(2)
                : $view->setFreezeRow(2)
        );
    }
}
