<?php

declare(strict_types=1);

namespace superbig\vipps\responses;

use craft\commerce\base\RequestResponseInterface;

/**
 * Maps Vipps ePayment API responses to Commerce's RequestResponseInterface.
 *
 * State mapping:
 * - CREATED + redirectUrl → isRedirect() = true
 * - AUTHORIZED / CAPTURED → isSuccessful() = true
 * - ABORTED / EXPIRED / TERMINATED / CANCELLED → failed
 * - API error (no 'state' key) → failed with error message
 */
class VippsResponse implements RequestResponseInterface
{
    /** Vipps states that mean the payment succeeded. */
    private const SUCCESSFUL_STATES = ['AUTHORIZED', 'CAPTURED', 'REFUNDED'];

    /** Vipps states that mean the payment failed or was abandoned. */
    private const FAILED_STATES = ['ABORTED', 'EXPIRED', 'TERMINATED', 'CANCELLED'];

    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Create an error response from a caught exception.
     */
    public static function fromError(string $message, int $code = 0): self
    {
        return new self([
            'success' => false,
            'message' => $message,
            'code' => (string) $code,
        ]);
    }

    public function isSuccessful(): bool
    {
        $state = $this->data['state'] ?? null;

        return in_array($state, self::SUCCESSFUL_STATES, true);
    }

    public function isProcessing(): bool
    {
        // CREATED without a redirect URL means we're waiting for user action
        $state = $this->data['state'] ?? null;

        return $state === 'CREATED' && !$this->isRedirect();
    }

    public function isRedirect(): bool
    {
        return ($this->data['state'] ?? null) === 'CREATED'
            && !empty($this->data['redirectUrl']);
    }

    public function getRedirectMethod(): string
    {
        return 'GET';
    }

    public function getRedirectUrl(): string
    {
        return $this->data['redirectUrl'] ?? '';
    }

    public function getRedirectData(): array
    {
        return [];
    }

    public function getTransactionReference(): string
    {
        return $this->data['pspReference'] ?? $this->data['reference'] ?? '';
    }

    public function getCode(): string
    {
        return $this->data['code'] ?? $this->data['state'] ?? '';
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function getMessage(): string
    {
        // Error responses from our fromError() factory
        if (isset($this->data['message'])) {
            return $this->data['message'];
        }

        // Vipps API error responses
        if (isset($this->data['title'])) {
            $msg = $this->data['title'];
            if (isset($this->data['detail'])) {
                $msg .= ': ' . $this->data['detail'];
            }

            return $msg;
        }

        // State-based messages
        $state = $this->data['state'] ?? null;
        if (in_array($state, self::FAILED_STATES, true)) {
            return 'Payment ' . strtolower((string) $state);
        }

        if (in_array($state, self::SUCCESSFUL_STATES, true)) {
            return 'Payment ' . strtolower((string) $state);
        }

        return '';
    }

    public function redirect(): void
    {
        // Commerce handles the redirect via getRedirectUrl() — this is a no-op.
    }
}
