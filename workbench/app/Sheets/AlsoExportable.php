<?php

namespace Workbench\App\Sheets;

use Attribute;
use Luminix\Sheets\Exportable;

/**
 * A project-defined subclass of the package attribute.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class AlsoExportable extends Exportable {}
