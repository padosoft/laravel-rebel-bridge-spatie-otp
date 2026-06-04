<img src="resources/screenshoots/Laravel-Rebel-banner.png" alt="Laravel Rebel" width="100%" />

# laravel-rebel-bridge-spatie-otp

**AAL2 step-up authentication for Laravel apps already using `spatie/laravel-one-time-passwords`.**

Part of the [Laravel Rebel](https://github.com/padosoft/laravel-rebel-core) enterprise-auth suite.

[![CI](https://github.com/padosoft/laravel-rebel-bridge-spatie-otp/actions/workflows/ci.yml/badge.svg)](https://github.com/padosoft/laravel-rebel-bridge-spatie-otp/actions)
[![Packagist](https://img.shields.io/packagist/v/padosoft/laravel-rebel-bridge-spatie-otp.svg)](https://packagist.org/packages/padosoft/laravel-rebel-bridge-spatie-otp)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

---

## What this package does

This bridge exposes `spatie/laravel-one-time-passwords` as a **step-up driver** in the
Laravel Rebel `DriverRegistry`. Once registered, the Rebel step-up manager can challenge
users with a one-time password (delivered by Spatie's notification system — email, SMS, or any
custom channel) as a second factor to confirm a sensitive action ("step-up authentication").

**In plain English:** when a user wants to do something risky (delete their account, make a large
transfer, change their email), your app asks them to prove they're still present by typing a
fresh 6-digit code that was just sent to their inbox or phone. This package wires up the existing
Spatie OTP you've already installed to do exactly that.

---

## Glossary

| Term | Meaning |
|------|---------|
| **OTP** | One-Time Password — a short numeric code valid for a single use and a short time window. |
| **Step-up** | Asking an already-authenticated user to prove presence again before a sensitive action. |
| **AAL** | Authenticator Assurance Level (NIST 800-63). AAL1 = password only; AAL2 = password + second factor. |
| **AMR** | Authentication Methods References — a list of methods used, e.g. `['otp']`. |
| **Phishing-resistant** | A factor that cannot be captured and replayed by a phishing site (e.g. passkey/WebAuthn). OTP is NOT phishing-resistant. |
| **DriverRegistry** | Rebel's runtime registry of available step-up drivers; each driver declares its key and assurance level. |
| **AuditLogger** | Core Rebel contract that records auth events to `rebel_auth_events` for the compliance panel. |
| **HasOneTimePasswords** | Spatie's PHP trait — add this to your User model to enable OTP delivery. |

---

## How it works (step by step)

```
User triggers a protected action
        │
        ▼
Rebel StepUp Manager
  → looks up driver 'spatie_otp' in DriverRegistry
  → calls driver.isAvailableFor($user)       ← checks user has HasOneTimePasswords trait
        │
        ▼
driver.start($context)
  → calls $user->sendOneTimePassword()       ← Spatie creates DB record + dispatches notification
  → emits AuditEvent: stepup.spatie_otp.started
  → returns opaque reference (null for Spatie's stateful flow)
        │
        ▼
User receives OTP (email / SMS / custom notification)
        │
        ▼
driver.verify($context, $input, $reference)
  → calls $user->consumeOneTimePassword($input)  ← Spatie checks DB record
  → ConsumeOneTimePasswordResult::Ok  →  true   + AuditEvent: stepup.spatie_otp.verified
  → any other result                 →  false  + AuditEvent: stepup.spatie_otp.failed
```

The OTP code **never appears in the audit log** (only the event type, channel, and subject identity).

---

## Installation

### 1. Require the packages

```bash
composer require padosoft/laravel-rebel-bridge-spatie-otp
composer require spatie/laravel-one-time-passwords
```

> `spatie/laravel-one-time-passwords` is a **suggested** dependency. This bridge installs cleanly
> without it — the driver simply stays unregistered until Spatie is present.

### 2. Add the Spatie migration and config

```bash
php artisan vendor:publish --provider="Spatie\OneTimePasswords\OneTimePasswordsServiceProvider"
php artisan migrate
```

### 3. Add the trait to your User model

```php
use Spatie\OneTimePasswords\Models\Concerns\HasOneTimePasswords;

class User extends Authenticatable
{
    use HasOneTimePasswords;
    // ...
}
```

### 4. (Optional) Publish the Rebel bridge config

```bash
php artisan vendor:publish --tag="rebel-bridge-spatie-otp-config"
```

---

## Configuration

File: `config/rebel-bridge-spatie-otp.php`

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `drivers.spatie_otp` | bool | `true` | Enable or disable the `spatie_otp` step-up driver. |
| `audit_channel` | string | `'otp'` | Channel label in audit events (`rebel_auth_events.channel`). Change to `'email'` or `'sms'` to match your Spatie delivery method. |

You can also control the driver via the `REBEL_SPATIE_OTP_DRIVER_ENABLED` and
`REBEL_SPATIE_OTP_AUDIT_CHANNEL` environment variables.

---

## Usage examples

### Example 1 — Protect a sensitive route with step-up middleware

```php
// routes/web.php
Route::post('/account/delete', [AccountController::class, 'destroy'])
    ->middleware(['auth', 'rebel.stepup:delete-account']);
```

Configure the purpose policy (e.g. in `config/rebel-step-up.php`):

```php
'policies' => [
    'delete-account' => [
        'required_aal'            => 'aal2',
        'phishing_resistant'      => false,
        'preferred_drivers'       => ['spatie_otp'],
    ],
],
```

### Example 2 — Start a step-up challenge manually

```php
use Padosoft\Rebel\StepUp\RebelStepUp;
use Padosoft\Rebel\StepUp\StepUpContext;
use Padosoft\Rebel\Core\Context\SecurityContext;

$stepUp = app(RebelStepUp::class);

$context = new StepUpContext(
    subject: $request->user(),
    purpose: 'change-email',
    security: SecurityContext::fromRequest($request, app(KeyedHasher::class)),
);

// Starts the challenge: Rebel picks the best available driver (spatie_otp if enabled)
// and calls driver->start(), which sends the OTP via Spatie.
$challenge = $stepUp->start($context, driverKey: 'spatie_otp');
```

### Example 3 — Verify the user's input

```php
// The user submits the 6-digit code from their email.
$result = $stepUp->verify($challenge->id, $request->input('otp'), $context);

if ($result->success) {
    // Proceed with the sensitive action.
} else {
    return back()->withErrors(['otp' => 'Invalid or expired code. Please try again.']);
}
```

### Example 4 — Test your controller with a fake broker

In your feature tests, swap the broker for a fully in-memory fake — no mail, no DB:

```php
use Padosoft\Rebel\Bridge\SpatieOtp\Contracts\OneTimePasswordBroker;
use Padosoft\Rebel\Bridge\SpatieOtp\Testing\FakeOneTimePasswordBroker;

// In setUp() or a service provider override:
$fake = new FakeOneTimePasswordBroker(validCode: '123456');
app()->instance(OneTimePasswordBroker::class, $fake);

// Trigger the step-up (sends OTP via fake, no mail dispatched).
$this->post('/account/delete')->assertRedirect(); // redirected to step-up challenge

// Provide the correct code.
$this->post('/step-up/verify', ['otp' => '123456'])->assertOk();
expect($fake->sendCount)->toBe(1);
```

### Example 5 — Disable the driver temporarily

In `.env`:

```
REBEL_SPATIE_OTP_DRIVER_ENABLED=false
```

Or in code (e.g. in a feature flag service provider):

```php
config(['rebel-bridge-spatie-otp.drivers.spatie_otp' => false]);
```

---

## Assurance declaration

| Property | Value |
|----------|-------|
| Driver key | `spatie_otp` |
| AAL | `Aal::Aal2` |
| Phishing-resistant | No |
| AMR | `['otp']` |

---

## Audit events

Every step-up operation emits an event to `rebel_auth_events` via `AuditLogger`. The OTP code is
**never** logged.

| Event type | When |
|------------|------|
| `stepup.spatie_otp.started` | `start()` called, OTP sent successfully |
| `stepup.spatie_otp.verified` | `verify()` returned true (correct code) |
| `stepup.spatie_otp.failed` | `verify()` returned false (wrong / expired / rate-limited) |

Each event carries: `subjectType`, `subjectId`, `channel` (configurable), `provider: 'spatie_otp'`,
`purpose`, `aal: Aal2`, `amr: ['otp']`.

---

## Competitor card-battle

How does Rebel's spatie OTP bridge compare to rolling your own, or using alternative SaaS providers?

| Feature | **Laravel Rebel + Spatie OTP** | Roll-your-own OTP | Twilio Verify | Shopify MFA |
|---------|-------------------------------|-------------------|---------------|-------------|
| Offline testable (FakeOtpBroker) | ✅ | ❌ Requires mail/DB | ❌ Requires API | ❌ SaaS only |
| AAL/AMR compliance metadata | ✅ | ❌ DIY | ❌ Not exposed | ❌ Not exposed |
| Audit trail to DB (rebel_auth_events) | ✅ | ❌ DIY | ❌ | ❌ |
| Purpose-scoped step-up policies | ✅ | ❌ | ❌ | ❌ |
| Config-gated driver toggle | ✅ | ❌ | N/A | N/A |
| Zero hard dep (installs without Spatie) | ✅ | N/A | N/A | N/A |
| Fail-closed on broker Throwable | ✅ | ❌ Depends on impl | ❌ | ❌ |
| Custom delivery channel (email/SMS) | ✅ via Spatie notification | ❌ DIY | ✅ | ❌ |
| Open-source & self-hosted | ✅ | ✅ | ❌ | ❌ |
| Seam pattern (swappable broker) | ✅ | ❌ | ❌ | ❌ |

---

## 🔋 Vibe coding with batteries included

This package ships everything you need to start building immediately:

- **`CLAUDE.md`** — AI session guide (design notes, key decisions, session startup checklist).
- **`AGENTS.md`** — operative rules for every contributor (human or AI): branching, CI gates, DoD.
- **`.claude/skills/rebel-package-dev/SKILL.md`** — the dev loop (TDD, PHPStan-max recipes, security rules, Spatie-specific gotchas).
- **`Testing\FakeOneTimePasswordBroker`** — fully in-memory offline test double.
- **`config/rebel-bridge-spatie-otp.php`** — documented config file.
- **CI matrix** — PHP 8.3/8.4/8.5 × Laravel 12/13, quality job (Pint + PHPStan level max).

---

## Requirements

| Dependency | Version |
|-----------|---------|
| PHP | `^8.3` |
| Laravel | `^12` or `^13` |
| padosoft/laravel-rebel-core | `^0.1` |
| padosoft/laravel-rebel-step-up | `^0.1` |
| spatie/laravel-one-time-passwords | `^1.0` *(suggested)* |

---

## License

MIT — see [LICENSE](LICENSE).
