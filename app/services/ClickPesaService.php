<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ClickPesaService
{
    public function generateToken(): string
    {
        $response = Http::acceptJson()
            ->withHeaders([
                'api-key' => config('services.clickpesa.api_key'),
                'client-id' => config('services.clickpesa.client_id'),
            ])
            ->post('https://api.clickpesa.com/third-parties/generate-token');

        if ($response->failed()) {
            throw new RuntimeException(
                'Failed to generate token: ' . $response->status() . ' ' . $response->body()
            );
        }

        $token = $response->json('token');

        if (!$token) {
            throw new RuntimeException('Token not found.');
        }

        return $token;
    }
}