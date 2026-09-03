# CLAUDE.md

## Overview
**luminix/sheets** — Composer package that adds spreadsheet import/export to Luminix models.
A model opts in with the `#[Importable]` / `#[Exportable]` PHP attributes; the service provider
injects the two routes into the set `luminix/backend` generates per model and macros
`import`/`export` onto its `ResourceController`. PHP ^8.2, Laravel ^11|^12|^13,
`luminix/backend` ^1.0, `openspout/openspout` ^4.28|^5.3.
Consumed by `base-de-dados-api` (AppBase & Placar).

## Git overrides
- Remote is `github.com/luminix-cms/luminix-sheets` — outside the `AranduTech` org. The org
  standard is applied here by decision, not by ownership; confirm with the user before treating
  a Luminix-specific convention as a deviation.
- **Branch model is `master` + `feat/`/`fix/`, with no `Sandbox` — deliberate.** A package is
  consumed by Composer version constraint, not deployed, so there is no QA environment for a
  `Sandbox` branch to feed. Confirmed 2026-09-03. Everything else in `/arandu`'s git model
  applies unchanged; a consumer app pins a pre-release constraint when it needs to try a branch.

## Verify
```bash
composer test    # phpunit through orchestra/testbench — 84 tests
composer lint    # pint --test
```

## Workflow
- For broad prompts, align with the user using AskUserQuestion before making a decision.
- **Tests live in `workbench/app/Tests/`, not `tests/`** — the `orchestra/workbench` layout the
  sibling packages (`luminix/media-gallery`, `luminix/laravel-permission-integration`) use.
  `workbench/app/Models/` holds the fixture models, `workbench/app/Sheets/` the custom handlers
  the tests drive, `workbench/database/migrations/` their schema.
- **Reducers are static and leak between tests.** `TestCase::setUp()` flushes
  `RouteGenerator`, `ManifestService` and `ModelFinder` before booting; a new reducer-based
  integration needs the same treatment or the previous test's registration is still live.
- **`openspout` resolves to v4 on PHP 8.2 and v5 on 8.3+, and the two majors break in three
  places.** Fluent setters were renamed (`setFontBold` → `withFontBold`,
  `setFreezeRow` → `withFreezeRow`) — `SpreadsheetWriter` shims both with `method_exists`. The
  third is not shimmable: v4's `Row::__construct(array $cells, ?Style $style)` became v5's
  `final readonly Row::__construct(array $cells, float $height)`, which dropped row-level
  styling entirely. `SpreadsheetWriter::row()` therefore puts the style on each `StringCell`
  (same signature in both) and always calls `new Row($cells)`. None of this is defensive
  padding — it is what the declared constraint costs. **The CI matrix is the verification**
  (`.github/workflows/checks.yml`); PHP 8.3 has no `pdo_sqlite` on this machine, so the local
  run covers 8.2/v4 and 8.4/v5 only.
- **Every translatable string lives in `lang/pt-BR.json` — one file, JSON, no namespaced PHP
  group.** Deliberate divergence from `luminix/backend`'s `luminix-backend::backend.*`: the
  English line is the key, so `en` needs no file, and the same dictionary serves both sides.
  `luminix/admin` puts `trans('*')` — the whole JSON dictionary — in the boot payload, and
  `@luminix/sheets-for-mui-cms` reads its labels from there, so the CMS labels ship from here
  too. That i18next instance is initialised with `prefix: ':'` and an empty suffix, so **the
  placeholders are `:model`, never `{{model}}`** — an i18next-style one renders literally on
  screen. An application overrides a line by repeating the key in its own `lang/{locale}.json`
  (the loader merges package paths first, the app last); there is no lang publish tag, since a
  JSON under `lang/vendor` is never read back.
- Consequence in `DefaultExportable`: booleans and the date format follow the app locale
  (`__('Yes')`, `__('m/d/Y H:i')`) instead of being hardcoded pt-BR, so the two tests that
  assert `Não` / `04/03/2026` set the locale explicitly.
- The reference implementation for streamed export also lives **outside this repo**, in
  `base-de-dados-api`: `App\Services\PlayerExportService` + `PlayerController::export`.
- Spawn agents only w/ user approval

## References
Deep-dives worth keeping at `docs/claude-md-references/`:

| File | Keywords |
| --- | --- |
| `compliance.md` | compliance, pendings, audit gaps |
