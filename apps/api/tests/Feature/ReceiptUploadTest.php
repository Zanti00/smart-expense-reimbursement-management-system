<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Modules\Users\Models\User;
use App\Modules\Reimbursements\Models\Receipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;

class ReceiptUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employee = User::create([
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'role' => 'employee',
            'grade' => 'L1',
            'department' => 'IT',
        ]);

        Storage::fake('supabase');
        Queue::fake();
    }

    public function test_single_file_upload_stores_array_file_fields(): void
    {
        $response = $this
            ->withoutMiddleware(\App\Modules\Shared\Http\Middleware\AuthenticateWithExternalService::class)
            ->actingAs($this->employee)
            ->post('/api/reimbursements/receipts', [
                'file' => UploadedFile::fake()->image('rcpt.png'),
            ]);

        $response->assertStatus(201);
        $this->assertIsArray($response->json('data.file_path'));
        $this->assertIsArray($response->json('data.file_hash'));
        $this->assertIsArray($response->json('data.file_type'));
        $this->assertIsArray($response->json('data.file_size_bytes'));
        $this->assertCount(1, $response->json('data.file_path'));
    }

    public function test_segmented_upload_stores_array_file_fields(): void
    {
        $response = $this
            ->withoutMiddleware(\App\Modules\Shared\Http\Middleware\AuthenticateWithExternalService::class)
            ->actingAs($this->employee)
            ->post('/api/reimbursements/receipts/segmented', [
                'files' => [
                    UploadedFile::fake()->image('a.png'),
                    UploadedFile::fake()->image('b.png'),
                ],
            ]);

        $response->assertStatus(201);
        $this->assertIsArray($response->json('data.file_path'));
        $this->assertCount(2, $response->json('data.file_path'));
        $this->assertCount(2, $response->json('data.file_hash'));
        $this->assertCount(2, $response->json('data.file_type'));
        $this->assertCount(2, $response->json('data.file_size_bytes'));
    }

    public function test_upload_preserves_user_metadata(): void
    {
        $response = $this
            ->withoutMiddleware(\App\Modules\Shared\Http\Middleware\AuthenticateWithExternalService::class)
            ->actingAs($this->employee)
            ->post('/api/reimbursements/receipts', [
                'file' => UploadedFile::fake()->image('rcpt_meta.png'),
                'vendor_name' => 'GroceryMart',
                'transaction_date' => '2026-06-10',
                'total_amount' => 100.00,
                'vat_amount' => 12.00,
                'tin' => '123-456-789-000',
                'invoice_number' => 'INV-2026-0100',
                'vat_classification' => 'vat',
                'currency' => 'PHP',
                'location' => 'Makati',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.vendor_name', 'GroceryMart');
        $response->assertJsonPath('data.tin', '123-456-789-000');
        $response->assertJsonPath('data.invoice_number', 'INV-2026-0100');
        $response->assertJsonPath('data.vat_classification', 'vat');
        $response->assertJsonPath('data.currency', 'PHP');
        $response->assertJsonPath('data.location', 'Makati');
        $response->assertJsonPath('data.total_amount', '100.00');
        $response->assertJsonPath('data.vat_amount', '12.00');
        $this->assertStringContainsString('2026-06-10', $response->json('data.transaction_date'));

        $this->assertDatabaseHas('receipts', [
            'uploaded_by' => $this->employee->id,
            'vendor_name' => 'GroceryMart',
            'transaction_date' => '2026-06-10 00:00:00',
            'total_amount' => '100.00',
            'vat_amount' => '12.00',
            'tin' => '123-456-789-000',
            'invoice_number' => 'INV-2026-0100',
            'vat_classification' => 'vat',
            'currency' => 'PHP',
            'location' => 'Makati',
        ]);
    }

    public function test_receipt_creation_writes_audit_log(): void
    {
        $response = $this
            ->withoutMiddleware(\App\Modules\Shared\Http\Middleware\AuthenticateWithExternalService::class)
            ->actingAs($this->employee)
            ->post('/api/reimbursements/receipts', [
                'file' => UploadedFile::fake()->image('rcpt_audit.png'),
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->employee->id,
            'actor_role' => 'employee',
            'action_type' => 'RECEIPT_CREATED',
            'entity_type' => 'receipt',
            'entity_id' => $response->json('data.id'),
        ]);
    }

    public function test_list_returns_array_typed_receipts(): void
    {
        $receipt = Receipt::create([
            'uploaded_by' => $this->employee->id,
            'file_path' => ['receipts/a.png', 'receipts/b.png'],
            'file_hash' => [str_repeat('f', 64), str_repeat('g', 64)],
            'file_type' => ['png', 'png'],
            'file_size_bytes' => [1024, 2048],
            'vendor_name' => 'Array Vendor',
        ]);

        $response = $this
            ->withoutMiddleware(\App\Modules\Shared\Http\Middleware\AuthenticateWithExternalService::class)
            ->actingAs($this->employee)
            ->getJson('/api/reimbursements/receipts');

        $response->assertStatus(200);
        $this->assertIsArray($response->json('data.0.file_path'));
        $this->assertIsArray($response->json('data.0.file_hash'));
        $this->assertIsArray($response->json('data.0.file_type'));
        $this->assertIsArray($response->json('data.0.file_size_bytes'));
        $this->assertCount(2, $response->json('data.0.file_path'));
    }
}
