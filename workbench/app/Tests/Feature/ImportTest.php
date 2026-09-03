<?php

namespace Workbench\App\Tests\Feature;

use Workbench\App\Models\Invoice;
use Workbench\App\Models\Player;
use Workbench\App\Providers\WorkbenchServiceProvider;
use Workbench\App\Sheets\InvoiceImport;
use Workbench\App\Tests\TestCase;

class ImportTest extends TestCase
{
    // Autorização

    public function test_guests_cannot_import(): void
    {
        $this->json('POST', '/luminix-api/players/import')->assertStatus(401);
    }

    public function test_import_is_refused_when_the_gate_denies_create(): void
    {
        WorkbenchServiceProvider::$denied = ['create-player'];

        $this->actingAs($this->user())
            ->json('POST', '/luminix-api/players/import')
            ->assertStatus(401);
    }

    /**
     * The upload rules describe the endpoint. An unauthorised caller must get
     * the 401 first, never a 422 that leaks them.
     */
    public function test_the_gate_is_checked_before_the_upload_is_validated(): void
    {
        WorkbenchServiceProvider::$denied = ['create-player'];

        $this->actingAs($this->user())
            ->json('POST', '/luminix-api/players/import', [])
            ->assertStatus(401);
    }

    public function test_import_is_not_found_for_a_model_that_is_only_exportable(): void
    {
        $this->actingAs($this->user())
            ->json('POST', '/luminix-api/notes/import')
            ->assertStatus(404);
    }

    // Validação do upload

    public function test_a_missing_file_is_rejected(): void
    {
        $this->actingAs($this->user())
            ->json('POST', '/luminix-api/players/import', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_an_unaccepted_format_is_rejected(): void
    {
        $file = $this->makeSheet([['Name'], ['Ana']], 'csv');

        $this->actingAs($this->user())
            ->json('POST', '/luminix-api/players/import', ['file' => $file])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->assertSame(0, Player::count());
    }

    public function test_a_file_over_the_size_ceiling_is_rejected(): void
    {
        config()->set('luminix.sheets.import.max_file_size_kb', 1);

        $rows = [['Name']];

        foreach (range(1, 2000) as $i) {
            $rows[] = ['Player '.$i];
        }

        $this->actingAs($this->user())
            ->json('POST', '/luminix-api/players/import', ['file' => $this->makeSheet($rows)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    // Caminho feliz

    public function test_rows_are_persisted_and_the_count_is_reported(): void
    {
        $file = $this->makeSheet([
            ['Name', 'Registration', 'Score'],
            ['Ana', '007', '10'],
            ['Beto', '012', '20'],
        ]);

        $this->actingAs($this->user())
            ->json('POST', '/luminix-api/players/import', ['file' => $file])
            ->assertStatus(201)
            ->assertJson(['count' => 2]);

        $this->assertSame(2, Player::count());
        $this->assertSame('007', Player::where('name', 'Ana')->value('registration'));
    }

    public function test_blank_rows_are_skipped(): void
    {
        $file = $this->makeSheet([
            ['Name', 'Registration'],
            ['Ana', '007'],
            ['', ''],
            ['Beto', '012'],
        ]);

        $this->actingAs($this->user())
            ->json('POST', '/luminix-api/players/import', ['file' => $file])
            ->assertStatus(201)
            ->assertJson(['count' => 2]);
    }

    /**
     * Real files have ragged rows. Pairing them with the header must pad short
     * rows and drop the overflow instead of failing.
     */
    public function test_ragged_rows_do_not_break_the_import(): void
    {
        $file = $this->makeSheet([
            ['Name', 'Registration', 'Score'],
            ['Ana'],
            ['Beto', '012', '20', 'lixo', 'mais lixo'],
        ]);

        $this->actingAs($this->user())
            ->json('POST', '/luminix-api/players/import', ['file' => $file])
            ->assertStatus(201)
            ->assertJson(['count' => 2]);

        $this->assertNull(Player::where('name', 'Ana')->value('registration'));
    }

    // Colunas proibidas

    public function test_columns_outside_the_allowlist_are_ignored(): void
    {
        $file = $this->makeSheet([
            ['Id', 'Name', 'Secret Note', 'Banned'],
            ['999', 'Ana', 'injetado', '1'],
        ]);

        $this->actingAs($this->user())
            ->json('POST', '/luminix-api/players/import', ['file' => $file])
            ->assertStatus(201);

        $player = Player::first();

        $this->assertNotSame(999, $player->id);
        $this->assertNull($player->secret_note);
        $this->assertFalse((bool) $player->banned);
    }

    // Handler declarado

    public function test_the_heading_row_may_be_below_the_top_of_the_sheet(): void
    {
        $file = $this->makeSheet([
            ['Relatório de faturas — março'],
            ['Número', 'Cliente', 'Total'],
            ['NF-1', 'Acme', '1.234,50'],
        ]);

        $this->actingAs($this->user())
            ->json('POST', '/luminix-api/invoices/import', ['file' => $file])
            ->assertStatus(201)
            ->assertJson(['count' => 1]);

        $this->assertEquals(1234.50, (float) Invoice::first()->total);
    }

    public function test_validation_errors_are_keyed_by_the_real_sheet_row(): void
    {
        $file = $this->makeSheet([
            ['Relatório de faturas'],
            ['Número', 'Cliente', 'Total'],
            ['NF-1', 'Acme', '10,00'],
            ['NF-2', 'Globex', 'não é número'],
        ]);

        $this->actingAs($this->user())
            ->json('POST', '/luminix-api/invoices/import', ['file' => $file])
            ->assertStatus(422)
            // Row 4 of the sheet, as the person looking at the file counts it.
            ->assertJsonPath('errors.4.total.0', 'O total precisa ser um número.')
            ->assertJsonMissingPath('errors.3');

        $this->assertSame(0, Invoice::count());
    }

    // Transação

    public function test_a_failure_rolls_the_whole_import_back(): void
    {
        $file = $this->makeSheet([
            ['Relatório'],
            ['Número', 'Cliente', 'Total'],
            ['NF-1', 'Acme', '10,00'],
            ['NF-2', 'Globex', '20,00'],
            ['NF-1', 'Duplicada', '30,00'],
        ]);

        $response = $this->actingAs($this->user())
            ->json('POST', '/luminix-api/invoices/import', ['file' => $file]);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(0, Invoice::count());
    }

    public function test_without_a_transaction_the_rows_before_the_failure_survive(): void
    {
        InvoiceImport::$transactional = false;

        $file = $this->makeSheet([
            ['Relatório'],
            ['Número', 'Cliente', 'Total'],
            ['NF-1', 'Acme', '10,00'],
            ['NF-2', 'Globex', '20,00'],
            ['NF-1', 'Duplicada', '30,00'],
        ]);

        $response = $this->actingAs($this->user())
            ->json('POST', '/luminix-api/invoices/import', ['file' => $file]);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(2, Invoice::count());
    }

    // Ida e volta

    public function test_an_exported_file_can_be_imported_back(): void
    {
        Invoice::create(['number' => 'NF-1', 'customer' => 'Acme', 'total' => 1234.5]);

        $exported = $this->actingAs($this->user())
            ->get('/luminix-api/invoices/export')
            ->assertStatus(200)
            ->streamedContent();

        Invoice::query()->delete();

        // The export has no title line, so the header is where this handler
        // expects it only if we prepend one.
        $upload = $this->makeSheet(array_merge(
            [['Relatório']],
            $this->readSheet($exported)
        ));

        $this->actingAs($this->user())
            ->json('POST', '/luminix-api/invoices/import', ['file' => $upload])
            ->assertStatus(201);

        $invoice = Invoice::first();

        $this->assertSame('NF-1', $invoice->number);
        $this->assertSame('Acme', $invoice->customer);
        $this->assertEquals(1234.50, (float) $invoice->total);
    }
}
