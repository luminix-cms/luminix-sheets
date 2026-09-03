<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Luminix\Backend\Model\LuminixModel;
use Luminix\Sheets\Exportable;
use Luminix\Sheets\Importable;
use Workbench\App\Sheets\InvoiceExport;
use Workbench\App\Sheets\InvoiceImport;

/**
 * The custom-handler fixture: explicit headers, widths, validation rules and a
 * unique column the import can collide with.
 */
#[Exportable(handler: InvoiceExport::class)]
#[Importable(handler: InvoiceImport::class)]
class Invoice extends Model
{
    use LuminixModel;

    protected $fillable = ['number', 'customer', 'total'];
}
