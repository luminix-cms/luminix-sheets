# Luminix Sheets

![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012%20%7C%2013-FF2D20?logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)
![Luminix](https://img.shields.io/badge/Luminix%20Backend-1.x-1E6B4E)
![Licença](https://img.shields.io/badge/licen%C3%A7a-MIT-blue)

Importação e exportação de planilhas para modelos do Luminix. Marque um model com
`#[Exportable]` ou `#[Importable]` e o pacote injeta as rotas `GET .../export` e
`POST .../import` no conjunto que o `luminix/backend` já gera, com as mesmas
permissões, filtros e ordenação da listagem.

A leitura e a escrita são feitas linha a linha com [OpenSpout](https://github.com/openspout/openspout):
o consumo de memória é limitado pelo tamanho do lote, não pelo número de linhas.

## Requisitos

- [`/arandu`](https://github.com/AranduTech/arandu-skill) — obrigatória para trabalhar neste repositório com Claude Code:

  ```bash
  gh api -H "Accept: application/vnd.github.raw" repos/AranduTech/arandu-skill/contents/install.sh | bash
  ```

- PHP 8.2 ou superior
- Laravel 11, 12 ou 13
- [`luminix/backend`](https://github.com/luminix-cms/backend) 1.x
- [`luminix/frontend`](https://github.com/luminix-cms/frontend) 1.x — opcional, expõe as
  flags `importable` / `exportable` no manifesto consumido pelo frontend

## Instalação (ambiente de desenvolvimento)

```bash
git clone https://github.com/luminix-cms/luminix-sheets.git
cd luminix-sheets
composer install
composer test
```

O pacote é uma biblioteca: não sobe serviço nem tem URL ou credencial semeada. A
suíte roda sobre [`orchestra/testbench`](https://github.com/orchestral/testbench)
com SQLite em memória, e a aplicação de teste vive em [`workbench/`](workbench/).

Comandos disponíveis:

| Comando | O que faz |
| --- | --- |
| `composer test` | Roda a suíte completa |
| `composer test:coverage` | Roda a suíte com relatório de cobertura (exige Xdebug ou PCOV) |
| `composer lint` | Verifica o estilo com Pint, sem alterar arquivos |
| `composer format` | Aplica o Pint |

## Instalação no projeto

```bash
composer require luminix/sheets
```

Para publicar a configuração:

```bash
php artisan vendor:publish --tag=luminix-sheets-config
```

## Uso

### Habilitar um model

```php
use Luminix\Sheets\Exportable;
use Luminix\Sheets\Importable;

#[Exportable]
#[Importable]
class Player extends Model
{
    use LuminixModel;

    protected $fillable = ['name', 'registration', 'score'];
}
```

Isso registra, dentro do conjunto de rotas do `luminix/backend`:

| Rota | Nome | Permissão |
| --- | --- | --- |
| `GET {prefixo}/players/export` | `luminix.player.export` | `read-player` |
| `POST {prefixo}/players/import` | `luminix.player.import` | `create-player` |

As rotas entram **antes** de `show` e `update`, que também casam com
`players/{id}` e engoliriam `players/export`.

### Exportar

`GET /luminix-api/players/export` devolve um download em `xlsx`.

A consulta é a mesma da listagem: o escopo `allowed` da permissão, `q`, `where`,
`tab` e `order_by` valem igual. Exportar o que está na tela é passar os mesmos
parâmetros:

```
GET /luminix-api/players/export?q=ana&order_by=score:desc
```

### Importar

`POST /luminix-api/players/import` com `multipart/form-data` e um campo `file`.

- `201` com `{"message": "...", "count": 12}` quando tudo entra;
- `422` com os erros **indexados pelo número da linha na planilha**, como a
  pessoa que abre o arquivo os conta:

  ```json
  {
    "message": "The import file contains 1 row(s) with validation errors.",
    "errors": { "4": { "total": ["O total precisa ser um número."] } }
  }
  ```

Por padrão a importação inteira roda em uma transação: uma linha que falha no
banco desfaz todas as anteriores.

## Configuração

`config/luminix/sheets.php`:

```php
'routes' => [
    'enabled' => true,          // desligue para registrar as rotas você mesmo
],

'permissions' => [
    'export' => 'read',         // null desliga o gate E o escopo de linha
    'import' => 'create',
],

'import' => [
    'max_file_size_kb' => 10240,
    'formats' => ['xlsx'],
],

'export' => [
    'default_format' => 'xlsx', // xlsx | csv | ods
    'chunk_size' => 1000,       // linhas por ida ao banco
    'max_rows' => null,         // teto de linhas por requisição
],
```

O `luminix/backend` não tem entrada para `export`/`import` no próprio mapa de
permissões, então os verbos são resolvidos aqui. Um verbo `null` desliga tanto o
`Gate` quanto o escopo `allowed` daquela ação.

## Handlers

Sem handler declarado, o pacote usa `DefaultExportable` / `DefaultImportable`:
as colunas são o `$fillable` menos as ocultas, os rótulos são os nomes em
`Title Case`, datas saem como `d/m/Y H:i`, booleanos como `Sim` / `Não` e enums
pelo `value`.

Para assumir o controle, gere um handler:

```bash
php artisan make:export PlayerExport
php artisan make:import PlayerImport
```

e aponte o model para ele:

```php
#[Exportable(handler: PlayerExport::class)]
#[Importable(handler: PlayerImport::class)]
class Player extends Model { /* ... */ }
```

### Exportação

| Método | Para quê |
| --- | --- |
| `headers()` | Os rótulos das colunas, em ordem. **É isso que define o formato do arquivo** — uma exportação sem resultados ainda sai com cabeçalho. |
| `widths()` | Largura por rótulo |
| `map(Model $model)` | Uma linha, indexada pelos rótulos de `headers()` |
| `columns()` | Restringe os atributos usados pelos padrões acima |
| `query(Builder $query)` | Restrições extras sobre a consulta já filtrada |
| `fileName()` / `sheetName()` / `format()` | Nome do arquivo, da aba e formato |
| `beforeExport(LazyCollection $rows)` / `afterExport()` | Ganchos |

`beforeExport()` recebe o resultado preguiçoso. Iterar ali carrega tudo em
memória e anula o streaming — leia dele só quando precisar mesmo de um segundo
passo.

### Importação

| Método | Para quê |
| --- | --- |
| `map(array $row, int $rowIndex)` | Linha → atributos; `null` pula a linha |
| `rules()` / `messages()` | Validação por linha |
| `allowedColumns()` | Colunas aceitas do arquivo |
| `headingRow()` | Em que linha está o cabeçalho |
| `useTransaction()` | Envolver tudo em uma transação |
| `beforeImport(UploadedFile $file)` / `afterImport(Collection $imported)` | Ganchos |

`allowedColumns()` é aplicado **pelo motor**, não só pelo handler padrão: um
handler que sobrescreve `map()` e devolve uma coluna fora da lista não consegue
gravá-la.

## Colunas ocultas

O model decide o que nunca entra na planilha. As propriedades podem ser
`protected`; os métodos precisam ser públicos.

```php
class Player extends Model
{
    // Ocultas na importação e na exportação. Padrão: o $hidden do model.
    protected array $sheetsHidden = ['secret_note'];

    // Só na exportação
    protected array $sheetsHiddenForExport = ['internal'];

    // Só na importação
    protected array $sheetsHiddenForImport = ['computed'];
}
```

A chave primária nunca é importável.

## Streaming e memória

O pacote não monta a planilha em memória. Na exportação:

1. o resultado é lido do banco em lotes de `chunk_size`;
2. cada linha é escrita no arquivo assim que é mapeada;
3. o arquivo é fechado **antes** de a resposta começar.

O passo 3 é deliberado: uma falha no meio da escrita vira `500`, e não um `200`
carregando um anexo truncado. O arquivo temporário é removido tanto no erro
quanto ao fim do download.

Todo valor é gravado como texto, de modo que uma matrícula `007` continua `007`
em vez de virar `7`.

## Licença

MIT. Veja [LICENSE](LICENSE).
