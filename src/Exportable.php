<?php

namespace Luminix\Sheets;

use Attribute;
use Luminix\Sheets\Default\DefaultExportable;

#[Attribute(Attribute::TARGET_CLASS)]
class Exportable
{
    public string $handler;

    public function __construct(string $handler = DefaultExportable::class)
    {
        $this->handler = $handler;
    }
}
