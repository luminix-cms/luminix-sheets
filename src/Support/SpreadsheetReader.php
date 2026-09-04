<?php

namespace Luminix\Sheets\Support;

use Generator;
use Luminix\Sheets\Exceptions\UnreadableSheetException;
use OpenSpout\Common\Exception\OpenSpoutException;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ODS\Reader as OdsReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Row-at-a-time spreadsheet reader over OpenSpout.
 *
 * Yields raw row arrays so the caller never holds the whole file in memory.
 */
class SpreadsheetReader
{
    /**
     * @return Generator<int, array<int, mixed>> 1-based sheet row number => cell values
     */
    public static function rows(string $path, string $format): Generator
    {
        $reader = static::makeReader($format);

        // Every OpenSpout failure becomes the package's own exception: a file
        // that is not a spreadsheet is a bad request, and the caller gets that
        // answer instead of the reader's IOException reaching the handler.
        try {
            $reader->open($path);
        } catch (OpenSpoutException $e) {
            throw new UnreadableSheetException($e);
        }

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $number => $row) {
                    yield $number => $row->toArray();
                }

                break; // active sheet only, mirroring the previous behaviour
            }
        } catch (OpenSpoutException $e) {
            // A file can also go bad halfway through — truncated, or valid zip
            // wrapping broken XML.
            throw new UnreadableSheetException($e);
        } finally {
            $reader->close();
        }
    }

    protected static function makeReader(string $format): ReaderInterface
    {
        return match ($format) {
            'csv' => new CsvReader,
            'ods' => new OdsReader,
            default => new XlsxReader,
        };
    }
}
