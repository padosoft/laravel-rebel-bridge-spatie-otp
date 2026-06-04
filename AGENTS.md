# AGENTS.md — operative rules for `padosoft/laravel-rebel-bridge-spatie-otp`

> This file is the **work contract** for every AI agent (and human) contributing to this repo.

## Stack & target

- **Laravel 12 + 13**, **PHP 8.3 + 8.4 + 8.5**. Constraint: `illuminate/support: ^12.0|^13.0`, `php: ^8.3`.
- Testbench `^10.0|^11.0`, **Pest 4**, **Larastan 3** (PHPStan **level max**), **Pint** (preset `laravel`).
- Namespace PSR-4: `Padosoft\Rebel\Bridge\SpatieOtp\`. Composer name: `padosoft/laravel-rebel-bridge-spatie-otp`.

## Branching & PR — one PR per macro-task

- One branch per macro-task: `feat/<macro>` from `main`.
- Sub-tasks are **local commits** on the macro branch (local loop below). No PR for sub-tasks.
- When macro is complete: push → **one PR `feat/<macro>` → main** → CI green → merge → tag/release.

## Definition of Done

### Local loop (per sub-task, no PR)

1. Implement with guardrails: **Pest** for all logic; offline via `FakeOneTimePasswordBroker`.
2. Green locally: `composer test` · `composer phpstan` (max) · `composer pint -- --test`.
3. Commit local. Update CHANGELOG if needed.

### GitHub gate (once per macro PR)

1. `git push` branch; `gh pr create` (`feat/<macro>` → main).
2. Wait for **CI all-green** (PHP 8.3/8.4/8.5 × Laravel 12/13).
3. Green → merge. Then `git tag vX.Y.Z && git push origin vX.Y.Z` + `gh release create`.

## Guardrails = mandatory, not optional

Every sub-task must have: a precise objective, implementation details, and unit tests (Pest always).
Nothing is "done" without green tests + PHPStan + Pint.

## Security (design-lock)

- Never log OTPs, codes, or secrets. Audit metadata sanitized by core `Redactor`.
- Fail-closed on any `\Throwable` from the broker.
- `consumeOneTimePassword` result mapped: only `ConsumeOneTimePasswordResult::Ok` → true.
- Broker seam ensures testability without live DB/mail.

## File state (canonical)

- `CHANGELOG.md` — what changed per release. Update before every `git tag`.
- `CLAUDE.md` — AI session guide (design notes, conventions). Update when you learn something.
