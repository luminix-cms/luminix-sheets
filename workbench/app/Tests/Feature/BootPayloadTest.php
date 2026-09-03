<?php

namespace Workbench\App\Tests\Feature;

use Luminix\Frontend\Services\BootService;
use Workbench\App\Tests\TestCase;

class BootPayloadTest extends TestCase
{
    public function test_the_payload_carries_the_accepted_import_formats(): void
    {
        config(['luminix.sheets.import.formats' => ['xlsx', 'csv']]);

        $boot = app(BootService::class)->get();

        $this->assertSame(['xlsx', 'csv'], $boot['luminix']['sheets']['import']['formats']);
    }

    public function test_the_payload_falls_back_to_the_configured_default(): void
    {
        $boot = app(BootService::class)->get();

        $this->assertSame(['xlsx'], $boot['luminix']['sheets']['import']['formats']);
    }
}
