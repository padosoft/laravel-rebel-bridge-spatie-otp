<?php

declare(strict_types=1);

namespace Padosoft\Rebel\Bridge\SpatieOtp\Drivers;

use Illuminate\Contracts\Config\Repository;
use Padosoft\Rebel\Bridge\SpatieOtp\Contracts\OneTimePasswordBroker;
use Padosoft\Rebel\Core\Assurance\Aal;
use Padosoft\Rebel\Core\Assurance\AssuranceLevel;
use Padosoft\Rebel\Core\Audit\AuditEvent;
use Padosoft\Rebel\Core\Contracts\AuditLogger;
use Padosoft\Rebel\StepUp\Contracts\StepUpDriver;
use Padosoft\Rebel\StepUp\StepUpContext;

/**
 * Step-up driver backed by spatie/laravel-one-time-passwords.
 *
 * Assurance: **AAL2** (existing session = possession/knowledge factor, plus a
 * delivered OTP = second factor), NOT phishing-resistant (a delivered OTP can be
 * intercepted or phished in real time — for phishing resistance use a passkey).
 *
 * Flow:
 *   1. start()  → calls broker->send(), which calls $user->sendOneTimePassword()
 *                 (Spatie creates the DB record and dispatches the notification).
 *                 Returns an opaque reference (the OTP record key) for the manager.
 *   2. verify() → calls broker->consume($code), which calls
 *                 $user->consumeOneTimePassword($code) and maps the result enum.
 *                 Returns true only on ConsumeOneTimePasswordResult::Ok.
 *
 * Feature detection: the driver is only registered when the user model uses the
 * HasOneTimePasswords trait (checked at isAvailableFor() time via the broker seam).
 *
 * Fail-closed: any \Throwable from the broker is caught and mapped to false / null
 * so a broken Spatie setup never grants access.
 *
 * Audit telemetry: started / verified / failed events are emitted via AuditLogger.
 * The code is NEVER written to the audit log.
 */
final class SpatieOtpStepUpDriver implements StepUpDriver
{
    private readonly string $auditChannel;

    public function __construct(
        private readonly OneTimePasswordBroker $broker,
        private readonly AuditLogger $audit,
        Repository $config,
    ) {
        $channel = $config->get('rebel-bridge-spatie-otp.audit_channel', 'otp');
        $this->auditChannel = is_string($channel) ? $channel : 'otp';
    }

    public function key(): string
    {
        return 'spatie_otp';
    }

    public function assurance(): AssuranceLevel
    {
        return new AssuranceLevel(
            aal: Aal::Aal2,
            phishingResistant: false,
            amr: ['otp'],
        );
    }

    public function isAvailableFor(StepUpContext $context): bool
    {
        try {
            return $this->broker->supportsUser($context->subject);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Creates and sends the OTP via Spatie; returns the OTP record reference
     * (opaque string) so the step-up manager can persist it alongside the challenge.
     *
     * Emits stepup.spatie_otp.started on success, or swallows any Throwable and
     * returns null (the manager treats null as "no reference, challenge started").
     */
    public function start(StepUpContext $context): ?string
    {
        try {
            $reference = $this->broker->send($context->subject);

            $this->emitStarted($context);

            return $reference;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Consumes the OTP via Spatie. Returns true only on Ok; maps every other
     * result (expired, wrong, rate-limited, different origin) to false.
     *
     * Emits stepup.spatie_otp.verified on success, stepup.spatie_otp.failed on
     * any failure. The $input code is NEVER included in the emitted event.
     */
    public function verify(StepUpContext $context, string $input, ?string $reference): bool
    {
        try {
            $ok = $this->broker->consume($context->subject, $input);
        } catch (\Throwable) {
            $this->emitFailed($context);

            return false;
        }

        if ($ok) {
            $this->emitVerified($context);

            return true;
        }

        $this->emitFailed($context);

        return false;
    }

    private function emitStarted(StepUpContext $context): void
    {
        $this->audit->record(new AuditEvent(
            type: 'stepup.spatie_otp.started',
            subjectType: $context->subjectType(),
            subjectId: $context->subjectId(),
            channel: $this->auditChannel,
            provider: 'spatie_otp',
            purpose: $context->purpose,
            aal: Aal::Aal2,
            amr: ['otp'],
        ));
    }

    private function emitVerified(StepUpContext $context): void
    {
        $this->audit->record(new AuditEvent(
            type: 'stepup.spatie_otp.verified',
            subjectType: $context->subjectType(),
            subjectId: $context->subjectId(),
            channel: $this->auditChannel,
            provider: 'spatie_otp',
            purpose: $context->purpose,
            aal: Aal::Aal2,
            amr: ['otp'],
        ));
    }

    private function emitFailed(StepUpContext $context): void
    {
        $this->audit->record(new AuditEvent(
            type: 'stepup.spatie_otp.failed',
            subjectType: $context->subjectType(),
            subjectId: $context->subjectId(),
            channel: $this->auditChannel,
            provider: 'spatie_otp',
            purpose: $context->purpose,
            aal: Aal::Aal2,
            amr: ['otp'],
        ));
    }
}
