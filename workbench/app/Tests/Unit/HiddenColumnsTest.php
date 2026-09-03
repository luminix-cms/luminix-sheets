<?php

namespace Workbench\App\Tests\Unit;

use Luminix\Sheets\Support\HiddenColumns;
use Workbench\App\Models\Legacy;
use Workbench\App\Models\Player;
use Workbench\App\Models\Uninitialized;
use Workbench\App\Tests\TestCase;

class HiddenColumnsTest extends TestCase
{
    /**
     * Reading `$model->sheetsHidden` from outside the class falls through
     * Eloquent's __get, finds no such attribute and yields null. The property
     * has to be read by reflection or the declaration is silently ignored.
     */
    public function test_a_protected_property_is_honoured(): void
    {
        $this->assertContains('secret_note', HiddenColumns::forExport(new Player));
        $this->assertContains('secret_note', HiddenColumns::forImport(new Player));
    }

    public function test_a_method_declaration_is_honoured(): void
    {
        $this->assertContains('internal', HiddenColumns::forExport(new Legacy));
        $this->assertNotContains('internal', HiddenColumns::forImport(new Legacy));
    }

    public function test_it_falls_back_to_the_models_hidden_attributes(): void
    {
        $this->assertContains('token', HiddenColumns::forExport(new Legacy));
        $this->assertContains('token', HiddenColumns::forExport(new Uninitialized));
    }

    public function test_the_primary_key_is_never_importable(): void
    {
        $this->assertContains('id', HiddenColumns::forImport(new Player));
        $this->assertNotContains('id', HiddenColumns::forExport(new Player));
    }
}
