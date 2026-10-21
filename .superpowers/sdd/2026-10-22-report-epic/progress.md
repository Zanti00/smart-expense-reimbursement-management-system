# SDD ledger — plan: docs/superpowers/plans/2026-10-22-report-epic.md

## Preflight Conflict Scan

### Cross-Task Interface & Shared File Table
| Task Pair | Producer Output | Consumer Input | Finding / Ruling |
| --- | --- | --- | --- |
| Task 1 → Task 2 | `GET /api/reports/{type}/{id}` (proxied at `/api/serms/reports/{type}/{id}`) with `Content-Type: application/pdf`, `Content-Disposition: attachment; filename="...-Report-#{id}.pdf"`, and `?by=cash_advance` query support | `useReportExport.js` calls `apiFetch('/api/serms/reports/' + type + '/' + cleanId + query)` and parses `Content-Disposition` | Clean — exact URL, query parameter (`?by=cash_advance`), and header names match. |
| Task 2 → Task 3 | `useReportExport()` returns `{ isExporting, exportReport(type, id, options) }` | `ReimbursementDetailsModal.vue` (`exportReport('reimbursement', viewingRecord.id)`) and `CashAdvanceDetailsModal.vue` (`exportReport('cash-advance', record.id)`) | Clean — signatures and return shapes match. |
| Task 2 → Task 4 | `useReportExport()` returns `{ isExporting, exportReport(type, id, options) }` | `LiquidationSettlementForm.vue` (`exportReport('liquidation', id, { byCashAdvance })`) and `LiquidationReviewModal.vue` (`exportReport('liquidation', id)`) | Clean — `cleanId` strips `LIQ-0*` prefix if passed a formatted string ID. |

### Per-Task Self-Consistency Table
| Task | Tests vs Implementation | Files Created vs Modified | Finding |
| --- | --- | --- | --- |
| Task 1 | `ReportExportTest.php` tests reimbursement, cash-advance, liquidation (direct + `?by=cash_advance`), 403 RBAC, and `REPORT_EXPORTED` audit log | Creates `ReportGeneratorService.php`, `ReportController.php`, `ReportExportTest.php`; modifies `Shared/routes/api.php` | Clean |
| Task 2 | `useReportExport.test.js` tests PDF blob download, `?by=cash_advance`, and 403 error toast | Creates `useReportExport.js` and `useReportExport.test.js` | Clean |
| Task 3 | `ReimbursementDetailsModal.test.js` and `CashAdvanceDetailsModal.test.js` test `data-testid="export-reimbursement-report-btn"` and `data-testid="export-cash-advance-report-btn"` | Modifies `ReimbursementDetailsModal.vue`, `ReimbursementDetailsModal.test.js`, `CashAdvanceDetailsModal.vue`; creates `CashAdvanceDetailsModal.test.js` | Clean |
| Task 4 | `LiquidationReviewModal.test.js` tests `data-testid="export-admin-liquidation-report-btn"` and `LiquidationSettlementForm.vue` `data-testid="export-liquidation-report-btn"` | Modifies `LiquidationSettlementForm.vue`, `LiquidationReviewModal.vue`, `LiquidationReviewModal.test.js` | Clean |

## Progress
- [x] **Task 1: Backend PDF Report Generator Service, Controller, Route & Audit Logging (`G2-054`, `G2-061`)** — DONE
  - Implemented `ReportGeneratorService.php`, `ReportController.php`, `routes/api.php`, and `ReportExportTest.php`.
  - Spec & Quality Review: Verified `php artisan test tests/Feature/ReportExportTest.php` passes 4/4 tests (36 assertions) covering reimbursement, cash advance, liquidation (direct + `?by=cash_advance` fallback), 403 RBAC restriction, and immutable `REPORT_EXPORTED` audit log entries.
- [x] **Task 2: Reusable Frontend Report Export Composable (`G2-054`, `G2-055`–`G2-060`)** — DONE
  - Implemented `apps/web/src/composables/useReportExport.js` and `apps/web/src/composables/useReportExport.test.js`.
  - Spec & Quality Review: Verified `npx vitest run src/composables/useReportExport.test.js` passes 3/3 tests covering PDF blob download + `Content-Disposition` filename extraction, `?by=cash_advance` query fallback, and 403 error toast notification.
- [x] **Task 3: Reimbursements & Cash Advances View Modal "Export Report" Integration (`G2-055`, `G2-056`, `G2-058`, `G2-059`)** — DONE
  - Updated `ReimbursementDetailsModal.vue`, `ReimbursementDetailsModal.test.js`, `CashAdvanceDetailsModal.vue`, and created `CashAdvanceDetailsModal.test.js`.
  - Spec & Quality Review: Verified `npx vitest run src/components/reimbursements/ReimbursementDetailsModal.test.js src/components/cash-advances/CashAdvanceDetailsModal.test.js` passes 5/5 tests with zero console warnings.
- [x] **Task 4: Employee & Admin Liquidation Details "Export Report" Integration (`G2-057`, `G2-060`)** — DONE
  - Updated `LiquidationSettlementForm.vue`, `LiquidationReviewModal.vue`, and `LiquidationReviewModal.test.js`.
  - Spec & Quality Review: Verified `npx vitest run src/composables/useReportExport.test.js src/components/reimbursements/ReimbursementDetailsModal.test.js src/components/cash-advances/CashAdvanceDetailsModal.test.js src/components/liquidations/LiquidationReviewModal.test.js` (12/12 tests passing) and `php artisan test tests/Feature/ReportExportTest.php` (4/4 tests, 36 assertions passing).

- [x] **Report PDF UI Modernization & Currency Encoding Fix (`G2-054`–`G2-061`)** — DONE
  - Upgraded `ReportGeneratorService.php` to a modern, corporate UI layout using standard `Helvetica`, `Helvetica-Bold`, and `Helvetica-Oblique` with `/Encoding /WinAnsiEncoding`.
  - Fixed the garbled `å` / `â‚±` encoding bug: Replaced the multi-byte Unicode `₱` symbol (not present in standard 14 PDF Type 1 fonts) with clean ISO `PHP ` currency formatting (`PHP 1,250.50`), universally supported across all PDF viewers.
  - Added visual branding banner, metadata summary bar with colored status pills, structured 2-column key-value cards, callout quote boxes for user/admin comments, visual progress stepper cards with connectors, receipts table with dark slate header and alternating rows, and dynamic multi-page footer.
  - Verified with `php artisan test tests/Feature/ReportExportTest.php` (4/4 passed) and Vitest (12/12 passed).
