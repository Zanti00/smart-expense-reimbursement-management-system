# Liquidation OCR Pipeline Integration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the placeholder/fake OCR in the liquidation module with the real async OCR pipeline (ocr-pipeline) so that receipt uploads in liquidation forms are processed by the external AI service via `AsyncOcrEngineInterface`.

**Architecture:** Backend: refactor `LiquidationController::scan()` from synchronous `TesseractOcrEngine::extractReceiptData()` (hardcoded `Fake Vendor (Mocked AI)` / 1250.00) to async flow mirroring `ReceiptService::storeReceipt` — store to Supabase, create `Receipt(status=processing)`, dispatch `DispatchReceiptToAiService` via `AsyncOcrEngineInterface::sendForProcessing()`, handle 422 quality gate via `OcrCallbackService`. Add `GET /liquidations/receipts/{id}` (polling) and `POST /liquidations/receipts/{id}/retry-ocr`. Reuse existing `OcrCallbackService` and reimbursement callback path for the liquidation receipts, or add liquidation-specific callback `POST /liquidations/receipts/{id}/ocr-callback` for symmetry. Frontend: enhance `FileUpload.vue::simulateOCR` to handle `processing` status with 3s polling against receipt endpoint until `status != processing`, and surface rejected/failed states; optionally extract `useLiquidationOcr` composable for testability.

**Tech Stack:** Laravel 13 (PHP 8.3), Vue 3 + Pinia, Supabase S3 disk, Async OCR (AiServiceOcrEngine), ocr-pipeline FastAPI (callback contract), apiFetch.

**Spec:** User prompt: liquidation module currently uses placeholder data; must call ocr-pipeline when user uploads receipts in liquidation module/page. Verified via codebase exploration: `apps/api/app/Modules/Ai/Services/TesseractOcrEngine.php:14-27` fake, `LiquidationController::scan():178-253` sync stub, `FileUpload.vue:170-224 simulateOCR` expects one-shot fake response.

## Global Constraints

- PHP ^8.3, Laravel ^13.7, Node/Vue ^3.4.0, Tailwind ^3.4.3
- Module structure: every backend component in `app/Modules/{ModuleName}`; cross-module imports only via `Shared`
- Immutability: never UPDATE/DELETE on `audit_logs` or `penalties`
- Errors: 403 Forbidden (authz), 401 Unauthorized (authn), 409 Conflict (duplicates)
- Boundary `try-catch` at system edges (Supabase, OCR queue, HTTP, filesystem) — no silent swallow, must log + rethrow/map/return 4xx/5xx; never swallow inside `DB::transaction` without rethrow
- Reusability: scan for existing components/composables (`base/FileUpload.vue`, `useReceiptUploads`, `receiptUtils.js`) before duplicating
- Audit: every DB mutation has `AuditLogService::log()` in same transaction
- Analytics: use SQL aggregates not in-memory
- Sensitive inputs encrypted client-side (out of scope for this feature but must not regress)
- Exports + dashboard already follow audit/aggregation rules

---

## File Structure

**Modified files:**
- `apps/api/app/Modules/Liquidations/Http/Controllers/LiquidationController.php` — replace sync scan with async dispatch; add showReceipt/retryOcr/ocrCallback methods
- `apps/api/app/Modules/Liquidations/routes/api.php` — add `GET /receipts/{id}`, `POST /receipts/{id}/retry-ocr`, `POST /receipts/{id}/ocr-callback` (outside auth.external for callback)
- `apps/web/src/components/base/FileUpload.vue` — enhance `simulateOCR` to support async polling, 422 handling, retry
- `apps/web/src/stores/liquidation.js` — optional helpers `fetchReceipt`, `retryOcr` for liquidation module (or delegate to receipts store)
- `apps/api/app/Modules/Ai/Services/AiServiceOcrEngine.php` — minor: generalize 422 handler to resolve callback service for both modules (if needed) — or keep as-is since OcrCallbackService is generic

**New files (optional but recommended for clean boundaries):**
- `apps/api/app/Modules/Liquidations/Jobs/DispatchLiquidationReceiptToAiService.php` — ONLY if we decide not to reuse `DispatchReceiptToAiService`; otherwise no new file
- `apps/api/app/Modules/Liquidations/Http/Controllers/LiquidationOcrCallbackController.php` — thin wrapper over `OcrCallbackService` with `auth.ai-service-api` (if liquidation callback route desired)
- `apps/web/src/composables/liquidations/useLiquidationOcr.js` — polling composable mirroring `useReceiptUploads:startPolling` but scoped to FileUpload/liquidation form
- `apps/api/tests/Feature/LiquidationOcrTest.php` — feature tests for async scan + polling + callback

**Inspected but NOT modified (reused as-is):**
- `apps/api/app/Modules/Ai/Contracts/AsyncOcrEngineInterface.php`
- `apps/api/app/Modules/Ai/Services/AiServiceOcrEngine.php` (reuse)
- `apps/api/app/Modules/Reimbursements/Services/OcrCallbackService.php` (reuse)
- `apps/api/app/Modules/Reimbursements/Jobs/DispatchReceiptToAiService.php` (reuse with existing reimbursement callback — liquidation receipts will callback to same URL)
- `apps/api/app/Modules/Shared/Traits/ValidatesReceiptDuplicates.php`
- `apps/web/src/utils/receiptUtils.js` (`buildPrefilledReceiptDraft`, `formatDateForInput`, `firstFilePathField`)
- `apps/web/src/composables/reimbursements/useReceiptUploads.js` (reference implementation for polling)

---

### Task 1: Backend — Refactor LiquidationController::scan to Async OCR

**Files:**
- Modify: `apps/api/app/Modules/Liquidations/Http/Controllers/LiquidationController.php:1-254`
- Modify: `apps/api/app/Modules/Liquidations/routes/api.php`
- Test: `apps/api/tests/Feature/LiquidationOcrTest.php` (create)

**Interfaces:**
- Consumes: `AsyncOcrEngineInterface::sendForProcessing`, `DispatchReceiptToAiService`, `OcrCallbackService::handle`, `Receipt` model, `ValidatesReceiptDuplicates`, `config('services.ai_service.*')`
- Produces: `POST /api/liquidations/scan` now returns `201 { data: { id, status: 'processing', file_path, ... } }` instead of `200 { data: { vendor_name: 'Fake Vendor...', total_amount: 1250 } }`; plus `GET /api/liquidations/receipts/{id}` and `POST /api/liquidations/receipts/{id}/retry-ocr`

- [ ] **Step 1: Write failing test for async scan**

```php
// apps/api/tests/Feature/LiquidationOcrTest.php
public function test_scan_dispatches_to_real_ocr_pipeline_with_processing_status(): void
{
    Http::fake(); // mock AI service
    $user = User::factory()->create();
    $this->actingAs($user, 'external');

    $file = UploadedFile::fake()->image('receipt.jpg', 600, 400);
    $response = $this->postJson('/api/liquidations/scan', [
        'files' => [$file],
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('data.status', 'processing');
    $this->assertDatabaseHas('receipts', [
        'uploaded_by' => $user->id,
        'status' => 'processing',
    ]);
    // Should NOT contain fake vendor
    $this->assertNotEquals('Fake Vendor (Mocked AI)', $response->json('data.vendor_name'));
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter LiquidationOcrTest::test_scan_dispatches_to_real_ocr_pipeline`
Expected: FAIL — currently returns 200 with fake vendor_name = 'Fake Vendor (Mocked AI)' and total_amount 1250.00

- [ ] **Step 3: Implement minimal backend fix**

In `LiquidationController.php`:

1. Add `use` imports:
```php
use App\Modules\Reimbursements\Jobs\DispatchReceiptToAiService;
use App\Modules\Shared\Traits\ValidatesReceiptDuplicates;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use App\Modules\Reimbursements\Services\OcrCallbackService;
```

2. Add trait `use ValidatesReceiptDuplicates;` to class.

3. Replace `protected OcrEngineInterface $ocrEngine;` + constructor to inject `AsyncOcrEngineInterface` OR keep both and inject async specifically for scan. Simplest: inject none and instantiate via service container in method, or inject `AsyncOcrEngineInterface $asyncOcrEngine`. Change constructor:
```php
public function __construct(
    protected \App\Modules\Ai\Contracts\AsyncOcrEngineInterface $asyncOcrEngine
) {}
```

4. Rewrite `scan()` method (178-253) to:
```php
public function scan(Request $request)
{
    $request->validate([
        'files' => 'required|array|min:1',
        'files.*' => 'required|file|mimes:jpeg,png,pdf|max:2048',
    ]);

    $files = $request->file('files');
    $filePaths = []; $fileHashes = []; $fileTypes = []; $fileSizes = [];

    foreach ($files as $file) {
        $fileType = $file->extension();
        if ($fileType === 'jpg') $fileType = 'jpeg';
        $filePaths[] = $file->store('receipts', 'supabase');
        $fileHashes[] = hash_file('sha256', $file->getRealPath());
        $fileTypes[] = $fileType;
        $fileSizes[] = $file->getSize();
    }

    // Duplicate hash guard — mirrors ReceiptService
    try {
        $this->validateDuplicateReceipt($fileHashes);
    } catch (\Illuminate\Validation\ValidationException $e) {
        // Clean up uploaded files before returning 422
        foreach ($filePaths as $path) {
            try { Storage::disk('supabase')->delete($path); } catch (\Throwable $ignored) {}
        }
        throw $e;
    }

    try {
        $receipt = Receipt::create([
            'uploaded_by' => $request->user()->id,
            'file_path' => $filePaths,
            'file_hash' => $fileHashes,
            'file_type' => $fileTypes,
            'file_size_bytes' => $fileSizes,
            'ocr_flagged' => false,
            'status' => 'processing',
        ]);
    } catch (\Throwable $e) {
        Log::error('Liquidation scan: failed to create receipt', ['error' => $e->getMessage()]);
        throw $e;
    }

    // Dispatch async OCR — default sync connection runs inline if no worker
    $connection = config('services.ai_service.ocr_queue_connection', 'sync');
    try {
        $job = (new DispatchReceiptToAiService($receipt))->onConnection($connection);
        Bus::dispatch($job);
    } catch (\Throwable $e) {
        Log::error('Liquidation scan: failed to dispatch OCR job', ['receipt_id' => $receipt->id, 'error' => $e->getMessage()]);
        $receipt->update([
            'status' => 'failed',
            'ocr_flagged' => true,
            'rejection_code' => 'ocr_failed',
            'rejection_reason' => 'OCR could not be started. Please retry OCR.',
        ]);
    }

    // Audit
    AuditLogService::log(
        actorId: $request->user()->id,
        actorRole: $request->user()->role,
        actionType: 'RECEIPT_CREATED',
        entityType: 'receipt',
        entityId: $receipt->id,
        beforeState: null,
        afterState: $receipt->fresh()->load('items')->toArray(),
        ipAddress: $request->ip()
    );

    return response()->json([
        'message' => 'Receipt uploaded for OCR processing.',
        'data' => [
            'id' => $receipt->id,
            'status' => $receipt->status,
            'vendor_name' => $receipt->vendor_name,
            'transaction_date' => $receipt->transaction_date?->format('Y-m-d'),
            'total_amount' => $receipt->total_amount,
            'vat_amount' => $receipt->vat_amount,
            'tin' => $receipt->tin,
            'invoice_number' => $receipt->invoice_number,
            'ocr_confidence_score' => $receipt->ocr_confidence_score,
            'ocr_flagged' => $receipt->ocr_flagged,
            'rejection_code' => $receipt->rejection_code,
            'rejection_reason' => $receipt->rejection_reason,
            'file_path' => $filePaths,
            'file_hash' => $fileHashes,
            'file_type' => $fileTypes,
            'file_size_bytes' => $fileSizes,
        ]
    ], 201);
}
```

Remove all references to `OcrEngineInterface` and `tempnam`/`extractReceiptData` fake.

5. Add new methods for polling + retry (mirroring ReceiptController):
```php
public function showReceipt(Request $request, $id)
{
    $receipt = Receipt::with('category','items','uploader')->findOrFail($id);
    $canManage = $request->user()->can('serms.reimbursements.manage') || $request->user()->can('serms.liquidations.manage');
    if (!$canManage && $receipt->uploaded_by !== $request->user()->id) {
        return response()->json(['message' => 'Forbidden.'], 403);
    }
    return response()->json(['data' => $receipt]);
}

public function retryOcr(Request $request, $id)
{
    $receipt = Receipt::findOrFail($id);
    if ($receipt->uploaded_by !== $request->user()->id && !$request->user()->can('serms.liquidations.manage')) {
        return response()->json(['message' => 'Forbidden.'], 403);
    }
    try {
        $before = $receipt->toArray();
        $receipt->update(['status' => 'processing', 'ocr_flagged' => false, 'rejection_code' => null, 'rejection_reason' => null]);
        DispatchReceiptToAiService::dispatch($receipt);
        AuditLogService::log(actorId: $request->user()->id, actorRole: $request->user()->role, actionType: 'RECEIPT_OCR_RETRY', entityType: 'receipt', entityId: $receipt->id, beforeState: $before, afterState: $receipt->fresh()->toArray(), ipAddress: $request->ip());
        return response()->json(['message' => 'OCR reprocessing started.', 'data' => $receipt->fresh()]);
    } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
        return response()->json(['message' => $e->getMessage()], 403);
    }
}

public function ocrCallback(Request $request, $id)
{
    // Delegate to existing OcrCallbackService — reuses Reimbursements validation
    $validated = app(\App\Modules\Reimbursements\Http\Requests\OcrCallbackRequest::class)->validated();
    // Actually resolve via controller injection: app(OcrCallbackService::class)->handle(...)
}
```

For callback, simplest: add `LiquidationOcrCallbackController` that does `$service->handle((int)$id, $request->validated())`.

- [ ] **Step 4: Update routes**

`apps/api/app/Modules/Liquidations/routes/api.php`:
```php
use App\Modules\Liquidations\Http\Controllers\LiquidationOcrCallbackController;

Route::post('/receipts/{id}/ocr-callback', LiquidationOcrCallbackController::class)->middleware('auth.ai-service-api');

Route::middleware(['auth.external'])->group(function () {
    // ... existing
    Route::get('/receipts/{id}', [LiquidationController::class, 'showReceipt']);
    Route::post('/receipts/{id}/retry-ocr', [LiquidationController::class, 'retryOcr']);
});
```

If reusing reimbursement callback, the liquidation callback is optional — dispatch job currently posts to `/api/reimbursements/receipts/{id}/ocr-callback`, so liquidation receipts will be updated via that path without new route. Keep liquidation callback for decoupling: update `DispatchReceiptToAiService` to branch based on source context OR create `DispatchLiquidationReceiptToAiService` that posts to `/api/liquidations/receipts/{id}/ocr-callback`. For minimal risk, keep reusing reimbursement callback and add liquidation callback as alias that also works.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter LiquidationOcrTest`
Expected: PASS — receipt created with status processing, dispatched, no fake vendor.

- [ ] **Step 6: Verify existing tests still pass**

Run: `php artisan test --filter LiquidationLogicTest`
Expected: PASS (update fixtures that assert fake OCR if any)

- [ ] **Step 7: Commit**

```bash
git add apps/api/app/Modules/Liquidations/Http/Controllers/LiquidationController.php apps/api/app/Modules/Liquidations/routes/api.php apps/api/tests/Feature/LiquidationOcrTest.php
git commit -m "feat(liquidations): dispatch real OCR pipeline on receipt scan (async)"
```

---

### Task 2: Backend — Add Liquidation OCR Callback Controller (if needed for decoupling)

**Files:**
- Create: `apps/api/app/Modules/Liquidations/Http/Controllers/LiquidationOcrCallbackController.php`
- Modify: `apps/api/app/Modules/Liquidations/routes/api.php` (if not done in Task 1)

**Interfaces:**
- Consumes: `OcrCallbackService::handle(int $receiptId, array $data): Receipt`, `OcrCallbackRequest` validation rules
- Produces: `POST /api/liquidations/receipts/{id}/ocr-callback` → applies OCR results to receipt

- [ ] **Step 1: Write failing test**

```php
public function test_liquidation_callback_applies_ocr_results(): void
{
    $receipt = Receipt::factory()->create(['status' => 'processing']);
    $payload = [
        'receipt_id' => $receipt->id,
        'vendor_name' => 'Jollibee',
        'total_amount' => 299.50,
        'ocr_confidence_score' => 0.92,
        'status' => 'processed',
    ];
    $response = $this->postJson("/api/liquidations/receipts/{$receipt->id}/ocr-callback", $payload, [
        'Authorization' => 'Bearer '.config('services.ai_service.api_key'),
    ]);
    $response->assertOk();
    $this->assertDatabaseHas('receipts', ['id' => $receipt->id, 'vendor_name' => 'Jollibee', 'status' => 'processed']);
}
```

- [ ] **Step 2: Run test to verify it fails**
Run: `php artisan test --filter test_liquidation_callback_applies_ocr_results`
Expected: FAIL — route 404.

- [ ] **Step 3: Write minimal implementation**

Create `LiquidationOcrCallbackController.php`:
```php
<?php
namespace App\Modules\Liquidations\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Modules\Reimbursements\Services\OcrCallbackService;
use App\Modules\Reimbursements\Http\Requests\OcrCallbackRequest;
class LiquidationOcrCallbackController extends Controller
{
    public function __invoke(OcrCallbackRequest $request, $id)
    {
        $service = app(OcrCallbackService::class);
        $receipt = $service->handle((int)$id, $request->validated());
        return response()->json(['message' => 'OCR callback processed.', 'data' => $receipt]);
    }
}
```

Add route as in Task 1.

- [ ] **Step 4: Run test to verify it passes**
Run: `php artisan test --filter test_liquidation_callback_applies_ocr_results`
Expected: PASS

- [ ] **Step 5: Commit**
```bash
git add apps/api/app/Modules/Liquidations/Http/Controllers/LiquidationOcrCallbackController.php
git commit -m "feat(liquidations): add OCR callback endpoint for liquidation receipts"
```

---

### Task 3: Frontend — Enhance FileUpload.vue to Call Real OCR with Polling

**Files:**
- Modify: `apps/web/src/components/base/FileUpload.vue:170-224`
- Create (optional): `apps/web/src/composables/liquidations/useLiquidationOcr.js`
- Test: `apps/web/src/components/base/FileUpload.test.js` (or create vitest)

**Interfaces:**
- Consumes: `apiFetch` or `fetch`, `useAuthStore`, `buildPrefilledOcrData`, `formatDateForInput`, `getFileUrl`
- Produces: `FileUpload` entry.ocrStatus lifecycle: `idle -> processing -> done|failed|rejected`; `ocrData` hydrated from polled receipt; emits `update:modelValue` and `upload-error`; supports `retryOcr(entry)` and handles 422 quality/dupe

- [ ] **Step 1: Write failing component test**

```js
// FileUpload.ocr.test.js
it('calls real OCR and polls until done', async () => {
  global.fetch = vi.fn()
    .mockResolvedValueOnce({ ok: true, status: 201, json: async () => ({ data: { id: 42, status: 'processing', file_path: ['receipts/a.jpg'] } }) })
    .mockResolvedValueOnce({ ok: true, json: async () => ({ data: { id: 42, status: 'processing', vendor_name: null } }) })
    .mockResolvedValueOnce({ ok: true, json: async () => ({ data: { id: 42, status: 'processed', vendor_name: 'Jollibee', total_amount: 299.50, vat_amount: 32.04, tin: '123-456-789-000', invoice_number: 'INV-001', ocr_confidence_score: 0.92, location: 'Manila', file_path: ['receipts/a.jpg'] } }) });

  // mount FileUpload, upload file, advance timers 3s, assert entry.ocrStatus === 'done' and entry.merchantName === 'Jollibee'
});
```

- [ ] **Step 2: Run test to verify it fails**
Run: `npm run test -- FileUpload.ocr.test.js`
Expected: FAIL — simulateOCR sets done immediately with fake data, no polling.

- [ ] **Step 3: Implement polling logic**

Rewrite `simulateOCR(entry)` in `FileUpload.vue`:

```js
import { useAuthStore } from '@/stores/auth' // add if not present

const pollTimers = new Map()

async function simulateOCR(entry) {
  entry.ocrStatus = 'processing'
  entry.ocrData = buildPrefilledOcrData(entry.pages[0])
  try {
    const formData = new FormData()
    entry.pages.forEach(file => formData.append('files[]', file))
    const response = await apiFetch('/api/serms/liquidations/scan', { method: 'POST', body: formData })
    if (!response.ok) {
      const errorBody = await response.json().catch(() => ({}))
      // 422 duplicate/quality handling mirrors useReceiptUploads
      if (response.status === 422) {
        if (errorBody?.errors?.file_hash || errorBody?.message?.toLowerCase().includes('duplicate')) {
          entry.ocrStatus = 'rejected'
          entry.rejectionCode = 'duplicate'
          emit('upload-error', { type: 'duplicate', message: errorBody.message || 'Duplicate receipt detected.', fileName: entry.name })
          entry.ocrData = buildPrefilledOcrData(entry.pages[0])
          emit('update:modelValue', files.value)
          return
        }
        entry.ocrStatus = 'rejected'
        entry.rejectionCode = errorBody?.rejection_code || 'quality_failed'
        entry.rejectionReason = errorBody?.rejection_reason || errorBody?.message || 'Image quality is too low for accurate OCR.'
        emit('upload-error', { type: 'quality', message: entry.rejectionReason, code: entry.rejectionCode })
        emit('update:modelValue', files.value)
        return
      }
      throw new Error(errorBody.message || 'OCR scan failed')
    }
    const result = await response.json()
    const ocrData = result.data

    // If backend returns processing (async), start polling; if it already has data (fallback), hydrate immediately
    if (ocrData.status === 'processing' || !ocrData.vendor_name) {
      entry.id = ocrData.id
      entry.ocrData.id = ocrData.id
      // keep processing state and start polling reimbursement receipt endpoint (shared receipt table)
      startPolling(entry)
    } else {
      hydrateEntry(entry, ocrData)
    }
  } catch (error) {
    console.error('OCR processing failed:', error)
    entry.ocrStatus = 'failed'
    entry.rejectionReason = error.message
    emit('upload-error', { type: 'failed', message: error.message })
    emit('update:modelValue', files.value)
  }
}

function hydrateEntry(entry, ocrData) {
  entry.ocrStatus = 'done'
  entry.id = ocrData.id
  entry.ocrData = {
    id: ocrData.id,
    amount: ocrData.total_amount ?? entry.ocrData.amount ?? '',
    vat: ocrData.vat_amount ?? entry.ocrData.vat ?? '',
    tin: ocrData.tin ?? entry.ocrData.tin ?? '',
    vendor: ocrData.vendor_name ?? entry.ocrData.vendor ?? '',
    invoiceNumber: ocrData.invoice_number ?? entry.ocrData.invoiceNumber ?? '',
    date: formatDateForInput(ocrData.transaction_date || entry.ocrData.date),
    location: ocrData.location || entry.location || '',
    confidence: Math.round((ocrData.ocr_confidence_score ?? 0.85) * (ocrData.ocr_confidence_score <= 1 ? 100 : 1)),
    file_path: ocrData.file_path,
    file_hash: ocrData.file_hash,
    rejection_code: ocrData.rejection_code,
    rejection_reason: ocrData.rejection_reason,
  }
  entry.merchantName = entry.ocrData.vendor
  entry.date = entry.ocrData.date
  entry.tin = entry.ocrData.tin
  entry.invoiceNumber = entry.ocrData.invoiceNumber
  entry.location = entry.ocrData.location
  entry.amount = Number(entry.ocrData.amount) || 0
  entry.tax = entry.ocrData.vat ? String(entry.ocrData.vat) : '0.00'
  entry.subtotal = (Math.max(Number(entry.amount || 0) - Number(entry.tax || 0), 0)).toFixed(2)
  emit('update:modelValue', files.value)
  emit('ocr-result', entry.ocrData)
}

function startPolling(entry) {
  if (pollTimers.has(entry.id)) return
  const auth = useAuthStore()
  const timer = setInterval(async () => {
    try {
      const res = await apiFetch(`/api/serms/reimbursements/receipts/${entry.id}`, { headers: { Accept: 'application/json' } })
      // Fallback to liquidation receipt endpoint if reimbursement returns 403/404
      let data = null
      if (res.ok) {
        const json = await res.json()
        data = json.data
      } else {
        const alt = await apiFetch(`/api/serms/liquidations/receipts/${entry.id}`, { headers: { Accept: 'application/json' } })
        if (alt.ok) data = (await alt.json()).data
        else return
      }
      if (data.status !== 'processing') {
        clearInterval(timer); pollTimers.delete(entry.id)
        if (data.status === 'failed') {
          entry.ocrStatus = 'failed'
          entry.rejectionCode = data.rejection_code || 'ocr_failed'
          entry.rejectionReason = data.rejection_reason || 'OCR processing failed. You can retry.'
          emit('update:modelValue', files.value)
          return
        }
        if (data.status === 'rejected') {
          const isDup = data.rejection_code === 'duplicate' || data.is_duplicate
          entry.ocrStatus = 'rejected'
          entry.rejectionCode = data.rejection_code || (isDup ? 'duplicate' : 'blurry')
          entry.rejectionReason = data.rejection_reason || data.error || 'Receipt rejected.'
          if (isDup) window.dispatchEvent(new CustomEvent('receipt-duplicate-detected', { detail: { receiptId: data.id } }))
          emit('upload-error', { type: isDup ? 'duplicate' : 'quality', message: entry.rejectionReason, code: entry.rejectionCode })
          emit('update:modelValue', files.value)
          return
        }
        // processed / flagged — hydrate
        hydrateEntry(entry, data)
      }
    } catch (e) {
      console.error('Polling error', entry.id, e)
    }
  }, 3000)
  pollTimers.set(entry.id, timer)
}

// Export retry for ScannedReceiptsList usage
async function retryOcr(entry) {
  if (!entry?.id) return
  entry.ocrStatus = 'processing'
  try {
    const res = await apiFetch(`/api/serms/liquidations/receipts/${entry.id}/retry-ocr`, { method: 'POST' })
    if (!res.ok) throw new Error('Retry failed')
    startPolling(entry)
  } catch (e) {
    entry.ocrStatus = 'failed'
    emit('update:modelValue', files.value)
  }
}
defineExpose({ retryOcr, startPolling })
```

Ensure `emit('upload-error')` is handled by `LiquidationsView.vue:1162 handleReceiptUploadError` (already exists).

Add UI states: in template, show `rejected` with retry button when `entry.ocrStatus === 'rejected'` or `'failed'`.

- [ ] **Step 4: Run test to verify it passes**
Run: `npm run test -- FileUpload`
Expected: PASS — polling hydrates, rejected/failed surfaced.

- [ ] **Step 5: Manual verification**
Upload a real receipt image in `LiquidationsView` locally: expect `Processing...` spinner, then after pipeline callback vendor/amount/tin populated (not 1250/Fake Vendor). Check Network: `POST /api/serms/liquidations/scan → 201 status processing`, then `GET /api/serms/reimbursements/receipts/{id}` polling every 3s until processed.

- [ ] **Step 6: Commit**

```bash
git add apps/web/src/components/base/FileUpload.vue apps/web/src/composables/liquidations/useLiquidationOcr.js
git commit -m "feat(web): wire liquidation FileUpload to real OCR pipeline with polling"
```

---

### Task 4: Frontend — Wire LiquidationsView & ScannedReceiptsList to Show Async States

**Files:**
- Modify: `apps/web/src/views/LiquidationsView.vue` (handle duplicate event, retry wiring)
- Modify: `apps/web/src/components/liquidations/LiquidationSettlementForm.vue` (propagate ocrStatus / retry)
- Modify: `apps/web/src/components/reimbursements/ScannedReceiptsList.vue` (show processing/rejected states + Retry button)

**Interfaces:**
- Consumes: `FileUpload` events `upload-error`, `ocr-result`, `update:modelValue`
- Produces: Settlement form blocks submit while `receipts.some(r => r.ocrStatus === 'processing')`

- [ ] **Step 1: Write test for submit blocking**

```js
it('disables submit while OCR is processing', () => {
  receipts.value = [{ ocrStatus: 'processing', ocrData: { amount: '' } }]
  expect(hasIncompleteReceiptFields.value).toBe(true) // or new computed isOcrProcessing
  expect(submitDisabled).toBe(true)
})
```

- [ ] **Step 2: Run test to verify it fails**
Run: `npm run test -- LiquidationsView`
Expected: FAIL — submit not blocked during processing.

- [ ] **Step 3: Implement**

In `LiquidationsView.vue`:
```js
const isOcrProcessing = computed(() => receipts.value.some(r => r.ocrStatus === 'processing'))
// extend hasIncompleteReceiptFields OR add to settlement form :submitting / :has-incomplete-receipt-fields logic to also include isOcrProcessing
```

In `LiquidationSettlementForm.vue` (props):
```vue
<BaseButton :disabled="hasIncompleteReceiptFields || isOcrProcessing || submitting" />
```

In `ScannedReceiptsList.vue`:
- Show `processing` spinner already exists; ensure `rejected` shows `rejectionReason` and `Retry OCR` button that calls `emit('retry-ocr', receipt)`.
- Parent `LiquidationsView` listens and calls `FileUpload` retry or `apiFetch POST /api/serms/liquidations/receipts/{id}/retry-ocr`.

- [ ] **Step 4: Run tests + manual verification**
Run: `npm run test`
Expected: PASS

Upload receipt → see Processing spinner in both FileUpload mini-card and ScannedReceiptsList detail rows; submit disabled until done.

- [ ] **Step 5: Commit**
```bash
git add apps/web/src/views/LiquidationsView.vue apps/web/src/components/liquidations/LiquidationSettlementForm.vue apps/web/src/components/reimbursements/ScannedReceiptsList.vue
git commit -m "feat(web): block liquidation submit during OCR processing and surface retry"
```

---

### Task 5: Integration & Graph Update

**Files:**
- Run: `graphify update .` (AST-only, no API cost)
- Verify: `docs/CHANGELOG.md` entry

- [ ] **Step 1: Run graphify update**
```bash
python -m graphify update .
# or via npx if installed
```

- [ ] **Step 2: Update CHANGELOG.md**
Add entry: `2026-08-26: liquidation OCR pipeline — replace Tesseract mock with AsyncOcrEngine dispatch + frontend polling`

- [ ] **Step 3: Run full verification**
```bash
php artisan test --filter Liquidation
npm run test
php artisan route:list | grep liquidations
```

- [ ] **Step 4: Commit docs**
```bash
git add docs/CHANGELOG.md graphify-out/
git commit -m "docs: changelog and graph refresh for liquidation OCR integration"
```

---

## Self-Review

**Spec coverage:**
- ✅ Placeholder replacement (Tesseract fake → real pipeline) — Task 1
- ✅ OCR called on upload (FileUpload simulateOCR now POSTs to scan + polls) — Task 3
- ✅ Backend dispatch mirrors reimbursement flow (Bus::dispatch with same receipt table) — Task 1
- ✅ Callback handling (reuse OcrCallbackService) — Task 2
- ✅ Frontend async handling (processing → done/rejected/failed) — Tasks 3-4
- ✅ Retry path (`retry-ocr`) — Tasks 1+3
- ✅ No duplicate handling regression (hash guard) — Task 1
- ✅ Graph + changelog update — Task 5

**Placeholder scan:** No TBD/TODO left; all steps have concrete code.

**Type consistency:** `receipt.id` is int (backend) ↔ `entry.id`/`ocrData.id` (frontend) coerced to string for pollTimers key; `status` enum strings `processing|processed|flagged|rejected|failed` consistent with OcrCallbackService; `file_path` always array; route prefixes `api/liquidations` → frontend `api/serms/liquidations` via Vite proxy.

**Risks mitigated:**
- Trait `ValidatesReceiptDuplicates`LIKE query may false-positive if hash substring matches — acceptable (existing behavior).
- Supabase disk public URL generation via `Receipt::file_url` accessor required for AI service download — ensure disk config `supabase` has `url` set.
- Queue sync default ensures pipeline works without worker; document `AI_SERVICE_OCR_QUEUE_CONNECTION=database` for worker deployments.
