<?php

namespace Workbench\App\Tests\Unit;

use Luminix\Sheets\Default\DefaultExportable;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\Tier;
use Workbench\App\Models\Player;
use Workbench\App\Models\Profile;
use Workbench\App\Tests\TestCase;

class DefaultExportableTest extends TestCase
{
    public function test_headers_are_the_titled_fillable_columns_minus_the_hidden_ones(): void
    {
        $handler = new DefaultExportable(Player::class);

        $this->assertSame(
            ['Name', 'Registration', 'Score', 'Active', 'Joined At'],
            $handler->headers()
        );
    }

    public function test_the_file_and_sheet_are_named_after_the_model(): void
    {
        $handler = new DefaultExportable(Player::class);

        $this->assertStringStartsWith('players_', $handler->fileName());
        $this->assertSame('Players', $handler->sheetName());
    }

    public function test_the_format_comes_from_the_configuration(): void
    {
        $handler = new DefaultExportable(Player::class);

        $this->assertSame('xlsx', $handler->format());

        config()->set('luminix.sheets.export.default_format', 'csv');

        $this->assertSame('csv', (new DefaultExportable(Player::class))->format());
    }

    public function test_dates_enums_booleans_and_arrays_reach_the_writer_as_text(): void
    {
        // Booleans and the date format come from the lang files, so the
        // expectations below only hold under a locale the package ships.
        $this->app->setLocale('pt-BR');

        $profile = new Profile([
            'name' => 'Ana',
            'tags' => ['a', 'b'],
            'joined_at' => '2026-03-04 15:30:00',
            'active' => false,
        ]);

        $profile->status = Status::Suspended;
        $profile->tier = Tier::Gold;

        $row = (new DefaultExportable(Profile::class))->map($profile);

        $this->assertSame('ativo', (new DefaultExportable(Profile::class))
            ->map((new Profile)->setAttribute('status', Status::Active))['Status']);

        $this->assertSame('suspenso', $row['Status']);
        $this->assertSame('Gold', $row['Tier']);
        $this->assertSame('["a","b"]', $row['Tags']);
        $this->assertSame('04/03/2026 15:30', $row['Joined At']);
        $this->assertSame('Não', $row['Active']);
    }

    public function test_booleans_and_dates_follow_the_application_locale(): void
    {
        $this->app->setLocale('en');

        $profile = new Profile([
            'joined_at' => '2026-03-04 15:30:00',
            'active' => false,
        ]);

        $row = (new DefaultExportable(Profile::class))->map($profile);

        $this->assertSame('No', $row['Active']);
        $this->assertSame('03/04/2026 15:30', $row['Joined At']);
    }
}
