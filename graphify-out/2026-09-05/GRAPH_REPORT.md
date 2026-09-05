# Graph Report - smart-expense-management-system  (2026-09-05)

## Corpus Check
- 330 files · ~197,276 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 2342 nodes · 3649 edges · 261 communities (194 shown, 67 thin omitted)
- Extraction: 94% EXTRACTED · 5% INFERRED · 0% AMBIGUOUS · INFERRED: 193 edges (avg confidence: 0.75)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `0fa388bd`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- SERMS Master Specification
- LiquidationsView.vue
- Receipt 1 - Jollibee Las Vegas
- Illuminate\Support\ServiceProvider
- DispatchReceiptToAiService
- MyExpenseView.vue
- CashAdvance
- Receipt
- CashAdvanceDetailsModal.vue
- PayloadDecryptionService
- dependencies
- ReceiptUploadModal.vue
- TestCase
- ReimbursementFormView.vue
- ExpenseCategory
- ImagePreviewModal.vue
- CashAdvanceRevisionTest
- UserFactory.php
- index.js
- Liquidation
- src/views/CashAdvancesView.vue
- FileUpload.vue
- CashAdvancePolicy
- Controller
- receiptUtils.js
- src/views/CashAdvanceFormView.vue
- ReimbursementsView.vue
- ReceiptDetailsModal.vue
- devDependencies
- DashboardView.vue
- ReceiptFilteringTest
- Changelog — SERMS
- PolicyView.vue
- Issue Creation Guide — SERMS
- CashAdvanceTable.vue
- composer.json
- scripts
- BaseTable.vue
- SegmentedReceiptUpload.vue
- src/layouts/AppLayout.vue
- Illuminate\Database\Eloquent\Model
- CashAdvanceStatusHistory
- OcrCallbackRequest
- AuthenticateWithExternalService.php
- DecisionConfirmationModal.vue
- MetaAndAttachments.vue
- useReceiptUploads
- apiFetch
- StoreReceiptRequest
- AppServiceProvider
- UploadedFile
- ActionDropdownMenu.vue
- CurrencySelect.vue
- ScannedReceiptsList.vue
- useReimbursementDetails
- mockOcr.js
- User
- ReceiptViewModal.vue
- UNLIQUIDATED Status
- MockOcrService
- OcrCallbackServiceTest
- CashAdvancePasswordVerificationTest
- BaseButton.vue
- BaseUtilityToolbar.vue
- DuplicateReceiptModal.vue
- LiquidationSettlementForm.vue
- UnifiedRoadmapStepper.vue
- ReimbursementDetailsModal.vue
- ReimbursementsTable.vue
- AuditLogService
- calculateLiquidationStatus
- Governance and Security
- PrsReimbursementWebhookTest
- OCRExtractedFields.vue
- useLiquidationDecisions
- useReimbursementDecisions
- Pull Request Template
- SERMS Overview
- OCR Workflow
- DatabaseSeeder
- require
- require-dev
- setup
- PHP-FPM Service
- DeleteConfirmModal.vue
- src/views/admin/ReportsView.vue
- Queue Worker Service
- config
- BasePagination.vue
- ReceiptQualityRejectionModal.vue
- useReimbursementFilters
- formatters.js
- Hold-to-Confirm Pattern
- ReimbursementPasswordVerificationTest
- CashAdvancesServiceProvider.php
- Clinical Neutral Palette
- BaseModal.vue
- BaseWarningBanner.vue
- ImagePreviewModal.test.js
- OCRField.vue
- Illuminate\Foundation\Http\FormRequest
- ExpenseCard.vue
- crypto.js
- reimbursementForwarding.js
- Debug Session Workflow
- AI Prompt Engineering Safety Review
- bootstrap/app.php
- psr-4
- Illuminate\Support\Str
- logging.php
- BaseInput.vue
- useNavLinks.js
- useUnsavedChanges
- policy.js
- employeeAdvanceStatus
- File Structure
- Illuminate\Support\Facades\Route
- ExampleTest
- SBSI Logo
- NotificationPanel.vue
- AdminCashAdvanceTable.vue
- ReceiptUploadModal.spec.js
- LiquidationAdvancesList.vue
- useReimbursementSubmit
- src/views/admin/AuditView.vue
- src/views/AuthCallbackView.vue
- opencode.json
- AuditLogsServiceProvider
- Illuminate\Database\Migrations\Migration
- .log
- ReceiptDuplicateDetected
- post-autoload-dump
- useOcrMode
- Illuminate\Database\Schema\Blueprint
- StoreReceiptRequest
- UpdateReceiptRequest
- TesseractOcrEngine.php
- ReimbursementController
- ResubmitReceiptRequest
- UpdateReceiptRequest
- Reimbursement
- 2026_08_01_134526_convert_receipts_columns_to_json.php
- GrantReimbursementRequest
- BaseToggleSwitch.vue
- AppLayout Component
- StatusBadge.vue
- useLiquidationForwarding
- useLiquidationSubmit
- confirmReceiptDecision
- resetLiquidationComposer
- graphify.js
- BaseButton.vue Component
- Boundary Try-Catch Error Handling Rule
- StatusBadge.vue Component
- docker-entrypoint.sh
- ReceiptsManagementHeader.vue
- RejectReimbursementRequest
- ReimbursementSummaryPanel.vue
- ToastNotification.vue
- constants.js
- reimbursementForwarding.test.js
- closeReview
- confirmDeleteLiquidation
- employeeOutstandingAdvances
- getActions
- getSortValue
- isValidReportAttachment
- Bug Report Template
- BaseReceiptImage.vue
- LiquidationReceiptModal.vue
- LiquidationReviewModal.vue
- Capstone Project
- Mock Receipt PNG Image
- StoreReimbursementRequest
- RoadmapStepItem.vue
- normalize
- UpdateCashAdvanceRequest
- Illuminate\Http\Request
- LiquidationsServiceProvider.php
- BaseReceiptDetailModal.vue
- closeDetails
- UpdateReimbursementRequest
- NotificationsServiceProvider
- PrsReimbursementApiTest
- Illuminate\Support\Facades\Schema
- LiquidationLogicTest
- Role-Based Access Control
- ReimbursementsServiceProvider
- StoreCashAdvanceRequest
- drawSignature
- Expense Category Field
- normalizeStatus
- RejectCashAdvanceRequest
- console.php
- keywords

## God Nodes (most connected - your core abstractions)
1. `User` - 109 edges
2. `Receipt` - 91 edges
3. `CashAdvance` - 62 edges
4. `SERMS Master Specification` - 47 edges
5. `System Design Document` - 42 edges
6. `TestCase` - 40 edges
7. `Reimbursement` - 38 edges
8. `CashAdvanceRevisionTest` - 32 edges
9. `CashAdvancePasswordVerificationTest` - 26 edges
10. `Controller` - 25 edges

## Surprising Connections (you probably didn't know these)
- `Vue 3 SPA` --implements--> `pinia`  [INFERRED]
  docs/SERMS.md → apps/web/package.json
- `BaseButton.vue Component` --semantically_similar_to--> `BaseButton.vue Component`  [INFERRED] [semantically similar]
  AGENTS.md → apps/web/docs/technical_spec.md
- `StatusBadge.vue Component` --semantically_similar_to--> `StatusBadge.vue Component`  [INFERRED] [semantically similar]
  AGENTS.md → apps/web/docs/technical_spec.md
- `Role-Based Access Control` --semantically_similar_to--> `Role-Based Access Control`  [INFERRED] [semantically similar]
  .agents/rules/smart-expense-reimbursement-management-system.md → README.md
- `BIR Compliance` --semantically_similar_to--> `BIR Compliance`  [INFERRED] [semantically similar]
  .agents/rules/smart-expense-reimbursement-management-system.md → README.md

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **SERMS Compliance & Business Rules** — bir_vat_rules, immutable_audit, rbac, client_side_encryption, duplicate_detection_90d, daily_penalty, grace_period_7d, database_analytics [EXTRACTED 0.95]
- **Debug Session Phases** — agents_workflows_debug_reproduce, agents_workflows_debug_isolate, agents_workflows_debug_diagnose, agents_workflows_debug_fix [EXTRACTED 1.00]
- **Frontend Reactive Stores** — apps_web_docs_technical_spec_use_auth_store, apps_web_docs_technical_spec_use_reimbursement_store, apps_web_docs_technical_spec_use_cash_advance_store, apps_web_docs_technical_spec_use_notification_store [EXTRACTED 1.00]
- **Pull Request Pre-Merge Checklist** — github_pr_template_conventional_commits, github_pr_template_a09_rule, github_pr_template_audit_log_service, github_pr_template_bir_vat_compliance, github_pr_template_hold_to_confirm [EXTRACTED 1.00]
- **Prompt Safety Review Framework** — agents_workflows_improve_prompt_safety_assessment, agents_workflows_improve_prompt_bias_detection, agents_workflows_improve_prompt_security_privacy_assessment, agents_workflows_improve_prompt_effectiveness_evaluation [EXTRACTED 1.00]
- **SERMS Core Functional Modules** — readme_reimbursement_module, readme_cash_advance_liquidation, readme_governance_security [EXTRACTED 1.00]
- **SERMS Database Tables** — audit_logs_table, penalties_table, receipts_table, cash_advances_table, reimbursements_table, liquidations_table, users_table [EXTRACTED 1.00]
- **SERMS 9 Modules** — cashadvances_module, reimbursements_module, expenses_module, liquidations_module, auditlogs_module, notifications_module, ai_module, users_module, shared_module [EXTRACTED 1.00]
- **Capstone Enterprise Ecosystem** — cms, docs_serms, prs, ts, capstone_auth_module [INFERRED 0.75]
- **SERMS Technology Stack** — vue3_spa, laravel13, mysql8, redis, mongodb, supabase_bucket, pinia, tailwind_config, index_css, web_crypto_api [INFERRED 0.75]
- **GitHub Issue Reporting Templates** — github_issue_template_bug_report, github_issue_template_feature_request [INFERRED 0.85]

## Communities (261 total, 67 thin omitted)

### Community 0 - "SERMS Master Specification"
Cohesion: 0.07
Nodes (77): Ai Module, audit_logs table, AuditLogService, AuditLogs Module, SSO Authentication Flow, BaseButton.vue, BaseKpiGrid.vue, BaseModal.vue (+69 more)

### Community 1 - "LiquidationsView.vue"
Cohesion: 0.03
Nodes (65): activeDraft, activeStatus, { addToast }, advancePanelCollapsed, agingInfo, {
  approvingId,
  rejectingId,
  revisionAction,
  confirmPassword,
  rejectionComment,
  isReviewSubmitting,
  openApproveModal,
  openRejectModal,
  cancelApprove,
  cancelReject,
  confirmApprove,
  confirmReject,
}, auth, calculatedOutstandingBalance (+57 more)

### Community 2 - "Receipt 1 - Jollibee Las Vegas"
Cohesion: 0.05
Nodes (49): Currency Field, USD, 5/20/2025 11:47:32 AM, 340, 1 Family Bundle - 10 pieces (Chickenjoy bucket + 3 large sides), 3890 S Maryland Parkway Suite 137, Las Vegas, NV 89119, Jollibee, 358 (+41 more)

### Community 3 - "Illuminate\Support\ServiceProvider"
Cohesion: 0.18
Nodes (5): AiServiceProvider, SharedServiceProvider, UsersServiceProvider, Illuminate\Support\ServiceProvider, Router

### Community 4 - "DispatchReceiptToAiService"
Cohesion: 0.38
Nodes (7): DispatchReceiptToAiService, UpdatePrsReimbursementStatusJob, Illuminate\Bus\Queueable, Illuminate\Contracts\Queue\ShouldQueue, Illuminate\Foundation\Bus\Dispatchable, Illuminate\Queue\InteractsWithQueue, Illuminate\Queue\SerializesModels

### Community 5 - "MyExpenseView.vue"
Cohesion: 0.05
Nodes (38): activeCategory, activeSort, activeStatus, { addToast }, adminNotesByReceipt, auth, automaticRejectedReceipts, CATEGORIES (+30 more)

### Community 7 - "Receipt"
Cohesion: 0.09
Nodes (7): Receipt, ReceiptItem, duplicateReceiptExists(), validateDuplicateReceipt(), ReceiptOwnerUpdateTest, ReceiptStorageRetrievalTest, Illuminate\Database\Eloquent\SoftDeletes

### Community 8 - "CashAdvanceDetailsModal.vue"
Cohesion: 0.06
Nodes (24): { addToast }, adminPassword, adminReviewNotes, auth, canAcknowledgeFromCurrentView, confirmationAction, documentData, isAdminDecisionSubmitting (+16 more)

### Community 9 - "PayloadDecryptionService"
Cohesion: 0.12
Nodes (3): CryptoController, PayloadDecryptionService, Illuminate\Support\Facades\Config

### Community 10 - "dependencies"
Cohesion: 0.05
Nodes (42): dependencies, chart.js, @headlessui/vue, lucide-vue-next, pinia, vue, vue3-lottie, vue-chartjs (+34 more)

### Community 11 - "ReceiptUploadModal.vue"
Cohesion: 0.07
Nodes (32): { addToast }, allOcrComplete, buildUpdatePayload(), canSaveNew, close(), emit, handleRetryOcr(), isDirty (+24 more)

### Community 12 - "TestCase"
Cohesion: 0.15
Nodes (10): ExampleTest, TestCase, Carbon\Carbon, Illuminate\Foundation\Testing\RefreshDatabase, Illuminate\Foundation\Testing\TestCase, Illuminate\Http\UploadedFile, Illuminate\Support\Facades\Http, Illuminate\Support\Facades\Queue (+2 more)

### Community 13 - "ReimbursementFormView.vue"
Cohesion: 0.06
Nodes (30): { addToast }, canProceed, cutoffPeriod, dismiss(), emit, fetching, forwardedReceiptCount, forwardedSource (+22 more)

### Community 14 - "ExpenseCategory"
Cohesion: 0.13
Nodes (8): ExpenseCategoryController, OcrCallbackController, PrsReimbursementRequestController, ExpenseCategory, OcrCallbackService, Illuminate\Http\JsonResponse, Illuminate\Support\Facades\Validator, Illuminate\Validation\Rule

### Community 15 - "ImagePreviewModal.vue"
Cohesion: 0.11
Nodes (21): clampZoom(), close(), dragStart, emit, handleKeyDown(), handleWheel(), hasMoved, isDragging (+13 more)

### Community 18 - "index.js"
Cohesion: 0.09
Nodes (7): toasts, useToast(), app, auth, pinia, router, routes

### Community 20 - "src/views/CashAdvancesView.vue"
Cohesion: 0.12
Nodes (11): { activeStatus, statusTabs, filteredRows, activeMetrics }, { addToast }, auth, deletingRequestId, isDeleteModalOpen, kpis, router, searchedRows (+3 more)

### Community 21 - "FileUpload.vue"
Cohesion: 0.13
Nodes (22): buildPrefilledOcrData(), emit, fileInput, files, handleConfirmDrop(), hydrateEntry(), isDragging, isDropdownOpen (+14 more)

### Community 23 - "Controller"
Cohesion: 0.19
Nodes (9): App\Modules\Shared\Traits\ValidatesReceiptDuplicates, Controller, AuthController, Illuminate\Auth\Access\AuthorizationException, Illuminate\Database\Eloquent\Builder, Illuminate\Support\Facades\Bus, Illuminate\Support\Facades\DB, Illuminate\Support\Facades\Log (+1 more)

### Community 24 - "receiptUtils.js"
Cohesion: 0.18
Nodes (16): buildDefaultItems(), buildPrefilledReceiptDraft(), buildReceiptUploadFormPrefill(), canDeleteReceipt(), DELETE_FORBIDDEN_STATUSES, EDIT_FORBIDDEN_STATUSES, getItems(), itemGrossAmount() (+8 more)

### Community 25 - "src/views/CashAdvanceFormView.vue"
Cohesion: 0.11
Nodes (16): addDays(), { addToast }, fileInput, form, formatDateInputValue(), isDirty, isEditMode, isSubmitted (+8 more)

### Community 26 - "ReimbursementsView.vue"
Cohesion: 0.09
Nodes (16): { addToast }, adminReimbursementColumns, {
  approvingId,
  rejectingId,
  grantingId,
  revisionAction,
  rejectionComment,
  confirmPassword,
  isReviewSubmitting,
  openApproveModal,
  cancelApprove,
  confirmApprove,
  openRejectModal,
  cancelReject,
  confirmReject,
  openGrantModal,
  cancelGrant,
  confirmGrant,
}, auth, deletingRequestId, employeeReimbursementColumns, isDeleteModalOpen, { isMockOcr, setMockMode } (+8 more)

### Community 27 - "ReceiptDetailsModal.vue"
Cohesion: 0.11
Nodes (18): auth, canEditVatClassification, categoryName, emit, hasVatClassification, isApproveDisabled, isConfirmDecisionDisabled, isOwnSubmission (+10 more)

### Community 28 - "devDependencies"
Cohesion: 0.11
Nodes (17): devDependencies, concurrently, laravel-vite-plugin, tailwindcss, @tailwindcss/vite, vite, tailwindcss, vite (+9 more)

### Community 29 - "DashboardView.vue"
Cohesion: 0.08
Nodes (25): adminBalancePieData, adminBarData, adminCaStatusPieData, adminKpis, auth, barOptions, caStore, currentYear (+17 more)

### Community 31 - "Changelog — SERMS"
Cohesion: 0.06
Nodes (32): [0.1.0] - 2026-07-05, [1.0.0] - 2026-07-12, [1.1.0] - 2026-08-15, [1.2.0] - 2026-08-17, [1.3.0] - 2026-08-17, [1.3.1] - 2026-08-19, [1.4.0] - 2026-08-24, [1.4.1] - 2026-08-24 (+24 more)

### Community 32 - "PolicyView.vue"
Cohesion: 0.14
Nodes (10): activeTab, authStore, CATEGORIES, DEPARTMENTS, GRADES, newPenalty, newPolicy, policyStore (+2 more)

### Community 33 - "Issue Creation Guide — SERMS"
Cohesion: 0.07
Nodes (24): 1. Branching Strategy: GitFlow, 2. Branch Naming Convention, 3. Creating and Managing Your Branch, 4. Finalizing Your Work, Branch Creation Guide — SERMS, Examples, Format, Step 1: Sync your local repository (+16 more)

### Community 34 - "CashAdvanceTable.vue"
Cohesion: 0.14
Nodes (11): columns, currentPage, emit, getActions(), getSortValue(), paginatedRows, props, sortDirection (+3 more)

### Community 35 - "composer.json"
Cohesion: 0.14
Nodes (13): autoload-dev, psr-4, description, extra, laravel, dont-discover, license, minimum-stability (+5 more)

### Community 36 - "scripts"
Cohesion: 0.14
Nodes (14): scripts, dev, post-create-project-cmd, post-update-cmd, pre-package-uninstall, test, Composer\\Config::disableProcessTimeout, Illuminate\\Foundation\\ComposerScripts::prePackageUninstall (+6 more)

### Community 37 - "BaseTable.vue"
Cohesion: 0.14
Nodes (9): currentPage, emit, filtered, paginated, props, search, sortDir, sortKey (+1 more)

### Community 38 - "SegmentedReceiptUpload.vue"
Cohesion: 0.18
Nodes (10): addFiles(), canSubmit, dragOver, emit, fileInputRef, handleDrop(), handleFileSelect(), handleSubmit() (+2 more)

### Community 39 - "src/layouts/AppLayout.vue"
Cohesion: 0.14
Nodes (12): { addToast }, auth, logout(), mobileOpen, navLinks, notif, notifOpen, pageTitle (+4 more)

### Community 40 - "Illuminate\Database\Eloquent\Model"
Cohesion: 0.18
Nodes (3): CashAdvanceDisbursement, PenaltyRecord, Illuminate\Database\Eloquent\Model

### Community 41 - "CashAdvanceStatusHistory"
Cohesion: 0.13
Nodes (4): CashAdvanceApprovalAction, CashAdvanceDocument, CashAdvanceStatusHistory, CashAdvanceService

### Community 43 - "AuthenticateWithExternalService.php"
Cohesion: 0.27
Nodes (6): AuthenticateAiServiceApi, AuthenticatePrsReimbursementApi, AuthenticateWithExternalService, Closure, Illuminate\Support\Facades\Auth, Symfony\Component\HttpFoundation\Response

### Community 44 - "DecisionConfirmationModal.vue"
Cohesion: 0.19
Nodes (12): auth, config, emit, handleClose(), handleFinalConfirm(), isConfirmStep, isProceedDisabled, isRejectNextDisabled (+4 more)

### Community 45 - "MetaAndAttachments.vue"
Cohesion: 0.18
Nodes (12): { addToast }, cutoffOptions, DEFAULT_CUTOFF_OPTIONS, emit, handleReportDrop(), handleReportSelect(), isValidFile(), props (+4 more)

### Community 46 - "useReceiptUploads"
Cohesion: 0.27
Nodes (11): useReceiptUploads(), addReceiptFiles(), checkIsDuplicateResponse(), clearQualityRejection(), continueAnyway(), handleReceiptDrop(), handleReceiptSelect(), handleRemoveDuplicateEvent() (+3 more)

### Community 47 - "apiFetch"
Cohesion: 0.17
Nodes (13): useAuthStore, useCashAdvanceStore, useLiquidationStore, useNotificationStore, arrayFieldsReceipt, localStorageMock, scalarFieldsReceipt, useReceiptStore (+5 more)

### Community 50 - "UploadedFile"
Cohesion: 0.14
Nodes (4): MockOcrUploadTest, ReceiptResubmitOcrTest, ReceiptUploadTest, UploadedFile

### Community 51 - "ActionDropdownMenu.vue"
Cohesion: 0.20
Nodes (8): addPositionListeners(), buttonRef, floatingMenuRef, isOpen, menuPosition, menuRef, removePositionListeners(), updateMenuPosition()

### Community 52 - "CurrencySelect.vue"
Cohesion: 0.21
Nodes (11): currentSymbol, customCode, emit, handleCustomInput(), handleSelectChange(), isCustom, isValidCustomCode, props (+3 more)

### Community 53 - "ScannedReceiptsList.vue"
Cohesion: 0.17
Nodes (12): emit, formatTinValue(), handleTinBlur(), handleTinInput(), handleVatClassChange(), hasUploadingReceipts, props, recalculateFinancials() (+4 more)

### Community 54 - "useReimbursementDetails"
Cohesion: 0.24
Nodes (6): useReimbursementDetails(), closeDetails(), hasProcessingReceipts(), openDetails(), startPolling(), stopPolling()

### Community 55 - "mockOcr.js"
Cohesion: 0.22
Nodes (16): buildMockFileUploadEntry(), buildMockReimbursementReceipt(), formatTin(), generateMockOcrData(), KNOWN_VENDORS, LOCATION_POOL, randomDigits(), randomInt() (+8 more)

### Community 56 - "User"
Cohesion: 0.12
Nodes (7): ExpenseService, User, PenaltyLogicTest, WarnReceiptDeletionTest, Collection, Illuminate\Database\Eloquent\Factories\HasFactory, Illuminate\Foundation\Auth\User

### Community 57 - "ReceiptViewModal.vue"
Cohesion: 0.31
Nodes (8): canDelete, canEdit, close(), editReceipt(), emit, isImagePreviewOpen, normalizedReceipt, props

### Community 58 - "UNLIQUIDATED Status"
Cohesion: 0.27
Nodes (10): Penalty Computation, Aging Tracker Countdown, Balance Variance Visual System, Daily Penalty 55 PHP, LIQUIDATED Status, OVERDUE Status, Reconciliation Lifecycle, UNLIQUIDATED Status (+2 more)

### Community 62 - "BaseButton.vue"
Cohesion: 0.27
Nodes (9): cancelHold(), emit, isHolding, progress, props, sizeClass, startHold(), updateProgress() (+1 more)

### Community 63 - "BaseUtilityToolbar.vue"
Cohesion: 0.14
Nodes (13): chooseCategory(), chooseSort(), chooseStatus(), currentSortLabel, emit, hasFilters, hasSort, isOpen (+5 more)

### Community 64 - "DuplicateReceiptModal.vue"
Cohesion: 0.20
Nodes (7): { addToast }, authStore, currentUser, customMessage, duplicateReceiptId, showModal, similarityScore

### Community 65 - "LiquidationSettlementForm.vue"
Cohesion: 0.13
Nodes (12): currentStatusLabel, emit, handleRemoveReceipt(), { isMockOcr, setMockMode }, liqStore, props, reportAttachmentInput, roadmapAging (+4 more)

### Community 66 - "UnifiedRoadmapStepper.vue"
Cohesion: 0.10
Nodes (16): allSteps, currentIndex, emit, handleStepClick(), isRejectedFlow, isReviseFlow, isTerminalSuccess, isTurnConnected (+8 more)

### Community 67 - "ReimbursementDetailsModal.vue"
Cohesion: 0.22
Nodes (5): activeReceiptItems, auth, emit, isOwnSubmission, props

### Community 68 - "ReimbursementsTable.vue"
Cohesion: 0.29
Nodes (8): columnCount, emit, getActions(), handleToggleSort(), handleViewDetails(), normalizeStatus(), props, tableMinWidth

### Community 69 - "AuditLogService"
Cohesion: 0.17
Nodes (7): ComputeDailyPenalties, WarnReceiptDeletion, AuditLogService, NotificationDeliveryService, ReceiptService, Command, Illuminate\Console\Command

### Community 70 - "calculateLiquidationStatus"
Cohesion: 0.25
Nodes (8): acceptedReceiptTotal(), acceptedReviewTotal, calculateLiquidationStatus(), isPastDue(), liquidationRows, mapBackendStatusToDisplayStatus(), reviewStatus, sourceCases

### Community 71 - "Governance and Security"
Cohesion: 0.22
Nodes (9): Client-Side Pre-Encryption, Append-Only Penalty Records, Audit Log Immutability, BIR Compliance, VAT Classification, useNotificationStore, BIR Compliance, Governance and Security (+1 more)

### Community 73 - "OCRExtractedFields.vue"
Cohesion: 0.39
Nodes (8): emit, formatTin(), localAmount, localDate, localTin, localVat, localVendor, props

### Community 74 - "useLiquidationDecisions"
Cohesion: 0.33
Nodes (8): useLiquidationDecisions(), cancelApprove(), cancelReject(), confirmApprove(), confirmReject(), isReviewingOwnLiquidation(), openApproveModal(), openRejectModal()

### Community 75 - "useReimbursementDecisions"
Cohesion: 0.24
Nodes (12): useReimbursementDecisions(), cancelApprove(), cancelGrant(), cancelReject(), confirmApprove(), confirmGrant(), confirmReject(), isOwnSubmission() (+4 more)

### Community 76 - "Pull Request Template"
Cohesion: 0.25
Nodes (8): A-09 Reusability Constraint, AuditLogService log, Reusability and Anti-Duplication Rule, A-09 Reusability Rule, AuditLogService log, BIR VAT Compliance Rules, Conventional Commits, Pull Request Template

### Community 77 - "SERMS Overview"
Cohesion: 0.29
Nodes (8): Canonical Source of Truth, Antigravity AI Engine, Modular Monolith Architecture, Pre-Aggregated Analytics, SERMS System Scope, Modular Monolith Architecture, Pre-Aggregated Analytics, SERMS Overview

### Community 78 - "OCR Workflow"
Cohesion: 0.25
Nodes (8): AI Expense Categorization, Cutoff Validation, Duplicate Detection, OCR Confidence Threshold, OCR Workflow, Supabase Bucket Storage, useReimbursementStore, Reimbursement Module

### Community 79 - "DatabaseSeeder"
Cohesion: 0.36
Nodes (4): ExpenseCategorySeeder, DatabaseSeeder, Illuminate\Database\Console\Seeds\WithoutModelEvents, Illuminate\Database\Seeder

### Community 80 - "require"
Cohesion: 0.25
Nodes (8): require, firebase/php-jwt, laravel/framework, laravel/tinker, league/flysystem-aws-s3-v3, php, predis/predis, thiagoalessio/tesseract_ocr

### Community 81 - "require-dev"
Cohesion: 0.25
Nodes (8): require-dev, fakerphp/faker, laravel/pail, laravel/pao, laravel/pint, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 82 - "setup"
Cohesion: 0.25
Nodes (8): post-root-package-install, setup, composer install, npm install --ignore-scripts, npm run build, @php artisan key:generate, @php artisan migrate --force, @php -r \"file_exists('.env') || copy('.env.example', '.env');\

### Community 83 - "PHP-FPM Service"
Cohesion: 0.25
Nodes (8): Docker Entrypoint Script, Laravel Framework, Nginx API Service, OCR Pipeline Service, PHP-FPM Service, PRS Integration Endpoint, Redis Service, Shared Capstone Network

### Community 84 - "DeleteConfirmModal.vue"
Cohesion: 0.32
Nodes (7): auth, close(), emit, handleConfirm(), password, props, showPassword

### Community 85 - "src/views/admin/ReportsView.vue"
Cohesion: 0.25
Nodes (6): generating, lineData, lineOptions, periods, reports, selectedPeriod

### Community 86 - "Queue Worker Service"
Cohesion: 0.33
Nodes (7): Laravel Queues, main.js Entry Point, SERMS App Mount Point, Queue Worker Service, MySQL Service, Web Frontend Service, Worker Service

### Community 87 - "config"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 89 - "BasePagination.vue"
Cohesion: 0.33
Nodes (6): emit, end, props, setPage(), start, totalPages

### Community 90 - "ReceiptQualityRejectionModal.vue"
Cohesion: 0.29
Nodes (6): badgeLabel, emit, isDuplicate, previewUrl, props, tips

### Community 91 - "useReimbursementFilters"
Cohesion: 0.46
Nodes (5): getCutoffPeriod(), getSortValue(), normalizeStatus(), statusLabel(), useReimbursementFilters()

### Community 92 - "formatters.js"
Cohesion: 0.29
Nodes (7): formatAmount(), formatCutoffPeriod(), formatDate(), formatKpiValue(), formatPeso(), MONTH_NAMES_SHORT, SUPPORTED_CURRENCIES

### Community 93 - "Hold-to-Confirm Pattern"
Cohesion: 0.33
Nodes (6): Hold-to-Confirm Pattern, Hold-to-Confirm Pattern, Immediate Validation Feedback, FileUpload.vue Component, Hold-to-Confirm Pattern, Hold-to-Confirm Pattern

### Community 96 - "Clinical Neutral Palette"
Cohesion: 0.33
Nodes (6): Blueprint Grid System, Readout Typography, Clinical Neutral Palette, Engineered Interface Philosophy, Instrument Card Component, Liquidation Console

### Community 97 - "BaseModal.vue"
Cohesion: 0.40
Nodes (4): emit, handleKeydown(), props, emit

### Community 98 - "BaseWarningBanner.vue"
Cohesion: 0.40
Nodes (5): dismiss(), emit, isVisible, props, themeConfig

### Community 100 - "OCRField.vue"
Cohesion: 0.40
Nodes (5): emit, isMono, onInput(), props, requiresReview

### Community 101 - "Illuminate\Foundation\Http\FormRequest"
Cohesion: 0.19
Nodes (4): AcknowledgeCashAdvanceRequest, ApproveCashAdvanceRequest, DisburseCashAdvanceRequest, Illuminate\Foundation\Http\FormRequest

### Community 102 - "ExpenseCard.vue"
Cohesion: 0.40
Nodes (4): canDelete, canEdit, isMenuOpen, props

### Community 103 - "crypto.js"
Cohesion: 0.60
Nodes (5): arrayBufferToBase64(), base64ToArrayBuffer(), encryptPayload(), fetchServerPublicKey(), pemToDer()

### Community 104 - "reimbursementForwarding.js"
Cohesion: 0.53
Nodes (5): canForwardToReimbursement(), getForwardingBlockReason(), mapReceiptToReimbursement(), normalizeItems(), normalizeReceiptStatus()

### Community 105 - "Debug Session Workflow"
Cohesion: 0.70
Nodes (5): Debug Session Workflow, Diagnose Phase, Fix Phase, Isolate Phase, Reproduce Phase

### Community 106 - "AI Prompt Engineering Safety Review"
Cohesion: 0.40
Nodes (5): Bias Detection, Effectiveness Evaluation, AI Prompt Engineering Safety Review, Safety Assessment, Security and Privacy Assessment

### Community 107 - "bootstrap/app.php"
Cohesion: 0.40
Nodes (3): Illuminate\Foundation\Application, Illuminate\Foundation\Configuration\Exceptions, Illuminate\Foundation\Configuration\Middleware

### Community 108 - "psr-4"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 110 - "logging.php"
Cohesion: 0.40
Nodes (4): Monolog\Handler\NullHandler, Monolog\Handler\StreamHandler, Monolog\Handler\SyslogUdpHandler, Monolog\Processor\PsrLogMessageProcessor

### Community 111 - "BaseInput.vue"
Cohesion: 0.50
Nodes (4): emit, isMono, onInput(), props

### Community 112 - "useNavLinks.js"
Cohesion: 0.50
Nodes (3): base, buildNavLinks(), employee

### Community 114 - "policy.js"
Cohesion: 0.40
Nodes (4): MOCK_EXPENSE_LIMITS, MOCK_PENALTY_RULES, MOCK_POLICY_LOGS, usePolicyStore

### Community 115 - "employeeAdvanceStatus"
Cohesion: 0.50
Nodes (5): employeeAdvanceBadgeStatus(), employeeAdvanceStatus(), employeeFilteredAdvances, employeeLiquidationKpis, employeeSortValue()

### Community 116 - "File Structure"
Cohesion: 0.20
Nodes (9): File Structure, Global Constraints, Liquidation OCR Pipeline Integration Implementation Plan, Self-Review, Task 1: Backend — Refactor LiquidationController::scan to Async OCR, Task 2: Backend — Add Liquidation OCR Callback Controller (if needed for decoupling), Task 3: Frontend — Enhance FileUpload.vue to Call Real OCR with Polling, Task 4: Frontend — Wire LiquidationsView & ScannedReceiptsList to Show Async States (+1 more)

### Community 119 - "SBSI Logo"
Cohesion: 0.83
Nodes (4): SBSI Logo, SBSI Logo Short, Logo, SBSI Brand

### Community 120 - "NotificationPanel.vue"
Cohesion: 0.50
Nodes (3): iconMap, store, unread

### Community 123 - "LiquidationAdvancesList.vue"
Cohesion: 0.29
Nodes (6): currentPage, emit, handleSortClick(), paginatedAdvances, props, totalPages

### Community 125 - "src/views/admin/AuditView.vue"
Cohesion: 0.50
Nodes (3): ACTION_MAP, auditColumns, MOCK_LOGS

### Community 126 - "src/views/AuthCallbackView.vue"
Cohesion: 0.50
Nodes (3): auth, route, router

### Community 127 - "opencode.json"
Cohesion: 0.50
Nodes (3): plugin, $schema, .opencode/plugins/graphify.js

### Community 131 - ".log"
Cohesion: 0.13
Nodes (7): App\Modules\Ai\Contracts\AsyncOcrEngineInterface, AiServiceException, Throwable, AiServiceOcrEngine, Throwable, ReceiptStatusObserver, RuntimeException

### Community 133 - "ReceiptDuplicateDetected"
Cohesion: 0.27
Nodes (6): ReceiptDuplicateDetected, Illuminate\Broadcasting\Channel, Illuminate\Broadcasting\InteractsWithSockets, Illuminate\Broadcasting\PrivateChannel, Illuminate\Contracts\Broadcasting\ShouldBroadcast, Illuminate\Foundation\Events\Dispatchable

### Community 134 - "post-autoload-dump"
Cohesion: 0.67
Nodes (3): post-autoload-dump, Illuminate\\Foundation\\ComposerScripts::postAutoloadDump, @php artisan package:discover --ansi

### Community 135 - "useOcrMode"
Cohesion: 0.36
Nodes (6): readStored(), shared, useOcrMode(), setMockMode(), toggleMockMode(), writeStored()

### Community 139 - "TesseractOcrEngine.php"
Cohesion: 0.40
Nodes (3): App\Modules\Ai\Contracts\OcrEngineInterface, TesseractOcrEngine, thiagoalessio\TesseractOCR\TesseractOCR

### Community 147 - "Reimbursement"
Cohesion: 0.15
Nodes (4): Reimbursement, ReimbursementService, PasswordVerificationService, ReimbursementLogicTest

### Community 152 - "BaseToggleSwitch.vue"
Cohesion: 0.40
Nodes (4): emit, isOn, props, toggle()

### Community 155 - "AppLayout Component"
Cohesion: 1.00
Nodes (3): AppLayout Component, AuthLayout Component, useAuthStore

### Community 160 - "resetLiquidationComposer"
Cohesion: 0.67
Nodes (3): closeAdminRequestForm(), openAdminRequestForm(), resetLiquidationComposer()

### Community 180 - "getActions"
Cohesion: 0.33
Nodes (6): categoryName(), getActions(), handleDeleteLiquidationForRow(), handleEditLiquidation(), openReview(), selectAdvance()

### Community 197 - "BaseReceiptImage.vue"
Cohesion: 0.24
Nodes (7): emit, handleImageError(), handleImageLoad(), imageError, isPdf, props, shouldShowImage

### Community 199 - "LiquidationReceiptModal.vue"
Cohesion: 0.50
Nodes (3): emit, normalizedReceipt, props

### Community 201 - "LiquidationReviewModal.vue"
Cohesion: 0.20
Nodes (10): emit, handleRoadmapNavigate(), liqStore, props, roadmapAging, roadmapCashAdvance, roadmapHistory, roadmapLiquidation (+2 more)

### Community 208 - "Mock Receipt PNG Image"
Cohesion: 0.25
Nodes (8): Mock Receipt PNG Image, Line Items Values, Merchant Name Value, Payment Method Value, Purpose Value, Receipt Date Value, Total Amount Value, VAT Amount Value

### Community 210 - "RoadmapStepItem.vue"
Cohesion: 0.38
Nodes (4): emit, handleClick(), isClickable(), props

### Community 211 - "normalize"
Cohesion: 0.32
Nodes (8): caStatus, formatActorName(), isOverdue, liqStatus, normalize(), resolveHistoryEntry(), resolveStepHistory(), isFormDisabled

### Community 213 - "Illuminate\Http\Request"
Cohesion: 0.21
Nodes (4): ExpenseController, PrsWebhookController, ReceiptController, Illuminate\Http\Request

### Community 215 - "BaseReceiptDetailModal.vue"
Cohesion: 0.40
Nodes (4): emit, hasItems, props, subtotal

### Community 217 - "closeDetails"
Cohesion: 0.40
Nodes (5): closeDetails(), confirmAcknowledge(), confirmAdminDecision(), emit, handleRoadmapNavigate()

### Community 228 - "Role-Based Access Control"
Cohesion: 0.50
Nodes (4): HTTP Error Codes 401 403 409, Role-Based Access Control, Session Timeout Policy, Role-Based Access Control

### Community 236 - "drawSignature"
Cohesion: 0.67
Nodes (4): drawSignature(), prepareSignatureCanvas(), signaturePoint(), startSignature()

### Community 245 - "normalizeStatus"
Cohesion: 0.67
Nodes (3): isTimelineStepCompleted(), isTimelineStepCurrent(), normalizeStatus()

### Community 251 - "console.php"
Cohesion: 0.50
Nodes (3): Illuminate\Foundation\Inspiring, Illuminate\Support\Facades\Artisan, Illuminate\Support\Facades\Schedule

### Community 258 - "keywords"
Cohesion: 0.67
Nodes (3): keywords, framework, laravel

## Ambiguous Edges - Review These
- `Meals (service category) - unclear if matches line item` → `Receipt 3 - Yamachan Japanese Restaurant`  [AMBIGUOUS]
  docs/receipts/receipt 3.jpg · relation: has_value
- `~22,497.60 (OCR ambiguous)` → `Receipt 3 - Yamachan Japanese Restaurant`  [AMBIGUOUS]
  docs/receipts/receipt 3.jpg · relation: has_value
- `Line Items Values` → `Mock Receipt PNG Image`  [AMBIGUOUS]
  apps/web/public/mock_receipt.png · relation: may_contain
- `Merchant Name Value` → `Mock Receipt PNG Image`  [AMBIGUOUS]
  apps/web/public/mock_receipt.png · relation: may_contain
- `Payment Method Value` → `Mock Receipt PNG Image`  [AMBIGUOUS]
  apps/web/public/mock_receipt.png · relation: may_contain
- `Purpose Value` → `Mock Receipt PNG Image`  [AMBIGUOUS]
  apps/web/public/mock_receipt.png · relation: may_contain
- `Receipt Date Value` → `Mock Receipt PNG Image`  [AMBIGUOUS]
  apps/web/public/mock_receipt.png · relation: may_contain
- `Total Amount Value` → `Mock Receipt PNG Image`  [AMBIGUOUS]
  apps/web/public/mock_receipt.png · relation: may_contain
- `VAT Amount Value` → `Mock Receipt PNG Image`  [AMBIGUOUS]
  apps/web/public/mock_receipt.png · relation: may_contain

## Knowledge Gaps
- **673 isolated node(s):** `$schema`, `.opencode/plugins/graphify.js`, `$schema`, `name`, `type` (+668 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **67 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **What is the exact relationship between `Meals (service category) - unclear if matches line item` and `Receipt 3 - Yamachan Japanese Restaurant`?**
  _Edge tagged AMBIGUOUS (relation: has_value) - confidence is low._
- **What is the exact relationship between `~22,497.60 (OCR ambiguous)` and `Receipt 3 - Yamachan Japanese Restaurant`?**
  _Edge tagged AMBIGUOUS (relation: has_value) - confidence is low._
- **What is the exact relationship between `Line Items Values` and `Mock Receipt PNG Image`?**
  _Edge tagged AMBIGUOUS (relation: may_contain) - confidence is low._
- **What is the exact relationship between `Merchant Name Value` and `Mock Receipt PNG Image`?**
  _Edge tagged AMBIGUOUS (relation: may_contain) - confidence is low._
- **What is the exact relationship between `Payment Method Value` and `Mock Receipt PNG Image`?**
  _Edge tagged AMBIGUOUS (relation: may_contain) - confidence is low._
- **What is the exact relationship between `Purpose Value` and `Mock Receipt PNG Image`?**
  _Edge tagged AMBIGUOUS (relation: may_contain) - confidence is low._
- **What is the exact relationship between `Receipt Date Value` and `Mock Receipt PNG Image`?**
  _Edge tagged AMBIGUOUS (relation: may_contain) - confidence is low._