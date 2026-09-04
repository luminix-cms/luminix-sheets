<?php

namespace Luminix\Sheets\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when OpenSpout cannot read the uploaded file.
 *
 * The upload rules go by the extension the client chose, so anything at all can
 * reach the reader under a spreadsheet's name. That is a bad request, not a
 * server fault, and it says so — the original error travels as `previous`, for
 * the log, and never in the message handed to the caller.
 */
class UnreadableSheetException extends RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct(
            __('The file could not be read as a spreadsheet. Re-save it and try again.'),
            0,
            $previous
        );
    }

    /**
     * @return array{message: string}
     */
    public function toArray(): array
    {
        return ['message' => $this->getMessage()];
    }
}
