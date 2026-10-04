<?php

namespace App\Services;

use App\Exceptions\ClickPesaException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ClickPesaService
{
    protected string $clientId;
    protected string $apiKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->clientId = config('services.clickpesa.client_id');
        $this->apiKey = config('services.clickpesa.api_key');
        // Confirm this against your ClickPesa dashboard/docs — sandbox and
        // production usually have different hosts.
        $this->baseUrl = config('services.clickpesa.base_url', 'https://api.clickpesa.com');
    }

    /**
     * Get a cached JWT token, requesting a new one if expired.
     * TODO: confirm the exact auth endpoint path + payload shape with
     * ClickPesa's docs (it's commonly something like
     * "{baseUrl}/third-parties/generate-token" with clientId/apiKey headers
     * or body — verify before relying on this).
     */
    protected function getToken(): string
    {
        return Cache::remember('clickpesa_token', now()->addMinutes(50), function () {
            $response = Http::withHeaders([
                'client-id' => $this->clientId,
                'api-key' => $this->apiKey,
            ])->post("{$this->baseUrl}/third-parties/generate-token");

            if ($response->failed()) {
                Log::error('ClickPesa token generation failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new RuntimeException('Failed to authenticate with ClickPesa.');
            }

            $token = $response->json('token');

            if (! $token) {
                Log::error('ClickPesa token missing in response', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new RuntimeException('ClickPesa token missing in response.');
            }

            // ClickPesa's docs indicate the token already includes the
            // "Bearer " prefix, but guard against it being absent so the
            // Authorization header is correct either way.
            if (! str_starts_with($token, 'Bearer ')) {
                $token = "Bearer {$token}";
            }

            return $token;
        });
    }

    /**
     * Preview available USSD push methods/fees for a number before charging.
     */
    public function previewUssdPush(array $data): array
    {
        $orderReference = substr(
            preg_replace('/[^A-Za-z0-9]/', '', $data['order_reference']),
            0,
            20
        );

        $response = $this->authorizedRequest()
            ->post("{$this->baseUrl}/third-parties/payments/preview-ussd-push-request", [
                'amount' => (string) $data['amount'],
                'currency' => $data['currency'] ?? 'TZS',
                'orderReference' => $orderReference,
                'phoneNumber' => self::normalizePhoneNumber($data['phone_number']),
                'fetchSenderDetails' => true,
            ]);

        $this->throwIfFailed($response, 'preview USSD push');

        return $response->json();
    }

    /**
     * Confirm that the selected wallet matches the carrier registered to the phone.
     * ClickPesa's preview response supplies the carrier without charging the customer.
     */
    public function assertPhoneProviderMatchesMethod(string $method, array $data): void
    {
        $preview = $this->previewUssdPush($data);
        $provider = strtoupper((string) data_get($preview, 'sender.accountProvider', ''));
        $normalizedProvider = preg_replace('/[^A-Z0-9]/', '', $provider);

        if ($normalizedProvider === '') {
            throw new ClickPesaException(
                'We could not verify the mobile money provider for this phone number. Check the number and try again.'
            );
        }

        $methodLabels = [
            'mpesa' => 'M-Pesa',
            'airtel_money' => 'Airtel Money',
            'yas' => 'Mixx by Yas',
            'halopesa' => 'HaloPesa',
        ];
        $methodAliases = [
            'mpesa' => ['VODACOM', 'MPESA'],
            'airtel_money' => ['AIRTEL'],
            'yas' => ['TIGO', 'YAS', 'MIXX'],
            'halopesa' => ['HALOTEL', 'HALOPESA', 'HALO'],
        ];
        $providerLabels = [
            'VODACOM' => 'M-Pesa',
            'MPESA' => 'M-Pesa',
            'AIRTEL' => 'Airtel Money',
            'TIGO' => 'Mixx by Yas',
            'YAS' => 'Mixx by Yas',
            'MIXX' => 'Mixx by Yas',
            'HALOTEL' => 'HaloPesa',
            'HALOPESA' => 'HaloPesa',
            'HALO' => 'HaloPesa',
        ];

        $expectedLabel = $methodLabels[$method] ?? 'the selected payment method';
        foreach ($methodAliases[$method] ?? [] as $alias) {
            if (str_contains($normalizedProvider, $alias)) {
                return;
            }
        }

        $actualLabel = 'the mobile money service ' . $provider;
        foreach ($providerLabels as $alias => $label) {
            if (str_contains($normalizedProvider, $alias)) {
                $actualLabel = $label;
                break;
            }
        }

        throw new ClickPesaException(
            "This phone number is registered with {$actualLabel}, but {$expectedLabel} was selected. " .
            "Select {$actualLabel} or enter a phone number registered with {$expectedLabel}."
        );
    }

    /**
     * Trigger the actual PIN prompt on the customer's phone.
     * This is the call your OrderController::payment() is currently missing.
     * orderReference: alphanumeric only, max 20 characters (ClickPesa limit).
     */
    public function initiateUssdPush(array $data): array
    {
        $orderReference = substr(
            preg_replace('/[^A-Za-z0-9]/', '', $data['order_reference']),
            0,
            20
        );

        $response = $this->authorizedRequest()
            ->post("{$this->baseUrl}/third-parties/payments/initiate-ussd-push-request", [
                'amount' => (string) $data['amount'],
                'currency' => $data['currency'] ?? 'TZS',
                'orderReference' => $orderReference,
                'phoneNumber' => self::normalizePhoneNumber($data['phone_number']),
            ]);

        $this->throwIfFailed($response, 'initiate USSD push');

        return $response->json();
    }

    /**
     * Query the current status of a payment by order reference.
     */
    public function queryPaymentStatus(string $orderReference): array
    {
        $orderReference = preg_replace('/[^A-Za-z0-9]/', '', $orderReference);

        $response = $this->authorizedRequest()
            ->get("{$this->baseUrl}/third-parties/payments/{$orderReference}");

        $this->throwIfFailed($response, 'query payment status');

        return $response->json();
    }

    /** Preview recipient details and fees before creating a mobile-money payout. */
    public function previewMobilePayout(array $data): array
    {
        $response = $this->authorizedRequest()->post(
            "{$this->baseUrl}/third-parties/payouts/preview-mobile-money-payout",
            [
                'amount' => (string) $data['amount'],
                'currency' => $data['currency'] ?? 'TZS',
                'orderReference' => $data['order_reference'],
                'phoneNumber' => self::normalizePhoneNumber($data['phone_number']),
            ]
        );
        $this->throwIfFailed($response, 'preview mobile-money payout');

        return $response->json();
    }

    /** Preview a bank recipient before creating the payout. */
    public function previewBankPayout(array $data): array
    {
        $response = $this->authorizedRequest()->post(
            "{$this->baseUrl}/third-parties/payouts/preview-bank-payout",
            [
                'amount' => (string) $data['amount'],
                'accountNumber' => $data['account_number'],
                'orderReference' => $data['order_reference'],
                'bic' => $data['bic'],
                'accountCurrency' => $data['currency'] ?? 'TZS',
            ]
        );
        $this->throwIfFailed($response, 'preview bank payout');

        return $response->json();
    }

    /** Submit one idempotently referenced mobile-money transfer. */
    public function createMobilePayout(array $data): array
    {
        $response = $this->authorizedRequest()->post(
            "{$this->baseUrl}/third-parties/payouts/create-mobile-money-payout",
            [
                'amount' => (string) $data['amount'],
                'currency' => $data['currency'] ?? 'TZS',
                'orderReference' => $data['order_reference'],
                'phoneNumber' => self::normalizePhoneNumber($data['phone_number']),
            ]
        );
        $this->throwIfFailed($response, 'create mobile-money payout');

        return $response->json();
    }

    /** Submit one idempotently referenced bank transfer. */
    public function createBankPayout(array $data): array
    {
        $response = $this->authorizedRequest()->post(
            "{$this->baseUrl}/third-parties/payouts/create-bank-payout",
            [
                'amount' => (string) $data['amount'],
                'accountNumber' => $data['account_number'],
                'accountName' => $data['account_name'],
                'orderReference' => $data['order_reference'],
                'bic' => $data['bic'],
                'accountCurrency' => $data['currency'] ?? 'TZS',
            ]
        );
        $this->throwIfFailed($response, 'create bank payout');

        return $response->json();
    }

    /** Query ClickPesa's authenticated payout status for webhook reconciliation. */
    public function queryPayoutStatus(string $orderReference): array
    {
        $response = $this->authorizedRequest()->get(
            "{$this->baseUrl}/third-parties/payouts/".rawurlencode($orderReference)
        );
        $this->throwIfFailed($response, 'query payout status');

        return $response->json();
    }

    protected function authorizedRequest()
    {
        // ClickPesa's generate-token response already includes the "Bearer "
        // prefix in the token string itself, so we set the header directly
        // instead of using withToken() (which would prepend a second "Bearer ").
        return Http::withHeaders([
            'Authorization' => $this->getToken(),
        ])
            ->acceptJson()
            ->timeout(30);
    }

    /**
     * Normalize a Tanzanian phone number to ClickPesa's required format:
     * country code prefix, no plus sign, no leading zero.
     * Accepts 0712345678, +255712345678, 255712345678, or with spaces/dashes.
     */
    public static function normalizePhoneNumber(string $phoneNumber): string
    {
        $digits = preg_replace('/\D/', '', $phoneNumber);

        if (str_starts_with($digits, '0')) {
            $digits = '255' . substr($digits, 1);
        } elseif (! str_starts_with($digits, '255')) {
            $digits = '255' . $digits;
        }

        return $digits;
    }

    protected function throwIfFailed($response, string $action): void
    {
        if ($response->failed()) {
            Log::error("ClickPesa {$action} failed", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            // ClickPesa returns a user-facing reason in `message` for
            // validation-style failures (insufficient funds, invalid phone,
            // duplicate order reference, etc.) — surface that exact text so
            // the controller/frontend can show it, instead of a generic
            // "something went wrong".
            $message = $response->json('message');

            if (is_array($message)) {
                $message = implode(' ', $message);
            }

            throw new ClickPesaException($message ?: "ClickPesa {$action} failed.");
        }
    }
}
