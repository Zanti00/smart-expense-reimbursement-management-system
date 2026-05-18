<?php

namespace App\Modules\Notifications\Services;

use Illuminate\Support\Facades\Log;

class NotificationDeliveryService
{
    /**
     * Send a template-based notification to a recipient user.
     *
     * @param string $recipientEmail
     * @param string $templateName
     * @param array $payload
     * @return bool
     */
    public static function send(string $recipientEmail, string $templateName, array $payload): bool
    {
        // Enforces templates only (as per SERMS compliance rule "Notifications must use templates only")
        $validTemplates = [
            'SUBMISSION_RECEIVED',
            'CLAIM_APPROVED',
            'CLAIM_REJECTED',
            'ADVANCE_DISBURSED',
            'LIQUIDATION_OVERDUE',
            'PENALTY_INCURRED',
        ];

        if (!in_array($templateName, $validTemplates)) {
            Log::error("Notification breach: Invalid or unapproved notification template '{$templateName}' requested.");
            return false;
        }

        // Standardized delivery logging (as per SERMS compliance rule "Notifications must log delivery attempts")
        Log::info("Notification dispatch attempt [Template: {$templateName}] to recipient [{$recipientEmail}]. Payload: " . json_encode($payload));

        // In a real application, you would queue this for dispatch via Laravel Queues (as per SERMS rule "asynchronous via Laravel Queues")
        // We will simulate a successful send for now.
        return true;
    }
}
