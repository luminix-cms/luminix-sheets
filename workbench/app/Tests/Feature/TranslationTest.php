<?php

namespace Workbench\App\Tests\Feature;

use Workbench\App\Models\Note;
use Workbench\App\Models\Player;
use Workbench\App\Providers\WorkbenchServiceProvider;
use Workbench\App\Tests\TestCase;

class TranslationTest extends TestCase
{
    // O dicionário

    /**
     * The English line is the key, so `en` needs no file: an untranslated
     * locale falls back to the key and still reads as a sentence.
     */
    public function test_a_locale_without_a_file_falls_back_to_the_english_key(): void
    {
        $this->assertSame(
            'Please upload a spreadsheet file.',
            __('Please upload a spreadsheet file.', [], 'en')
        );

        $this->assertSame(
            'Envie um arquivo de planilha.',
            __('Please upload a spreadsheet file.', [], 'pt-BR')
        );
    }

    /**
     * luminix/admin ships `trans('*')` — the whole JSON dictionary — in the boot
     * payload, and @luminix/sheets-for-mui-cms reads its labels from there. The
     * placeholders are Laravel-style because the CMS configures i18next with
     * `prefix: ':'` and an empty suffix.
     */
    public function test_the_cms_labels_travel_in_the_json_dictionary(): void
    {
        $dictionary = trans('*', [], 'pt-BR');

        $this->assertIsArray($dictionary);
        $this->assertSame('Exportar', $dictionary['Export'] ?? null);
        $this->assertSame('Importar :model', $dictionary['Import :model'] ?? null);

        $this->assertSame(
            'Importar Jogadores',
            __('Import :model', ['model' => 'Jogadores'], 'pt-BR')
        );
    }

    public function test_the_dictionary_uses_laravel_placeholders(): void
    {
        $dictionary = json_decode(
            file_get_contents(__DIR__.'/../../../../lang/pt-BR.json'),
            true
        );

        foreach ($dictionary as $key => $line) {
            $this->assertStringNotContainsString('{{', $key, "Key [{$key}] uses i18next syntax.");
            $this->assertStringNotContainsString('{{', $line, "Line [{$key}] uses i18next syntax.");
        }
    }

    /**
     * The macros' 404 is defensive — the routes are only registered for a model
     * that declares the attribute — so the message is checked at the source.
     */
    public function test_the_unsupported_model_message_names_the_model(): void
    {
        $this->assertSame(
            'O model [Workbench\App\Models\Note] não suporta importação.',
            __('Model [:model] does not support import.', ['model' => Note::class], 'pt-BR')
        );
    }

    // Mensagens das rotas

    public function test_the_import_message_is_translated_and_pluralised(): void
    {
        $this->app->setLocale('pt-BR');

        $this->actingAs($this->user())
            ->json('POST', '/luminix-api/players/import', [
                'file' => $this->makeSheet([['Name'], ['Ana']]),
            ])
            ->assertStatus(201)
            ->assertJson(['message' => '1 registro importado com sucesso.']);

        Player::query()->delete();

        $this->app->setLocale('en');

        $this->actingAs($this->user())
            ->json('POST', '/luminix-api/players/import', [
                'file' => $this->makeSheet([['Name'], ['Ana'], ['Beto']]),
            ])
            ->assertStatus(201)
            ->assertJson(['message' => '2 records imported successfully.']);
    }

    public function test_the_upload_validation_messages_are_translated(): void
    {
        $this->app->setLocale('pt-BR');

        $this->actingAs($this->user())
            ->json('POST', '/luminix-api/players/import', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.file.0', 'Envie um arquivo de planilha.');

        $this->actingAs($this->user())
            ->json('POST', '/luminix-api/players/import', [
                'file' => $this->makeSheet([['Name'], ['Ana']], 'csv'),
            ])
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.file.0',
                'Somente arquivos xlsx são aceitos. Salve o arquivo em um desses formatos e tente novamente.'
            );
    }

    public function test_the_row_error_message_is_translated(): void
    {
        $this->app->setLocale('pt-BR');

        $file = $this->makeSheet([
            ['Relatório de faturas'],
            ['Número', 'Cliente', 'Total'],
            ['NF-1', 'Acme', 'não é número'],
            ['NF-2', 'Globex', 'também não'],
        ]);

        $this->actingAs($this->user())
            ->json('POST', '/luminix-api/invoices/import', ['file' => $file])
            ->assertStatus(422)
            ->assertJson([
                'message' => 'O arquivo de importação contém 2 linhas com erros de validação.',
            ]);
    }

    public function test_the_unauthorized_message_is_translated(): void
    {
        $this->app->setLocale('pt-BR');

        WorkbenchServiceProvider::$denied = ['read-player'];

        $this->actingAs($this->user())
            ->json('GET', '/luminix-api/players/export')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Você não tem autorização para executar esta ação.');
    }
}
