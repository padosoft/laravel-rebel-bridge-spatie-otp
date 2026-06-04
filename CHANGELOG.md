# Changelog

All notable changes to `padosoft/laravel-rebel-bridge-spatie-otp` will be documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [0.1.0] - 2026-06-04

### Added

- `SpatieOtpStepUpDriver` implementing `StepUpDriver` (key `spatie_otp`, AAL2, non-phishing-resistant, AMR `['otp']`).
- `Contracts\OneTimePasswordBroker` — thin seam over `spatie/laravel-one-time-passwords` for testability.
- `Support\SpatieOneTimePasswordBroker` — real spatie-backed implementation calling `$user->sendOneTimePassword()` and `$user->consumeOneTimePassword($code)`.
- `Testing\FakeOneTimePasswordBroker` — fully in-memory offline test double (no DB, no mail, single-use semantics).
- `RebelSpatieOtpBridgeServiceProvider` — registers driver into `DriverRegistry` only when config-enabled AND spatie's trait is installed (`trait_exists` gate).
- Config file `rebel-bridge-spatie-otp.php` with `drivers.spatie_otp` toggle and `audit_channel` setting.
- Audit events: `stepup.spatie_otp.started`, `stepup.spatie_otp.verified`, `stepup.spatie_otp.failed` — all persisted to `rebel_auth_events` via core `AuditLogger`.
- Fail-closed on any `\Throwable` from the broker: `start()` returns null, `verify()` returns false.
- 18 Pest tests covering assurance, isAvailableFor, start, verify (accept/reject/replay/throwable), audit code-not-logged, driver registration/de-registration, and FakeOneTimePasswordBroker contract.
- CI matrix: PHP 8.3/8.4/8.5 × Laravel 12/13, PHPStan level max, Pint style check.
- `CLAUDE.md`, `AGENTS.md`, `.claude/skills/rebel-package-dev/SKILL.md` — batteries for AI-assisted development.
- English README with glossary, flow diagram, 5 usage examples, config table, and competitor card-battle table.
