<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $apiUrl;
    protected string $phoneId;
    protected string $accessToken;
    protected bool   $configured;

    public function __construct()
    {
        $this->apiUrl       = config('services.whatsapp.api_url', 'https://graph.facebook.com/v18.0');
        $this->phoneId      = config('services.whatsapp.phone_number_id', '');
        $this->accessToken  = config('services.whatsapp.access_token', '');
        $this->configured   = !empty($this->phoneId) && !empty($this->accessToken);
    }

    /**
     * Normalize a phone number to E.164 format (+91XXXXXXXXXX for Indian numbers).
     */
    protected function normalizePhone(string $phone): string
    {
        // Strip all non-digit characters
        $digits = preg_replace('/\D/', '', $phone);

        // If 10 digits, assume Indian number — prepend 91
        if (strlen($digits) === 10) {
            return '+91' . $digits;
        }

        // If 12 digits starting with 91 (no +)
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return '+' . $digits;
        }

        // Already has country code (11+ digits)
        if (strlen($digits) >= 11) {
            return '+' . $digits;
        }

        return '+' . $digits;
    }

    public function send(string $phone, string $message): bool
    {
        if (!$this->configured) {
            Log::warning("WhatsApp not configured — skipping message to {$phone}.");
            return false;
        }

        $normalized = $this->normalizePhone($phone);

        try {
            $response = Http::withToken($this->accessToken)
                ->post("{$this->apiUrl}/{$this->phoneId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to'                => $normalized,
                    'type'              => 'text',
                    'text'              => ['body' => $message],
                ]);

            if ($response->successful()) {
                Log::info("WhatsApp sent to {$normalized}");
                return true;
            }

            Log::error("WhatsApp failed to {$normalized}: " . $response->body());
            return false;

        } catch (\Exception $e) {
            Log::error("WhatsApp exception for {$normalized}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if WhatsApp is properly configured.
     */
    public function isConfigured(): bool
    {
        return $this->configured;
    }
}

