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
        $isUltramsg = str_contains(strtolower($this->apiUrl), 'ultramsg');

        try {
            if ($isUltramsg) {
                // UltraMsg API format
                $response = Http::post("{$this->apiUrl}/{$this->phoneId}/messages/chat", [
                    'token' => $this->accessToken,
                    'to'    => $normalized,
                    'body'  => $message,
                ]);
            } else {
                // Meta Graph API format
                $response = Http::withToken($this->accessToken)
                    ->post("{$this->apiUrl}/{$this->phoneId}/messages", [
                        'messaging_product' => 'whatsapp',
                        'to'                => $normalized,
                        'type'              => 'text',
                        'text'              => ['body' => $message],
                    ]);
            }

            if ($response->successful()) {
                Log::info("WhatsApp text sent to {$normalized} via " . ($isUltramsg ? 'UltraMsg' : 'Meta'));
                return true;
            }

            Log::error("WhatsApp failed to {$normalized}: " . $response->body());
            return false;

        } catch (\Exception $e) {
            Log::error("WhatsApp exception for {$normalized}: " . $e->getMessage());
            return false;
        }
    }

    public function sendTemplate(string $phone, string $templateName, array $variables = [], string $language = 'en_US'): bool
    {
        if (!$this->configured) {
            Log::warning("WhatsApp not configured — skipping template {$templateName} to {$phone}.");
            return false;
        }

        $isUltramsg = str_contains(strtolower($this->apiUrl), 'ultramsg');

        if ($isUltramsg) {
            // UltraMsg does not support Meta templates natively. We must compile the text locally and send as raw chat.
            $compiledMessage = $this->compileTemplate($templateName, $variables);
            return $this->send($phone, $compiledMessage);
        }

        $normalized = $this->normalizePhone($phone);
        
        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $normalized,
            'type'              => 'template',
            'template'          => [
                'name'     => $templateName,
                'language' => ['code' => $language],
            ],
        ];

        if (!empty($variables)) {
            $parameters = array_map(function ($value) {
                return ['type' => 'text', 'text' => (string) $value];
            }, $variables);

            $payload['template']['components'] = [
                [
                    'type'       => 'body',
                    'parameters' => $parameters
                ]
            ];
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->post("{$this->apiUrl}/{$this->phoneId}/messages", $payload);

            if ($response->successful()) {
                Log::info("WhatsApp Template '{$templateName}' sent to {$normalized} via Meta");
                return true;
            }

            Log::error("WhatsApp Template failed to {$normalized}: " . $response->body());
            return false;

        } catch (\Exception $e) {
            Log::error("WhatsApp Template exception for {$normalized}: " . $e->getMessage());
            return false;
        }
    }

    private function compileTemplate(string $templateName, array $variables): string
    {
        $templates = [
            'new_bill_uploaded_v1' => "*New Bill Uploaded* 📄\n\nHi {{1}},\nA new bill has been uploaded to your portal.\n\nInvoice No: {{2}}\nAmount: ₹{{3}}\n\nPlease log in to view and pay.",
            'payment_reminder_v1' => "*Payment Reminder* ⚠️\n\nHi {{1}},\nYou have {{2}} outstanding bill(s) pending on your account.\n\n{{3}}\n\nPlease log in to the portal and clear them immediately to avoid service disruption.",
            'payment_received_v1' => "*Payment Received* ⏳\n\nHi {{1}},\nWe have received your payment proof.\n\nInvoice No: {{2}}\nUTR: {{3}}\n\nOur team will verify it shortly.",
            'payment_verified_v1' => "*Payment Verified* ✅\n\nHi {{1}},\nYour payment has been successfully VERIFIED.\n\nInvoice No: {{2}}\n\nThank you for your business!",
            'payment_rejected_v1' => "*Payment Rejected* ❌\n\nHi {{1}},\nYour payment proof could NOT be verified.\n\nInvoice No: {{2}}\nReason: {{3}}\n\nPlease upload a clear proof or contact us.",
        ];

        $text = $templates[$templateName] ?? "Notification: {$templateName}";
        
        foreach ($variables as $index => $value) {
            $key = '{{' . ($index + 1) . '}}';
            $text = str_replace($key, (string)$value, $text);
        }

        return $text;
    }

    /**
     * Check if WhatsApp is properly configured.
     */
    public function isConfigured(): bool
    {
        return $this->configured;
    }
}

