<?php

namespace Luminix\Sheets;

use Attribute;
use Luminix\Sheets\Default\DefaultImportable;

#[Attribute(Attribute::TARGET_CLASS)]
class Importable
{
    public string $handler;

    public function __construct(string $handler = DefaultImportable::class)
    {
        $this->handler = $handler;
    }
}
