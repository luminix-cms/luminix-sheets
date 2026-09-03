<?php

namespace Workbench\App\Models\Reports;

use Illuminate\Database\Eloquent\Model;
use Workbench\App\Sheets\AlsoExportable;

#[AlsoExportable]
class CustomReport extends Model
{
    protected $fillable = ['title'];
}
