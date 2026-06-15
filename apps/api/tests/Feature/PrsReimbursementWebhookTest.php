<?php

namespace Tests\Feature;

use App\Modules\Reimbursements\Models\Reimbursement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrsReimbursementWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_signed_prs_webhook_creates_reimbursement(): void
    {
        config(['services.prs.webhook_secret' => 'test-prs-secret']);

        $payload = $this->payload();
        $response = $this->postJson('/api/reimbursements/webhooks/prs', $payload, $this->headers($payload));

        $response->assertCreated()
            ->assertJsonPath('data.source_system', 'prs')
            ->assertJsonPath('data.source_submission_id', '123');

        $this->assertDatabaseHas('reimbursements', [
            'source_system' => 'prs',
            'source_submission_id' => '123',
            'status' => 'submitted',
        ]);
        $this->assertDatabaseHas('receipts', [
            'invoice_number' => 'INV-2026-00001',
            'file_path' => 'https://prs.local/receipts/inv-00001.jpg',
        ]);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        config(['services.prs.webhook_secret' => 'test-prs-secret']);

        $payload = $this->payload();
        $headers = $this->headers($payload);
        $headers['X-PRS-Signature'] = 'sha256=bad';

        $this->postJson('/api/reimbursements/webhooks/prs', $payload, $headers)
            ->assertUnauthorized();
    }

    public function test_duplicate_delivery_is_idempotent(): void
    {
        config(['services.prs.webhook_secret' => 'test-prs-secret']);

        $payload = $this->payload();
        $headers = $this->headers($payload);

        $this->postJson('/api/reimbursements/webhooks/prs', $payload, $headers)->assertCreated();
        $this->postJson('/api/reimbursements/webhooks/prs', $payload, $headers)->assertOk();

        $this->assertSame(1, Reimbursement::where('source_system', 'prs')->where('source_submission_id', '123')->count());
    }

    private function payload(): array
    {
        return [
            'event' => 'prs.reimbursement.requested',
            'source_system' => 'prs',
            'source_submission_id' => '123',
            'source_user' => [
                'id' => 7,
                'name' => 'Sales User',
                'email' => 'sales@example.com',
                'department' => 'Sales & Marketing',
            ],
            'activity' => [
                'institution' => 'Clinic A',
                'contact_person' => 'Dr. Buyer',
                'product' => 'Kit A',
                'activity_type' => 'Demo',
                'activity_date' => '2026-06-15',
                'start_time' => '09:00',
                'description' => 'Approved activity',
            ],
            'receipt' => [
                'invoice_number' => 'INV-2026-00001',
                'transaction_date' => '2026-06-15',
                'tin' => '123-456-789-000',
                'vendor_name' => 'Clinic A',
                'expense_category_id' => null,
                'vat_classification' => 'vat',
                'total_amount' => 500,
                'vat_amount' => 60,
                'file_url' => 'https://prs.local/receipts/inv-00001.jpg',
                'items' => [
                    ['name' => 'Parking', 'quantity' => 1, 'price' => 500],
                ],
            ],
            'reimbursement' => [
                'description' => 'Reimbursement for Demo at Clinic A',
                'category' => 'Demo',
                'amount' => 500,
                'date' => '2026-06-15',
                'cutoff_period' => 'June 1-15, 2026',
            ],
        ];
    }

    private function headers(array $payload): array
    {
        $timestamp = (string) time();
        $body = json_encode($payload);

        return [
            'X-PRS-Event' => 'prs.reimbursement.requested',
            'X-PRS-Delivery-Id' => 'delivery-123',
            'X-PRS-Timestamp' => $timestamp,
            'X-PRS-Signature' => 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, 'test-prs-secret'),
        ];
    }
}
