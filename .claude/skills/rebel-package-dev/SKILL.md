---
name: rebel-package-dev
description: Use when adding or changing code in any padosoft/laravel-rebel-* package — encodes the suite's TDD loop, PHPStan-max recipes, security/telemetry rules, and the branch→PR→CI→tag/release Definition of Done.
---

# Developing a Laravel Rebel package

You are extending a package in the **Laravel Rebel** enterprise-auth suite. Follow this exactly.

## The loop (per sub-task)

1. **Write the test first** (Pest + Testbench): happy path, **auth/fail-closed**, Throwable path,
   empty state. Run `composer test`.
2. Implement with the conventions: `declare(strict_types=1)`, `final` classes, constructor
   promotion, English docblocks.
3. Make the gate green: `composer test` · `composer phpstan` (**level max**) · `composer pint -- --test`.
4. Commit on the feature branch. Repeat.

## PHPStan level max — fix the cause, never suppress

Forbidden: `@phpstan-ignore*`, baseline entries, `assert()`/inline `@var` to override inference,
type-casts/`mixed` widening just to silence. Instead:

- Narrow before casting: `is_scalar($x) ? (string) $x : null`.
- `json_decode($s, true)` returns `array<array-key, mixed>` — type/annotate accordingly.
- `class_uses($object::class)` returns `array<string, string>`.
- `Aal::tryFrom()` (fail-closed), not `from()`. Add `@property` blocks to Eloquent models.
- Run with `--memory-limit=512M`.

## Security & telemetry (non-negotiable)

- Never log OTPs/secrets (audit metadata is sanitized by `Redactor`).
- Record events through the core `AuditLogger` (persisted to `rebel_auth_events`).
- For a channel/driver/bridge, capture all panel telemetry: channel, provider, purpose, aal, amr.
- Fail-closed on any `\Throwable` — never grant access on error.

## Spatie OTP specifics

- `$user->sendOneTimePassword()` — creates DB record + dispatches notification (returns `$this`).
- `$user->consumeOneTimePassword($code)` — returns `ConsumeOneTimePasswordResult` enum.
- Only `ConsumeOneTimePasswordResult::Ok` maps to `true`; all other cases (expired, wrong, rate-limited,
  different origin, not found) map to `false`.
- Feature-detect via `trait_exists(\Spatie\OneTimePasswords\Models\Concerns\HasOneTimePasswords::class)` — NOT `class_exists` (it's a PHP trait, not a class).
- Check if a user has the trait via `class_uses_recursive($user)` or the broker's `supportsUser()`.

## Definition of Done (per change)

- One feature branch → one PR to `main`; CI matrix **PHP 8.3/8.4/8.5 × Laravel 12/13** green.
- README + CHANGELOG updated; squash-merge.
- **Release every change:** `git tag vX.Y.Z && git push origin vX.Y.Z` + `gh release create`.
  Stay within `0.1.x` (`^0.1` excludes `0.2.0`).

## Tooling notes

- `php`/`composer` run in **PowerShell** (Herd), NOT the Bash tool on Windows.
- Tests use `FakeOneTimePasswordBroker` — bind it in TestCase before the service provider boots
  (the SP checks `$app->bound(OneTimePasswordBroker::class)` and skips its own bind if already bound).
- Spatie's migration is at `vendor/spatie/laravel-one-time-passwords/database/migrations` — load
  it in `defineDatabaseMigrations()` if the test fixture creates real OTP models.
