# Output helpers: `e()` and `dt()`

`config/site_setting_and_config.php` defines two global output helpers. Use them in every page that renders DB rows.

## `e(?string $value): string`
HTML-escapes any string. Accepts `NULL` and renders an empty string — it never throws, so legacy rows with NULL columns render instead of crashing the page with a TypeError under `declare(strict_types=1)`.

## `dt(?string $value, string $format = 'M d, Y'): string`
Null-safe date display. Returns `—` for NULL, empty, `0000-00-00`, and unparseable values; otherwise the formatted date.

```php
<?= dt($row['created_at']) ?>                // "Nov 11, 2026" or "—"
<?= dt($row['sent_at'], 'M d, Y H:i') ?>     // custom format
```

## Rules

1. **Never write `date('FMT', strtotime($row['col']))` in a template.** If the column is NULL or a zero-date, `strtotime(null)` / `date(fmt, false)` throws a TypeError under `strict_types` (this crashed the production admin Users monitor — ref `E-20261111181759-6a0462`).
2. **`dt()` is for display only.** For logic — scheduling, comparisons, month math, relative strings (`'+7 days'`) — keep raw `strtotime()`/`date()` or use `DateTime`.
3. **User-authored free text never goes through `dt()`** — dates/timestamps only. Free text goes through `e()`.
4. Invoices and business documents should not show `—`: fall back to now, e.g. `dt($t['created_at'] ?? date('Y-m-d H:i:s'))`.

## Test suite

PHPUnit suite lives in `tests/` (`phpunit.xml`, `composer test` scripts). Unit tests must never depend on a MySQL connection (`config/db.php` opens lazily); integration tests that need a DB live in `tests/Integration/`.
