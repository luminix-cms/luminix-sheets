<?php

namespace Workbench\App\Tests\Feature;

use Workbench\App\Models\Invoice;
use Workbench\App\Models\Player;
use Workbench\App\Models\Tag;
use Workbench\App\Providers\WorkbenchServiceProvider;
use Workbench\App\Sheets\InvoiceExport;
use Workbench\App\Tests\TestCase;

class ExportTest extends TestCase
{
    // Autorização

    public function test_guests_cannot_export(): void
    {
        $this->json('GET', '/luminix-api/players/export')->assertStatus(401);
    }

    public function test_export_is_refused_when_the_gate_denies_read(): void
    {
        WorkbenchServiceProvider::$denied = ['read-player'];

        $this->actingAs($this->user())
            ->json('GET', '/luminix-api/players/export')
            ->assertStatus(401);
    }

    public function test_export_is_not_found_for_a_model_without_the_attribute(): void
    {
        Tag::create(['label' => 'x']);

        $this->actingAs($this->user())
            ->json('GET', '/luminix-api/tags/export')
            ->assertStatus(404);
    }

    // Conteúdo do arquivo

    public function test_export_returns_an_xlsx_attachment(): void
    {
        $this->player('Ana');

        $response = $this->actingAs($this->user())
            ->get('/luminix-api/players/export')
            ->assertStatus(200)
            ->assertHeader(
                'Content-Type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            );

        $this->assertStringContainsString(
            'attachment; filename="players_',
            $response->headers->get('Content-Disposition')
        );
    }

    public function test_export_writes_a_header_row_even_with_no_results(): void
    {
        $rows = $this->export('/luminix-api/players/export');

        $this->assertCount(1, $rows);
        $this->assertSame(
            ['Name', 'Registration', 'Score', 'Active', 'Joined At'],
            $rows[0]
        );
    }

    /**
     * `secret_note` is in $sheetsHidden and not in $hidden — see Player.
     */
    public function test_export_omits_columns_declared_in_sheets_hidden(): void
    {
        $this->player('Ana', ['secret_note' => 'classificado']);

        $rows = $this->export('/luminix-api/players/export');

        $this->assertNotContains('Secret Note', $rows[0]);
        $this->assertNotContains('classificado', $rows[1]);
    }

    public function test_export_keeps_leading_zeros(): void
    {
        $this->player('Ana', ['registration' => '007']);

        $rows = $this->export('/luminix-api/players/export');

        $this->assertSame('007', $rows[1][1]);
    }

    public function test_export_formats_dates_booleans_and_numbers(): void
    {
        $this->player('Ana', [
            'score' => 42,
            'active' => false,
            'joined_at' => '2026-03-04 15:30:00',
        ]);

        $rows = $this->export('/luminix-api/players/export');

        $this->assertSame('42', $rows[1][2]);
        $this->assertSame('Não', $rows[1][3]);
        $this->assertSame('04/03/2026 15:30', $rows[1][4]);
    }

    // Escopo e filtros

    public function test_export_respects_the_allowed_scope(): void
    {
        $this->player('Ana');
        $this->player('Beto', ['banned' => true]);

        $rows = $this->export('/luminix-api/players/export');

        $this->assertCount(2, $rows);
        $this->assertSame('Ana', $rows[1][0]);
    }

    public function test_export_respects_the_search_term(): void
    {
        $this->player('Ana');
        $this->player('Beto');

        $rows = $this->export('/luminix-api/players/export?q=Bet');

        $this->assertCount(2, $rows);
        $this->assertSame('Beto', $rows[1][0]);
    }

    public function test_export_respects_the_where_filter(): void
    {
        $this->player('Ana', ['score' => 10]);
        $this->player('Beto', ['score' => 90]);

        $rows = $this->export('/luminix-api/players/export?where[score:greaterThan]=50');

        $this->assertCount(2, $rows);
        $this->assertSame('Beto', $rows[1][0]);
    }

    public function test_export_respects_the_ordering(): void
    {
        $this->player('Ana', ['score' => 10]);
        $this->player('Beto', ['score' => 90]);

        $rows = $this->export('/luminix-api/players/export?order_by=score:desc');

        $this->assertSame('Beto', $rows[1][0]);
        $this->assertSame('Ana', $rows[2][0]);
    }

    // Streaming

    public function test_export_covers_every_row_across_chunk_boundaries(): void
    {
        config()->set('luminix.sheets.export.chunk_size', 2);

        foreach (range(1, 7) as $i) {
            $this->player('Player '.$i);
        }

        $rows = $this->export('/luminix-api/players/export');

        $this->assertCount(8, $rows); // header + 7
        $this->assertSame('Player 7', $rows[7][0]);
    }

    public function test_export_stops_at_the_configured_row_ceiling(): void
    {
        config()->set('luminix.sheets.export.max_rows', 3);

        foreach (range(1, 7) as $i) {
            $this->player('Player '.$i);
        }

        $rows = $this->export('/luminix-api/players/export');

        $this->assertCount(4, $rows); // header + 3
    }

    // Handler declarado

    public function test_export_uses_the_declared_handler(): void
    {
        Invoice::create(['number' => 'NF-1', 'customer' => 'Acme', 'total' => 1234.5]);

        $response = $this->actingAs($this->user())
            ->get('/luminix-api/invoices/export')
            ->assertStatus(200);

        $contents = $response->streamedContent();
        $rows = $this->readSheet($contents);

        $this->assertSame(['Número', 'Cliente', 'Total'], $rows[0]);
        $this->assertSame(['NF-1', 'Acme', '1.234,50'], $rows[1]);
        $this->assertSame('Faturas', $this->readSheetName($contents));
        $this->assertStringContainsString(
            'filename="faturas.xlsx"',
            $response->headers->get('Content-Disposition')
        );
    }

    public function test_export_applies_the_handler_query_hook(): void
    {
        Invoice::create(['number' => 'NF-1', 'customer' => 'Acme', 'total' => 1]);
        Invoice::create(['number' => 'NF-2', 'customer' => 'Globex', 'total' => 2]);

        InvoiceExport::$onlyCustomer = 'Globex';

        $rows = $this->export('/luminix-api/invoices/export');

        $this->assertCount(2, $rows);
        $this->assertSame('NF-2', $rows[1][0]);
    }

    public function test_after_export_runs_once_the_file_is_complete(): void
    {
        Invoice::create(['number' => 'NF-1', 'customer' => 'Acme', 'total' => 1]);

        $this->export('/luminix-api/invoices/export');

        $this->assertSame(['before', 'after'], InvoiceExport::$calls);
    }

    /**
     * A failure halfway through must not reach the client as a 200 carrying a
     * truncated attachment — and must not leave the scratch file behind.
     */
    public function test_a_failure_mid_file_surfaces_as_an_error_and_cleans_up(): void
    {
        foreach (range(1, 5) as $i) {
            Invoice::create(['number' => 'NF-'.$i, 'customer' => 'Acme', 'total' => $i]);
        }

        InvoiceExport::$throwOnRow = 3;

        $before = $this->scratchFiles();

        $response = $this->actingAs($this->user())
            ->get('/luminix-api/invoices/export');

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(['before'], InvoiceExport::$calls);
        $this->assertSame($before, $this->scratchFiles());
    }

    // Helpers

    private function player(string $name, array $attributes = []): Player
    {
        $banned = (bool) ($attributes['banned'] ?? false);
        unset($attributes['banned']);

        $player = Player::create(array_merge([
            'name' => $name,
            'registration' => null,
            'score' => 0,
            'active' => true,
        ], $attributes));

        // `banned` is guarded on purpose — it is a permission column, not an
        // importable one.
        $player->forceFill(['banned' => $banned])->save();

        return $player;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function export(string $url): array
    {
        $response = $this->actingAs($this->user())
            ->get($url)
            ->assertStatus(200);

        return $this->readSheet($response->streamedContent());
    }

    private function scratchFiles(): int
    {
        return count(glob(sys_get_temp_dir().'/luminix-sheet-*') ?: []);
    }
}
