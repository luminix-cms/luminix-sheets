<?php

namespace Luminix\Sheets\Exceptions;

use RuntimeException;

/**
 * Thrown when one or more rows in an import file fail validation.
 *
 * The $errors array is keyed by the 1-based spreadsheet row number and contains
 * the Laravel validation error bag for that row.
 *
 * Example structure:
 *   [
 *     3 => ['email' => ['The email field is required.']],
 *     7 => ['name'  => ['The name may not be greater than 255 characters.']],
 *   ]
 */
class ImportValidationException extends RuntimeException
{
    /** @var array<int, array<string, string[]>> */
    protected array $errors;

    public function __construct(array $errors, string $message = '')
    {
        $this->errors = $errors;

        parent::__construct(
            $message ?: 'The import file contains ' . count($errors) . ' row(s) with validation errors.'
        );
    }

    /** @return array<int, array<string, string[]>> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Convert to an array suitable for a JSON response body.
     *
     * @return array{message: string, errors: array<int, array<string, string[]>>}
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'errors' => $this->errors,
        ];
    }
}
