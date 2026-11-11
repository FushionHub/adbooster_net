# Adsbooster Test Suite

PHPUnit 11 test suite for Adsbooster.net (PHP 8.4, vanilla PHP app).

## Layout

```
phpunit.xml                     # suite config + DB env for integration tests
tests/bootstrap.php             # autoload only (DB connects lazily)
tests/make-test-db.php          # one-time: clones dev schema into adsbooster_test
tests/Unit/                     # pure logic — no MySQL, no network
tests/Integration/              # real DB against the adsbooster_test clone
tests/php_pcov.dll              # (local, gitignored) PCOV coverage driver
```

## Running

```bash
composer test              # full suite (loads pdo_mysql for integration tests)
composer test:unit         # unit suite only (no MySQL needed)
composer test:coverage     # full suite + line coverage (needs PCOV/Xdebug)
```

If the CLI PHP does not load `pdo_mysql` by default (winget PHP does not),
the `composer test` script already injects it. Manually:

```bash
php -d extension=php_pdo_mysql.dll vendor/bin/phpunit
```

## Coverage

```bash
# PCOV driver (already downloaded to tests/php_pcov.dll, gitignored):
php -d extension=php_pdo_mysql.dll -d extension=tests/php_pcov.dll \
    -d pcov.enabled=1 vendor/bin/phpunit --coverage-text
```

## Integration test database

Integration tests never touch the live `adsbooster` database. They run
against `adsbooster_test`, an isolated schema clone, selected via env vars
in `phpunit.xml` (`DB_NAME=adsbooster_test`). The `Database` class honours
`DB_HOST` / `DB_PORT` / `DB_NAME` / `DB_USER` / `DB_PASS` env vars.

Recreate the clone after schema changes:

```bash
php -d extension=php_pdo_mysql.dll tests/make-test-db.php
```

Every test creates its own sandboxed rows and deletes them in `tearDown()`,
so the test DB stays clean and tests are independent.

## Conventions

- Unit tests target pure logic: TOTP math, gateway registry, validation contract.
- Integration tests target DB-backed financial logic: escrow payout math,
  wallet crediting, 2FA persistence/recovery.
- `initiatePayment()`'s happy path performs a **live gateway HTTP call** and
  is intentionally NOT covered; mock/refactor before adding it.
- Security-critical and financial code targets 100% coverage.

## Known findings raised by this suite

1. **FIXED** — `EscrowBountyEngine::approveAndReleaseEscrow()` had no guard
   against double release (creator credited twice for one bounty). An
   idempotency guard was added and is pinned by
   `testApproveCannotReleaseEscrowTwice`.
2. **OPEN** — `PaymentProcessor::initiatePayment()` accepts sub-1-cent
   amounts (e.g. `0.0001`). If business rules require a minimum, add a
   floor check.
3. **OPEN** — invoice generation logs `ucfirst(null)` errors during
   `completeTransaction()` for some transaction types (metadata shape).
