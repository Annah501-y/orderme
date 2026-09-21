<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class BeemSmsService
{
    protected string $apiUrl = 'https://apisms.beem.africa/v1/send';

    /**
     * Send an SMS through Beem Africa.
     */
    public function send(
        string $phoneNumber,
        string $message
    ): array {
        $apiKey = config('services.beem.api_key');
        $secretKey = config('services.beem.secret_key');
        $senderId = config('services.beem.sender_id');

        if (! $apiKey || ! $secretKey) {
            throw new RuntimeException(
                'Beem API credentials are not configured.'
            );
        }

        $response = Http::withBasicAuth(
            $apiKey,
            $secretKey
        )->post($this->apiUrl, [
            'source_addr' => $senderId,
            'schedule_time' => '',
            'encoding' => 0,
            'message' => $message,
            'recipients' => [
                [
                    'recipient_id' => '1',
                    'dest_addr' => $phoneNumber,
                ],
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Beem SMS request failed: ' . $response->body()
            );
        }

        return $response->json();
    }
}