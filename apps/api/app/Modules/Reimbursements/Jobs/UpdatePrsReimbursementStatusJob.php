<?php

namespace App\Modules\Reimbursements\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UpdatePrsReimbursementStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public $sourceSubmissionId)
    {
    }

    public function handle(): void
    {
        $apiUrl = config('services.prs.reimbursement_status_api_url');
        $apiKey = config('services.prs.reimbursement_status_api_key');
        $timeout = (int) config('services.prs.reimbursement_status_api_timeout', 10);

        if (!$apiUrl || !$apiKey) {
            Log::warning('PRS reimbursement status API is not configured. Skipping PRS status update.');
            return;
        }

        try {
            $response = Http::timeout($timeout)
                ->withToken($apiKey)
                ->acceptJson()
                ->post($apiUrl, [
                    'source_submission_id' => $this->sourceSubmissionId,
                    'is_reimbursed' => 1,
                ]);

            if ($response->failed()) {
                Log::error('Failed to update PRS reimbursement status.', [
                    'source_submission_id' => $this->sourceSubmissionId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            } else {
                Log::info('Successfully updated PRS reimbursement status.', [
                    'source_submission_id' => $this->sourceSubmissionId,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception while updating PRS reimbursement status.', [
                'source_submission_id' => $this->sourceSubmissionId,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
