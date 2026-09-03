<?php

namespace Workbench\App\Tests\Feature;

use Luminix\Sheets\LuminixSheetsServiceProvider;
use Workbench\App\Models\Player;
use Workbench\App\Providers\WorkbenchServiceProvider;
use Workbench\App\Tests\TestCase;

/**
 * luminix/backend's own permission map has no import/export entry, so the
 * package resolves the verbs from its own configuration.
 */
class PermissionTest extends TestCase
{
    public function test_the_verbs_come_from_the_package_configuration(): void
    {
        $this->assertSame('read', LuminixSheetsServiceProvider::permissionFor('export'));
        $this->assertSame('create', LuminixSheetsServiceProvider::permissionFor('import'));
        $this->assertNull(LuminixSheetsServiceProvider::permissionFor('nonsense'));
    }

    public function test_the_configured_verb_is_the_one_checked(): void
    {
        config()->set('luminix.sheets.permissions.export', 'update');

        WorkbenchServiceProvider::$denied = ['update-player'];

        $this->actingAs($this->user())
            ->json('GET', '/luminix-api/players/export')
            ->assertStatus(401);
    }

    /**
     * A null verb turns off the gate AND the row scope: the caller sees every
     * row, banned included.
     */
    public function test_a_null_verb_opens_the_action(): void
    {
        config()->set('luminix.sheets.permissions.export', null);

        Player::create(['name' => 'Ana'])->forceFill(['banned' => true])->save();

        $rows = $this->readSheet(
            $this->get('/luminix-api/players/export')
                ->assertStatus(200)
                ->streamedContent()
        );

        $this->assertCount(2, $rows);
    }

    public function test_disabling_gates_globally_bypasses_the_check(): void
    {
        config()->set('luminix.backend.security.gates_enabled', false);

        WorkbenchServiceProvider::$denied = ['read-player'];

        $this->actingAs($this->user())
            ->get('/luminix-api/players/export')
            ->assertStatus(200);
    }
}
