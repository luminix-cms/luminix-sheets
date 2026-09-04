<?php

namespace Workbench\App\Tests\Unit;

use Luminix\Sheets\Support\SpreadsheetReader;
use Luminix\Sheets\Support\SpreadsheetWriter;
use PHPUnit\Framework\Attributes\DataProvider;
use Workbench\App\Tests\TestCase;

class SpreadsheetWriterTest extends TestCase
{
    /** @var string[] */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_it_declares_the_formats_it_can_write(): void
    {
        $this->assertTrue(SpreadsheetWriter::supports('xlsx'));
        $this->assertTrue(SpreadsheetWriter::supports('csv'));
        $this->assertTrue(SpreadsheetWriter::supports('ods'));
        $this->assertFalse(SpreadsheetWriter::supports('xls'));
        $this->assertFalse(SpreadsheetWriter::supports('pdf'));
    }

    public function test_it_maps_each_format_to_a_mime_type(): void
    {
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            SpreadsheetWriter::mimeType('xlsx')
        );
        $this->assertSame('text/csv', SpreadsheetWriter::mimeType('csv'));
        $this->assertSame(
            'application/vnd.oasis.opendocument.spreadsheet',
            SpreadsheetWriter::mimeType('ods')
        );
    }

    #[DataProvider('formats')]
    public function test_it_writes_a_readable_file(string $format): void
    {
        $path = $this->write($format, ['Nome' => 20, 'Matrícula' => 15]);

        $rows = iterator_to_array(SpreadsheetReader::rows($path, $format));

        $this->assertSame(['Nome', 'Matrícula'], $rows[1]);
        $this->assertSame(['Ana', '007'], $rows[2]);
    }

    public static function formats(): array
    {
        return [
            'xlsx' => ['xlsx'],
            'csv' => ['csv'],
            'ods' => ['ods'],
        ];
    }

    /**
     * An unsupported format must not produce a file the caller cannot open.
     */
    public function test_an_unknown_format_falls_back_to_xlsx(): void
    {
        $path = $this->write('xls', ['Nome' => 20, 'Matrícula' => 15]);

        $rows = iterator_to_array(SpreadsheetReader::rows($path, 'xlsx'));

        $this->assertSame(['Ana', '007'], $rows[2]);
    }

    public function test_nulls_are_written_as_empty_cells(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'writer-').'.csv';
        $this->files[] = $path;

        $writer = (new SpreadsheetWriter('csv'))->openToFile($path);
        $writer->writeHeader(['A', 'B'], ['A' => 10, 'B' => 10]);
        $writer->writeRow([null, 'x']);
        $writer->close();

        $rows = iterator_to_array(SpreadsheetReader::rows($path, 'csv'));

        $this->assertSame(['', 'x'], $rows[2]);
    }

    public function test_the_worksheet_carries_the_given_name(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'writer-').'.xlsx';
        $this->files[] = $path;

        $writer = (new SpreadsheetWriter('xlsx'))->openToFile($path);
        $writer->name('Relatório');
        $writer->writeHeader(['A'], ['A' => 10]);
        $writer->close();

        $this->assertSame('Relatório', $this->readSheetName(file_get_contents($path)));
    }

    /**
     * @param  array<string, int>  $header
     */
    private function write(string $format, array $header): string
    {
        $path = tempnam(sys_get_temp_dir(), 'writer-').'.'.$format;
        $this->files[] = $path;

        $writer = (new SpreadsheetWriter($format))->openToFile($path);
        $writer->name('Teste');
        $writer->writeHeader(array_keys($header), $header);
        $writer->writeRow(['Ana', '007']);
        $writer->close();

        return $path;
    }
}
