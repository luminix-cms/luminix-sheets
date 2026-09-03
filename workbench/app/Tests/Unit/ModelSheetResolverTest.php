<?php

namespace Workbench\App\Tests\Unit;

use InvalidArgumentException;
use Luminix\Sheets\Contracts\ExportsFromSheet;
use Luminix\Sheets\Contracts\ImportsFromSheet;
use Luminix\Sheets\Default\DefaultExportable;
use Luminix\Sheets\Support\ModelSheetResolver;
use Workbench\App\Models\Broken;
use Workbench\App\Models\Invoice;
use Workbench\App\Models\Note;
use Workbench\App\Models\Player;
use Workbench\App\Models\Reports\CustomReport;
use Workbench\App\Models\Reports\MonthlyReport;
use Workbench\App\Models\Tag;
use Workbench\App\Sheets\InvoiceExport;
use Workbench\App\Tests\TestCase;

class ModelSheetResolverTest extends TestCase
{
    public function test_it_reads_the_attributes_off_the_model(): void
    {
        $this->assertTrue(ModelSheetResolver::isExportable(Player::class));
        $this->assertTrue(ModelSheetResolver::isImportable(Player::class));

        $this->assertTrue(ModelSheetResolver::isExportable(Note::class));
        $this->assertFalse(ModelSheetResolver::isImportable(Note::class));

        $this->assertFalse(ModelSheetResolver::isExportable(Tag::class));
    }

    public function test_an_unknown_class_is_neither(): void
    {
        $this->assertFalse(ModelSheetResolver::isExportable('App\\Does\\Not\\Exist'));
        $this->assertNull(ModelSheetResolver::exporter('App\\Does\\Not\\Exist'));
    }

    public function test_the_attribute_is_inherited_from_a_parent_model(): void
    {
        $this->assertTrue(ModelSheetResolver::isExportable(MonthlyReport::class));
        $this->assertInstanceOf(
            DefaultExportable::class,
            ModelSheetResolver::exporter(MonthlyReport::class)
        );
    }

    public function test_a_project_subclass_of_the_attribute_is_recognised(): void
    {
        $this->assertTrue(ModelSheetResolver::isExportable(CustomReport::class));
    }

    public function test_it_builds_the_declared_handler(): void
    {
        $this->assertInstanceOf(InvoiceExport::class, ModelSheetResolver::exporter(Invoice::class));
        $this->assertInstanceOf(ExportsFromSheet::class, ModelSheetResolver::exporter(Invoice::class));
        $this->assertInstanceOf(ImportsFromSheet::class, ModelSheetResolver::importer(Invoice::class));
    }

    public function test_a_handler_that_does_not_implement_the_contract_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(ExportsFromSheet::class);

        ModelSheetResolver::exporter(Broken::class);
    }
}
