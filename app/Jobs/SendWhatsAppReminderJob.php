<?php

namespace App\Jobs;

use App\Models\ReminderLog;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWhatsAppReminderJob implements ShouldQueue
{
    use Queueable;

    protected $phone;
    protected $templateName;
    protected $variables;
    protected $customerId;
    protected $billId;
    protected $ruleId;

    public function __construct(string $phone, string $templateName, array $variables, int $customerId, int $billId, ?int $ruleId = null)
    {
        $this->phone = $phone;
        $this->templateName = $templateName;
        $this->variables = $variables;
        $this->customerId = $customerId;
        $this->billId = $billId;
        $this->ruleId = $ruleId;
    }

    public function handle(WhatsAppService $whatsapp): void
    {
        // NOTE: In production with pre-approved templates, WhatsAppService should hit a template endpoint
        // instead of the free-form text endpoint.
        $success = $whatsapp->sendTemplate($this->phone, $this->templateName, $this->variables);

        ReminderLog::create([
            'customer_id' => $this->customerId,
            'bill_id' => $this->billId,
            'rule_id' => $this->ruleId,
            'channel' => 'whatsapp',
            'status' => $success ? 'sent' : 'failed',
            'sent_at' => $success ? Carbon::now() : null,
            'error_msg' => $success ? null : 'WhatsApp API failed',
        ]);

        sleep(5);
    }
}
