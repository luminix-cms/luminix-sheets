<?php

namespace Luminix\Sheets\Exceptions;

use RuntimeException;

/**
 * Thrown when an import file carries more rows than `import.max_rows` allows.
 *
 * The ceiling bounds the request, not the engine: the engine streams in batches
 * either way. What it bounds is how long a synchronous import may run before the
 * work belongs in a queued job instead.
 */
class ImportRowLimitException extends RuntimeException
{
    protected int $limit;

    public function __construct(int $limit)
    {
        $this->limit = $limit;

        parent::__construct(
            __('The import accepts at most :max rows per file.', ['max' => $limit])
        );
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @return array{message: string}
     */
    public function toArray(): array
    {
        return ['message' => $this->getMessage()];
    }
}
