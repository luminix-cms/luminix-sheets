# The CI matrix — why every leg is load-bearing

`.github/workflows/checks.yml` runs three legs. None of them is thoroughness theatre: the
package declares two dependency constraints that span majors, and a single leg would let
either rot silently.

| Leg | openspout | Laravel | testbench |
| --- | --- | --- | --- |
| PHP 8.2 | v4 | 11 | `^9.0` |
| PHP 8.3 | v5 | 12 | `^10.0` |
| PHP 8.4 | v5 | 13 | `^11.0`, plus `pcov` and the coverage figure |

## openspout: the major follows PHP, on its own

`openspout/openspout ^4.28|^5.3` resolves to v4 on PHP 8.2 and to v5 on 8.3+, because v5
requires PHP `^8.3`. Nothing in the workflow arranges that — it falls out of the PHP version.

The two majors break in three places:

- `setFontBold` → `withFontBold`, and
- `setFreezeRow` → `withFreezeRow`.

  `SpreadsheetWriter` shims both with `method_exists`, so the same source runs on either.

- **`Row::__construct` is not shimmable.** v4's `(array $cells, ?Style $style)` became v5's
  `final readonly (array $cells, float $height)` — the second argument changed meaning, and v5
  dropped row-level styling altogether. `SpreadsheetWriter::row()` therefore puts the style on
  each `StringCell`, whose signature is the same in both, and always calls `new Row($cells)`.

None of this is defensive padding; it is what the declared constraint costs. The v5 leg is what
exposed the `Row` break in the first place.

## Laravel: the major has to be pinned, or only one ever runs

`composer update` always takes the newest version a constraint allows, so
`laravel/framework ^11.0|^12.0|^13.0` resolved to 12 on **every** leg: the other two majors were
declared as supported and never exercised once. The audit of 2026-09-03 found this by reading
`Locking laravel/framework (v12.69.1)` in all three legs of a green run.

Each leg now pins its major explicitly:

```bash
composer update --prefer-dist --no-progress --no-interaction \
  --with="laravel/framework:^11.0" \
  --with="orchestra/testbench:^9.0"
```

Two things make the pairing what it is:

- **testbench tracks Laravel one major behind its own number** — testbench `^9` requires Laravel
  `^11.50`, `^10` requires `^12.55`, `^11` requires `^13.23`. Pinning the framework without
  pinning testbench resolves nothing, since testbench's own constraint decides the outcome.
- **Laravel 13 requires PHP `^8.3`**, which is what fixes the direction of the pairing: the
  oldest Laravel goes on the oldest PHP, so 8.2/11, 8.3/12, 8.4/13. It also means the openspout
  major and the Laravel major move together — a leg failure names both, and the first thing to
  do is re-run that leg's `--with` pair locally to see which one it was.

A `composer show laravel/framework | grep` step follows the update and fails the leg if the pin
did not take. Without it a `--with` that silently resolved elsewhere would look like a pass.

## Rehearsing a leg locally

Only the leg matching this machine's PHP can be run here — PHP 8.3 has no `pdo_sqlite`
installed, so 8.2/v4/Laravel 11 and 8.4/v5/Laravel 13 are what a local run can cover, one at a
time. Use the same `--with` pair as the leg, then `composer update` with no arguments to get
back to the default resolution.
