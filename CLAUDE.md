# CLAUDE.md — AI working guide for `padosoft/laravel-rebel-bridge-spatie-otp`

> Working on this package with an AI agent (Claude Code, Cursor, Copilot, Codex)? Read this first.
> It's the "batteries" that make vibe-coding here land on the first try.

## What this package is

The bridge between `spatie/laravel-one-time-passwords` and Laravel Rebel: it exposes spatie's
email/SMS OTP delivery as an AAL2, non-phishing-resistant step-up driver (`spatie_otp`) registered
in the Rebel `DriverRegistry`.

Part of the **Laravel Rebel** suite — an enterprise authentication control plane over Laravel.
The shared language (value objects, contracts, the audit trail) lives in `padosoft/laravel-rebel-core`;
this package builds on it and on `padosoft/laravel-rebel-step-up`.

## Key design decisions

- **Seam pattern:** `Contracts\OneTimePasswordBroker` wraps all Spatie calls. The real implementation
  (`Support\SpatieOneTimePasswordBroker`) uses the `HasOneTimePasswords` trait methods directly.
  A `Testing\FakeOneTimePasswordBroker` runs tests fully offline (no DB, no mail, no Spatie internals).
- **Feature detection:** `class_exists(HasOneTimePasswords::class)` gates registration; the driver
  is never registered if Spatie is absent. Config `rebel-bridge-spatie-otp.drivers.spatie_otp`
  provides an explicit on/off toggle.
- **Fail-closed:** any `\Throwable` from the broker is caught: `start()` returns null, `verify()`
  returns false. A broken Spatie setup never grants access.
- **Spatie API used:** `$user->sendOneTimePassword()` (creates DB record + dispatches notification)
  and `$user->consumeOneTimePassword($code)` returning `ConsumeOneTimePasswordResult` enum.

## Non-negotiable conventions

- `declare(strict_types=1);` in every PHP file; `final` classes; constructor property promotion.
- **PHPStan level max** must stay green. Do NOT add `@phpstan-ignore`, baseline entries, or
  `assert()`/inline `@var` to silence errors — fix the root cause. Common recipes:
  - Narrow `mixed` before casting: `is_scalar($x) ? (string) $x : null`.
  - `json_decode($s, true)` is `array<array-key, mixed>`.
  - Use `cursor()` for large scans, `withoutGlobalScopes()` for cross-tenant admin reads.
  - Nested Eloquent `where(fn ($q) => …)` closures receive `Illuminate\Database\Eloquent\Builder`.
- **Tests:** Pest, Testbench, offline via `FakeOneTimePasswordBroker`. Cover happy path, fail-closed,
  Throwable, assurance declaration, code-not-in-audit, driver registration/de-registration.
- **Style:** Pint (`composer pint`). **Docs/comments in English.**
- Package wiring uses `spatie/laravel-package-tools` (`configurePackage`).

## Security & telemetry rules (suite-wide)

- Never store PII in cleartext. Never log OTPs/secrets.
- Audit events: `stepup.spatie_otp.started`, `stepup.spatie_otp.verified`, `stepup.spatie_otp.failed`.
  All include `subjectType`, `subjectId`, `channel`, `provider`, `purpose`, `aal`, `amr`.
- Record through the core `AuditLogger` — persists to `rebel_auth_events` (never session).

## How to extend it

- **Swap the broker:** bind a custom `OneTimePasswordBroker` implementation before the service
  provider boots. The provider respects an already-bound contract and will NOT overwrite it.
- **Disable the driver at runtime:** set `rebel-bridge-spatie-otp.drivers.spatie_otp = false`.
- **Change audit channel:** set `rebel-bridge-spatie-otp.audit_channel = 'email'` (or `'sms'`).

## Definition of Done (per change)

1. Red→green with Pest; `composer phpstan` (max) + `composer pint -- --test` clean.
2. One feature branch, one PR to `main`. CI matrix **PHP 8.3/8.4/8.5 × Laravel 12/13** must be green.
3. Update `README.md` + `CHANGELOG.md`. Squash-merge.
4. **Release:** `git tag vX.Y.Z && git push origin vX.Y.Z` + `gh release create`. Stay in `0.1.x`.

## Skills

This repo ships invocable skills under `.claude/skills/` — at least `rebel-package-dev` (the dev
loop + PHPStan-max recipes). Invoke it before non-trivial work.

## Session startup

At the start of each session:
1. Read this file (CLAUDE.md).
2. Run `composer test` to see current test state.
3. Check `CHANGELOG.md` for where the last release left off.
