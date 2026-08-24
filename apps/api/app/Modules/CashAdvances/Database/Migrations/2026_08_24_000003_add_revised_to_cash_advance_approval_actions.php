<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add 'revised' to cash_advance_approval_actions.action enum.
     *
     * Root cause: CashAdvanceService::rejectAdvance() inserts 'revised'
     * (past tense, consistent with 'approved'/'rejected') when action='revise'
     * and status is not terminal. The original migration only allowed
     * ENUM('approved','rejected'), causing MySQL Warning 1265 Data truncated
     * -> QueryException 500 on revise flows.
     *
     * The 2026_08_24_000002 migration added revision_count + status 'revise'
     * to cash_advances but never updated this approval_actions enum.
     *
     * Decision: use ENUM('approved','rejected','revised') minimal fix.
     * Past tense 'revised' matches existing 'approved'/'rejected' for
     * consistency. Defensive alternative if present tense is desired:
     * ENUM('approved','rejected','revised','revise') — would allow both.
     */
    public function up(): void
    {
        if (config('database.default') === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE cash_advance_approval_actions MODIFY COLUMN action ENUM('approved','rejected','revised') NOT NULL");
    }

    /**
     * Revert to original enum.
     */
    public function down(): void
    {
        if (config('database.default') === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE cash_advance_approval_actions MODIFY COLUMN action ENUM('approved','rejected') NOT NULL");
    }
};
