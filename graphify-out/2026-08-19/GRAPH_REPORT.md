# Graph Report - smart-expense-management-system  (2026-08-18)

## Corpus Check
- 308 files · ~166,672 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 2020 nodes · 3121 edges · 209 communities (167 shown, 42 thin omitted)
- Extraction: 94% EXTRACTED · 6% INFERRED · 0% AMBIGUOUS · INFERRED: 185 edges (avg confidence: 0.77)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- Community 118
- CashAdvance Actions & Approvals
- Reimbursement Controller
- General Controllers
- Form Request Validation
- Queue Jobs & Workers
- Receipt Item Relations
- Laravel Service Providers
- Password Verification
- Expense Controller
- Audit Log Models
- AI Module Contracts & Events
- AI & PRS Auth Services
- Receipt Controller
- Receipt Owner Update Tests
- Receipt Upload Tests
- Example Test Scaffold
- Receipt Filtering Tests
- OCR Callback Controller
- CashAdvance Controller
- OCR Callback Tests
- Receipt Resubmit OCR Tests
- AI Contracts & Liquidation API
- Reimbursement Service
- Database Seeders
- PRS Webhook Tests
- CashAdvance Policy & Traits
- PRS Reimbursement API Tests
- Liquidations View Components
- OCR Field Vue Component
- Rejection Modal Vue
- Crypto JS Utils
- Reimbursement Forwarding JS
- Receipt Upload Modal Vue
- Base Input Vue
- Navigation Links Composable
- Unsaved Changes Composable
- Employee Dashboard Composable
- Community 121
- Community 122
- Community 123
- Community 124
- Reimbursement Form View
- Image Preview Modal Vue
- Community 156
- Community 157
- Community 158
- Community 159
- Community 160
- Community 161
- Community 177
- Community 178
- Community 179
- App Bootstrap & Entry
- Community 180
- Community 181
- Community 182
- Cash Advances View
- File Upload Component
- Cash Advances Migrations
- Receipt Utils JS
- CashAdvance Form View
- Reimbursements View
- Dashboard View Components
- Admin Policy View
- CashAdvance Table Vue
- Base Table Vue
- Segmented Receipt Upload
- App Layout Vue
- Cash Advances Migrations (earlier)
- Cash Advances Migrations
- Reimbursements Migrations
- Meta & Attachments Modal
- Receipt Uploads Composable
- Notification & Receipts Stores
- My Expense View Components
- Action Dropdown Menu
- Currency Select Vue
- Scanned Receipts List
- Reimbursement Details Composable
- Receipt View Modal Vue
- Base Button Vue
- Base Utility Toolbar
- Duplicate Receipt Modal
- Liquidation Settlement Form
- Decision Confirmation Modal
- Reimbursement Details Modal
- Reimbursements Table Vue
- Pinia Stores Composable
- Liquidation Calculations
- OCR Extracted Fields Vue
- Liquidation Decisions Composable
- Reimbursement Decisions Composable
- CashAdvance Details Modal
- Delete Confirm Modal
- Reports View Vue
- Base Pagination Vue
- Reimbursement Filters Composable
- Formatters JS Utils
- Base Modal Vue
- Base Warning Banner Vue
- Image Preview Tests
- Architecture & Governance
- Frontend Package Dependencies
- Expense Card Vue
- Laravel Bootstrap
- PSR-4 Autoload Mappings
- App Config Files
- Logging Config
- Policy Store Composable
- Community 117
- Community 120
- Community 125
- Community 126
- Community 127
- Community 147
- Community 148
- Community 167
- Community 169
- Community 170
- Community 171
- Community 172
- Community 174
- Community 176
- Receipt Details Modal
- API Dev Dependencies
- Composer Non-Dev Deps
- Composer Scripts
- Feature & Unit Tests
- Composer Dependencies
- Composer Dev Dependencies
- Setup Scripts
- Composer Config Allow Plugins
- Receipt Quality Rejection Modal
- Module Route Files
- Debug Session Workflow
- AI Prompt Safety Review
- Community 116
- Community 119
- Community 155
- Community 162
- Community 163
- Community 164
- Community 183
- Receipt OCR Extracted Fields
- Community 207
- Penalty Computation Rationale
- Compliance & Immutability Rules
- Reusability Rules
- Core Architecture Concepts
- OCR Workflow Rules
- Docker Compose Services
- Queue Workers & Services
- Hold-to-Confirm Pattern
- Design System Rationale

## God Nodes (most connected - your core abstractions)
1. `User` - 97 edges
2. `Receipt` - 90 edges
3. `CashAdvance` - 49 edges
4. `SERMS Master Specification` - 47 edges
5. `System Design Document` - 42 edges
6. `Reimbursement` - 37 edges
7. `TestCase` - 34 edges
8. `Controller` - 23 edges
9. `Build Guide` - 23 edges
10. `ExpenseCategory` - 21 edges

## Surprising Connections (you probably didn't know these)
- `pinia` --implements--> `Vue 3 SPA`  [INFERRED]
  apps/web/package.json → docs/SERMS.md
- `Role-Based Access Control` --semantically_similar_to--> `Role-Based Access Control`  [INFERRED] [semantically similar]
  README.md → .agents/rules/smart-expense-reimbursement-management-system.md
- `BaseButton.vue Component` --semantically_similar_to--> `BaseButton.vue Component`  [INFERRED] [semantically similar]
  AGENTS.md → apps/web/docs/technical_spec.md
- `StatusBadge.vue Component` --semantically_similar_to--> `StatusBadge.vue Component`  [INFERRED] [semantically similar]
  AGENTS.md → apps/web/docs/technical_spec.md
- `BIR Compliance` --semantically_similar_to--> `BIR Compliance`  [INFERRED] [semantically similar]
  README.md → .agents/rules/smart-expense-reimbursement-management-system.md

## Import Cycles
- None detected.

## Communities (209 total, 42 thin omitted)

### Community 12 - "CashAdvance Actions & Approvals"
Cohesion: 0.08
Nodes (9): CashAdvanceApprovalAction, CashAdvanceDisbursement, CashAdvanceDocument, CashAdvanceStatusHistory, PenaltyRecord, duplicateReceiptExists(), validateDuplicateReceipt(), Illuminate\Database\Eloquent\Model (+1 more)

### Community 14 - "Reimbursement Controller"
Cohesion: 0.09
Nodes (6): ReimbursementController, ApproveReimbursementRequest, RejectReimbursementRequest, StoreReimbursementRequest, UpdateReimbursementRequest, Illuminate\Auth\Access\AuthorizationException

### Community 16 - "General Controllers"
Cohesion: 0.13
Nodes (9): Controller, ExpenseCategoryController, PrsReimbursementRequestController, PrsWebhookController, ExpenseCategory, CryptoController, Illuminate\Http\JsonResponse, Illuminate\Support\Facades\Validator (+1 more)

### Community 17 - "Form Request Validation"
Cohesion: 0.10
Nodes (5): StoreCashAdvanceRequest, ResubmitReceiptRequest, StoreReceiptRequest, UpdateReceiptRequest, Illuminate\Foundation\Http\FormRequest

### Community 19 - "Queue Jobs & Workers"
Cohesion: 0.15
Nodes (7): ComputeDailyPenalties, WarnReceiptDeletion, AuditLogService, NotificationDeliveryService, ReceiptService, Command, Illuminate\Console\Command

### Community 23 - "Receipt Item Relations"
Cohesion: 0.12
Nodes (4): ReceiptItem, Reimbursement, ReimbursementLogicTest, Illuminate\Support\Facades\Gate

### Community 3 - "Laravel Service Providers"
Cohesion: 0.05
Nodes (13): AiServiceProvider, AuditLogsServiceProvider, CashAdvancesServiceProvider, ExpensesServiceProvider, LiquidationsServiceProvider, NotificationsServiceProvider, ReimbursementsServiceProvider, SharedServiceProvider (+5 more)

### Community 31 - "Expense Controller"
Cohesion: 0.14
Nodes (3): ExpenseController, StoreReceiptRequest, UpdateReceiptRequest

### Community 33 - "Audit Log Models"
Cohesion: 0.15
Nodes (3): AuditLog, ReimbursementPasswordVerificationTest, WarnReceiptDeletionTest

### Community 4 - "AI Module Contracts & Events"
Cohesion: 0.07
Nodes (24): ReceiptDuplicateDetected, AiServiceException, AiServiceOcrEngine, TesseractOcrEngine, DispatchReceiptToAiService, UpdatePrsReimbursementStatusJob, App\Modules\Ai\Contracts\AsyncOcrEngineInterface, Throwable (+16 more)

### Community 43 - "AI & PRS Auth Services"
Cohesion: 0.27
Nodes (6): AuthenticateAiServiceApi, AuthenticatePrsReimbursementApi, AuthenticateWithExternalService, Closure, Illuminate\Support\Facades\Auth, Symfony\Component\HttpFoundation\Response

### Community 48 - "Receipt Controller"
Cohesion: 0.27
Nodes (3): ReceiptController, AuthController, Illuminate\Http\Request

### Community 55 - "Example Test Scaffold"
Cohesion: 0.20
Nodes (3): ExampleTest, TestCase, Illuminate\Foundation\Testing\TestCase

### Community 59 - "OCR Callback Controller"
Cohesion: 0.27
Nodes (3): OcrCallbackController, OcrCallbackRequest, OcrCallbackService

### Community 6 - "CashAdvance Controller"
Cohesion: 0.07
Nodes (8): CashAdvanceController, AcknowledgeCashAdvanceRequest, ApproveCashAdvanceRequest, DisburseCashAdvanceRequest, RejectCashAdvanceRequest, UpdateCashAdvanceRequest, CashAdvance, CashAdvanceService

### Community 7 - "AI Contracts & Liquidation API"
Cohesion: 0.08
Nodes (8): LiquidationController, Liquidation, Receipt, ReceiptStatusObserver, LiquidationLogicTest, ReceiptStorageRetrievalTest, App\Modules\Ai\Contracts\OcrEngineInterface, Illuminate\Database\Eloquent\SoftDeletes

### Community 79 - "Database Seeders"
Cohesion: 0.36
Nodes (4): ExpenseCategorySeeder, DatabaseSeeder, Illuminate\Database\Console\Seeds\WithoutModelEvents, Illuminate\Database\Seeder

### Community 9 - "CashAdvance Policy & Traits"
Cohesion: 0.08
Nodes (12): CashAdvancePolicy, ExpenseService, User, UserFactory, PenaltyLogicTest, App\Modules\Shared\Traits\ValidatesReceiptDuplicates, Collection, Illuminate\Auth\Access\HandlesAuthorization (+4 more)

### Community 1 - "Liquidations View Components"
Cohesion: 0.03
Nodes (61): activeDraft, activeStatus, { addToast }, agingInfo, {
  approvingId,
  rejectingId,
  confirmPassword,
  rejectionComment,
  isReviewSubmitting,
  openApproveModal,
  openRejectModal,
  cancelApprove,
  cancelReject,
  confirmApprove,
  confirmReject,
}, auth, calculatedOutstandingBalance, confirmFinalizeOpen (+53 more)

### Community 100 - "OCR Field Vue Component"
Cohesion: 0.40
Nodes (5): onInput(), emit, isMono, props, requiresReview

### Community 101 - "Rejection Modal Vue"
Cohesion: 0.47
Nodes (5): handleCancel(), handleConfirm(), emit, props, rejectionComment

### Community 103 - "Crypto JS Utils"
Cohesion: 0.60
Nodes (5): arrayBufferToBase64(), base64ToArrayBuffer(), encryptPayload(), fetchServerPublicKey(), pemToDer()

### Community 104 - "Reimbursement Forwarding JS"
Cohesion: 0.53
Nodes (5): canForwardToReimbursement(), getForwardingBlockReason(), mapReceiptToReimbursement(), normalizeItems(), normalizeReceiptStatus()

### Community 11 - "Receipt Upload Modal Vue"
Cohesion: 0.07
Nodes (32): buildUpdatePayload(), close(), handleRetryOcr(), notify(), resetUploadForm(), saveNewReceipt(), saveReceipt(), startOcrPolling() (+24 more)

### Community 111 - "Base Input Vue"
Cohesion: 0.50
Nodes (4): onInput(), emit, isMono, props

### Community 112 - "Navigation Links Composable"
Cohesion: 0.50
Nodes (3): buildNavLinks(), base, employee

### Community 115 - "Employee Dashboard Composable"
Cohesion: 0.50
Nodes (5): employeeAdvanceBadgeStatus(), employeeAdvanceStatus(), employeeSortValue(), employeeFilteredAdvances, employeeLiquidationKpis

### Community 123 - "Community 123"
Cohesion: 0.67
Nodes (3): handleSortClick(), emit, props

### Community 13 - "Reimbursement Form View"
Cohesion: 0.06
Nodes (29): dismiss(), { addToast }, canProceed, cutoffPeriod, emit, fetching, forwardedReceiptCount, forwardedSource (+21 more)

### Community 15 - "Image Preview Modal Vue"
Cohesion: 0.11
Nodes (21): clampZoom(), close(), handleKeyDown(), handleWheel(), resetZoom(), toggleZoomClick(), zoomIn(), zoomOut() (+13 more)

### Community 159 - "Community 159"
Cohesion: 0.67
Nodes (3): openRejectModal(), confirmReceiptDecision(), setReceiptDecision()

### Community 160 - "Community 160"
Cohesion: 0.67
Nodes (3): closeAdminRequestForm(), openAdminRequestForm(), resetLiquidationComposer()

### Community 18 - "App Bootstrap & Entry"
Cohesion: 0.09
Nodes (7): useToast(), toasts, app, auth, pinia, router, routes

### Community 20 - "Cash Advances View"
Cohesion: 0.09
Nodes (15): cancelReject(), confirmReject(), { activeStatus, statusTabs, filteredRows, activeMetrics }, { addToast }, auth, deletingRequestId, isDeleteModalOpen, kpis (+7 more)

### Community 21 - "File Upload Component"
Cohesion: 0.13
Nodes (18): buildPrefilledOcrData(), handleConfirmDrop(), onDrop(), onFileInput(), processFiles(), removeFile(), simulateOCR(), emit (+10 more)

### Community 22 - "Cash Advances Migrations"
Cohesion: 0.10
Nodes (3): normalizeLegacyScalarValues(), up(), Illuminate\Support\Facades\DB

### Community 24 - "Receipt Utils JS"
Cohesion: 0.17
Nodes (19): buildDefaultItems(), buildPrefilledReceiptDraft(), buildReceiptUploadFormPrefill(), canDeleteReceipt(), cleanName(), formatDateForInput(), getItems(), itemGrossAmount() (+11 more)

### Community 25 - "CashAdvance Form View"
Cohesion: 0.11
Nodes (16): addDays(), formatDateInputValue(), parseDateInputValue(), { addToast }, fileInput, form, isDirty, isEditMode (+8 more)

### Community 26 - "Reimbursements View"
Cohesion: 0.10
Nodes (15): { addToast }, adminReimbursementColumns, {
  approvingId,
  rejectingId,
  rejectionComment,
  confirmPassword,
  isReviewSubmitting,
  openApproveModal,
  cancelApprove,
  confirmApprove,
  openRejectModal,
  cancelReject,
  confirmReject,
}, auth, deletingRequestId, employeeReimbursementColumns, isDeleteModalOpen, newRequestFileInput (+7 more)

### Community 29 - "Dashboard View Components"
Cohesion: 0.12
Nodes (14): activeAdvances, auth, barData, barOptions, caStore, cutoffDays, cutoffHours, doughnutData (+6 more)

### Community 32 - "Admin Policy View"
Cohesion: 0.12
Nodes (12): formatDate(), activeTab, authStore, CATEGORIES, DEPARTMENTS, GRADES, newPenalty, newPolicy (+4 more)

### Community 34 - "CashAdvance Table Vue"
Cohesion: 0.15
Nodes (11): getActions(), getSortValue(), columns, currentPage, emit, paginatedRows, props, sortDirection (+3 more)

### Community 37 - "Base Table Vue"
Cohesion: 0.14
Nodes (9): currentPage, emit, filtered, paginated, props, search, sortDir, sortKey (+1 more)

### Community 38 - "Segmented Receipt Upload"
Cohesion: 0.18
Nodes (10): addFiles(), handleDrop(), handleFileSelect(), handleSubmit(), canSubmit, dragOver, emit, fileInputRef (+2 more)

### Community 39 - "App Layout Vue"
Cohesion: 0.14
Nodes (12): logout(), { addToast }, auth, mobileOpen, navLinks, notif, notifOpen, pageTitle (+4 more)

### Community 45 - "Meta & Attachments Modal"
Cohesion: 0.22
Nodes (10): handleReportDrop(), handleReportSelect(), isValidFile(), removeReportFile(), { addToast }, CUTOFF_OPTIONS, emit, reportDrag (+2 more)

### Community 46 - "Receipt Uploads Composable"
Cohesion: 0.27
Nodes (11): useReceiptUploads(), addReceiptFiles(), checkIsDuplicateResponse(), clearQualityRejection(), continueAnyway(), handleReceiptDrop(), handleReceiptSelect(), handleRemoveDuplicateEvent() (+3 more)

### Community 47 - "Notification & Receipts Stores"
Cohesion: 0.24
Nodes (8): getFileUrl(), canEditReceipt(), firstFilePathField(), useNotificationStore, arrayFieldsReceipt, localStorageMock, scalarFieldsReceipt, useReceiptStore

### Community 5 - "My Expense View Components"
Cohesion: 0.05
Nodes (36): confirmDelete(), ensureOcrWatch(), fetchReceipts(), forwardReceipt(), forwardReceipts(), forwardSelected(), normalizeFilterLabel(), onExpenseSaved() (+28 more)

### Community 51 - "Action Dropdown Menu"
Cohesion: 0.20
Nodes (8): addPositionListeners(), removePositionListeners(), updateMenuPosition(), buttonRef, floatingMenuRef, isOpen, menuPosition, menuRef

### Community 52 - "Currency Select Vue"
Cohesion: 0.21
Nodes (11): handleCustomInput(), handleSelectChange(), switchToSelect(), currentSymbol, customCode, emit, isCustom, isValidCustomCode (+3 more)

### Community 53 - "Scanned Receipts List"
Cohesion: 0.21
Nodes (6): handleVatClassChange(), recalculateFinancials(), recalculateFromItems(), removeReceiptItem(), hasUploadingReceipts, props

### Community 54 - "Reimbursement Details Composable"
Cohesion: 0.24
Nodes (6): useReimbursementDetails(), closeDetails(), hasProcessingReceipts(), openDetails(), startPolling(), stopPolling()

### Community 57 - "Receipt View Modal Vue"
Cohesion: 0.24
Nodes (9): close(), editReceipt(), actualSubtotal, canDelete, canEdit, emit, imageError, isImagePreviewOpen (+1 more)

### Community 62 - "Base Button Vue"
Cohesion: 0.27
Nodes (9): cancelHold(), startHold(), updateProgress(), emit, isHolding, progress, props, sizeClass (+1 more)

### Community 63 - "Base Utility Toolbar"
Cohesion: 0.24
Nodes (8): chooseCategory(), chooseStatus(), emit, hasFilters, isOpen, popoverWidthClass, props, root

### Community 64 - "Duplicate Receipt Modal"
Cohesion: 0.20
Nodes (7): { addToast }, authStore, currentUser, customMessage, duplicateReceiptId, showModal, similarityScore

### Community 65 - "Liquidation Settlement Form"
Cohesion: 0.24
Nodes (6): formatTinValue(), handleTinBlur(), handleTinInput(), emit, props, reportAttachmentInput

### Community 66 - "Decision Confirmation Modal"
Cohesion: 0.29
Nodes (9): handleClose(), handleConfirm(), auth, config, emit, localComment, localPassword, props (+1 more)

### Community 67 - "Reimbursement Details Modal"
Cohesion: 0.22
Nodes (7): normalizeStatus(), statusLabel(), activeReceiptItems, auth, emit, isOwnSubmission, props

### Community 68 - "Reimbursements Table Vue"
Cohesion: 0.29
Nodes (8): getActions(), handleToggleSort(), handleViewDetails(), normalizeStatus(), columnCount, emit, props, tableMinWidth

### Community 69 - "Pinia Stores Composable"
Cohesion: 0.42
Nodes (5): apiFetch(), useAuthStore, useCashAdvanceStore, useLiquidationStore, useReimbursementStore

### Community 70 - "Liquidation Calculations"
Cohesion: 0.20
Nodes (10): acceptedReceiptTotal(), calculateLiquidationStatus(), categoryName(), isPastDue(), mapBackendStatusToDisplayStatus(), selectAdvance(), acceptedReviewTotal, liquidationRows (+2 more)

### Community 73 - "OCR Extracted Fields Vue"
Cohesion: 0.39
Nodes (8): formatTin(), emit, localAmount, localDate, localTin, localVat, localVendor, props

### Community 74 - "Liquidation Decisions Composable"
Cohesion: 0.33
Nodes (8): useLiquidationDecisions(), cancelApprove(), cancelReject(), confirmApprove(), confirmReject(), isReviewingOwnLiquidation(), openApproveModal(), openRejectModal()

### Community 75 - "Reimbursement Decisions Composable"
Cohesion: 0.33
Nodes (8): useReimbursementDecisions(), cancelApprove(), cancelReject(), confirmApprove(), confirmReject(), isOwnSubmission(), openApproveModal(), openRejectModal()

### Community 8 - "CashAdvance Details Modal"
Cohesion: 0.06
Nodes (33): closeDetails(), confirmAcknowledge(), confirmAdminDecision(), drawSignature(), prepareSignatureCanvas(), signaturePoint(), startSignature(), { addToast } (+25 more)

### Community 84 - "Delete Confirm Modal"
Cohesion: 0.32
Nodes (7): close(), handleConfirm(), auth, emit, password, props, showPassword

### Community 85 - "Reports View Vue"
Cohesion: 0.25
Nodes (6): generating, lineData, lineOptions, periods, reports, selectedPeriod

### Community 89 - "Base Pagination Vue"
Cohesion: 0.33
Nodes (6): setPage(), emit, end, props, start, totalPages

### Community 91 - "Reimbursement Filters Composable"
Cohesion: 0.52
Nodes (5): getCutoffPeriod(), getSortValue(), normalizeStatus(), statusLabel(), useReimbursementFilters()

### Community 92 - "Formatters JS Utils"
Cohesion: 0.33
Nodes (3): formatAmount(), formatPeso(), SUPPORTED_CURRENCIES

### Community 97 - "Base Modal Vue"
Cohesion: 0.40
Nodes (4): handleKeydown(), emit, props, emit

### Community 98 - "Base Warning Banner Vue"
Cohesion: 0.40
Nodes (5): dismiss(), emit, isVisible, props, themeConfig

### Community 0 - "Architecture & Governance"
Cohesion: 0.07
Nodes (79): pinia, Ai Module, AuditLogService, AuditLogs Module, BaseButton.vue, BaseKpiGrid.vue, BaseModal.vue, BaseTable.vue (+71 more)

### Community 10 - "Frontend Package Dependencies"
Cohesion: 0.05
Nodes (38): dependencies, chart.js, @headlessui/vue, lucide-vue-next, pinia, vue, vue3-lottie, vue-chartjs (+30 more)

### Community 102 - "Expense Card Vue"
Cohesion: 0.33
Nodes (5): canDelete, canEdit, imageError, isMenuOpen, props

### Community 107 - "Laravel Bootstrap"
Cohesion: 0.40
Nodes (3): Illuminate\Foundation\Application, Illuminate\Foundation\Configuration\Exceptions, Illuminate\Foundation\Configuration\Middleware

### Community 108 - "PSR-4 Autoload Mappings"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 110 - "Logging Config"
Cohesion: 0.40
Nodes (4): Monolog\Handler\NullHandler, Monolog\Handler\StreamHandler, Monolog\Handler\SyslogUdpHandler, Monolog\Processor\PsrLogMessageProcessor

### Community 114 - "Policy Store Composable"
Cohesion: 0.40
Nodes (4): MOCK_EXPENSE_LIMITS, MOCK_PENALTY_RULES, MOCK_POLICY_LOGS, usePolicyStore

### Community 117 - "Community 117"
Cohesion: 0.50
Nodes (3): Illuminate\Foundation\Inspiring, Illuminate\Support\Facades\Artisan, Illuminate\Support\Facades\Schedule

### Community 120 - "Community 120"
Cohesion: 0.50
Nodes (3): iconMap, store, unread

### Community 125 - "Community 125"
Cohesion: 0.50
Nodes (3): ACTION_MAP, auditColumns, MOCK_LOGS

### Community 126 - "Community 126"
Cohesion: 0.50
Nodes (3): auth, route, router

### Community 127 - "Community 127"
Cohesion: 0.50
Nodes (3): plugin, $schema, .opencode/plugins/graphify.js

### Community 147 - "Community 147"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 148 - "Community 148"
Cohesion: 0.67
Nodes (3): post-autoload-dump, Illuminate\\Foundation\\ComposerScripts::postAutoloadDump, @php artisan package:discover --ansi

### Community 27 - "Receipt Details Modal"
Cohesion: 0.11
Nodes (18): auth, canEditVatClassification, emit, hasReceiptGrossAmount, hasVatClassification, isApproveDisabled, isConfirmDecisionDisabled, isOwnSubmission (+10 more)

### Community 28 - "API Dev Dependencies"
Cohesion: 0.11
Nodes (17): devDependencies, concurrently, laravel-vite-plugin, tailwindcss, @tailwindcss/vite, vite, private, $schema (+9 more)

### Community 35 - "Composer Non-Dev Deps"
Cohesion: 0.14
Nodes (13): autoload-dev, psr-4, description, keywords, license, minimum-stability, name, prefer-stable (+5 more)

### Community 36 - "Composer Scripts"
Cohesion: 0.14
Nodes (14): scripts, dev, post-create-project-cmd, post-update-cmd, pre-package-uninstall, test, Composer\\Config::disableProcessTimeout, Illuminate\\Foundation\\ComposerScripts::prePackageUninstall (+6 more)

### Community 44 - "Feature & Unit Tests"
Cohesion: 0.26
Nodes (6): Carbon\Carbon, Illuminate\Foundation\Testing\RefreshDatabase, Illuminate\Support\Facades\Http, Illuminate\Support\Facades\Queue, Illuminate\Support\Facades\Storage, Mockery

### Community 80 - "Composer Dependencies"
Cohesion: 0.25
Nodes (8): require, firebase/php-jwt, laravel/framework, laravel/tinker, league/flysystem-aws-s3-v3, php, predis/predis, thiagoalessio/tesseract_ocr

### Community 81 - "Composer Dev Dependencies"
Cohesion: 0.25
Nodes (8): require-dev, fakerphp/faker, laravel/pail, laravel/pao, laravel/pint, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 82 - "Setup Scripts"
Cohesion: 0.25
Nodes (8): post-root-package-install, setup, composer install, npm install --ignore-scripts, npm run build, @php artisan key:generate, @php artisan migrate --force, @php -r \"file_exists('.env') || copy('.env.example', '.env');\

### Community 87 - "Composer Config Allow Plugins"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 90 - "Receipt Quality Rejection Modal"
Cohesion: 0.29
Nodes (6): badgeLabel, emit, isDuplicate, previewUrl, props, tips

### Community 105 - "Debug Session Workflow"
Cohesion: 0.70
Nodes (5): Diagnose Phase, Fix Phase, Isolate Phase, Reproduce Phase, Debug Session Workflow

### Community 106 - "AI Prompt Safety Review"
Cohesion: 0.40
Nodes (5): Bias Detection, Effectiveness Evaluation, AI Prompt Engineering Safety Review, Safety Assessment, Security and Privacy Assessment

### Community 116 - "Community 116"
Cohesion: 0.50
Nodes (4): HTTP Error Codes 401 403 409, Role-Based Access Control, Role-Based Access Control, Session Timeout Policy

### Community 119 - "Community 119"
Cohesion: 0.83
Nodes (4): Logo, SBSI Brand, SBSI Logo, SBSI Logo Short

### Community 155 - "Community 155"
Cohesion: 1.00
Nodes (3): AppLayout Component, AuthLayout Component, useAuthStore

### Community 2 - "Receipt OCR Extracted Fields"
Cohesion: 0.06
Nodes (51): Currency Field, USD, 5/20/2025 11:47:32 AM, 340, 1 Family Bundle - 10 pieces (Chickenjoy bucket + 3 large sides), 3890 S Maryland Parkway Suite 137, Las Vegas, NV 89119, Jollibee, 358 (+43 more)

### Community 58 - "Penalty Computation Rationale"
Cohesion: 0.27
Nodes (10): Aging Tracker Countdown, Balance Variance Visual System, Daily Penalty 55 PHP, LIQUIDATED Status, OVERDUE Status, Reconciliation Lifecycle, UNLIQUIDATED Status, useCashAdvanceStore (+2 more)

### Community 71 - "Compliance & Immutability Rules"
Cohesion: 0.22
Nodes (9): useNotificationStore, BIR Compliance, Governance and Security, Client-Side Pre-Encryption, Append-Only Penalty Records, Audit Log Immutability, BIR Compliance, VAT Classification (+1 more)

### Community 76 - "Reusability Rules"
Cohesion: 0.25
Nodes (8): AuditLogService log, AuditLogService log, BIR VAT Compliance Rules, Conventional Commits, Pull Request Template, A-09 Reusability Constraint, Reusability and Anti-Duplication Rule, A-09 Reusability Rule

### Community 77 - "Core Architecture Concepts"
Cohesion: 0.29
Nodes (8): Modular Monolith Architecture, Pre-Aggregated Analytics, SERMS Overview, Canonical Source of Truth, Antigravity AI Engine, Modular Monolith Architecture, Pre-Aggregated Analytics, SERMS System Scope

### Community 78 - "OCR Workflow Rules"
Cohesion: 0.25
Nodes (8): useReimbursementStore, Reimbursement Module, AI Expense Categorization, Cutoff Validation, Duplicate Detection, OCR Confidence Threshold, OCR Workflow, Supabase Bucket Storage

### Community 83 - "Docker Compose Services"
Cohesion: 0.25
Nodes (8): Nginx API Service, OCR Pipeline Service, PHP-FPM Service, PRS Integration Endpoint, Redis Service, Shared Capstone Network, Docker Entrypoint Script, Laravel Framework

### Community 86 - "Queue Workers & Services"
Cohesion: 0.33
Nodes (7): main.js Entry Point, SERMS App Mount Point, Queue Worker Service, MySQL Service, Web Frontend Service, Worker Service, Laravel Queues

### Community 93 - "Hold-to-Confirm Pattern"
Cohesion: 0.33
Nodes (6): Immediate Validation Feedback, FileUpload.vue Component, Hold-to-Confirm Pattern, Hold-to-Confirm Pattern, Hold-to-Confirm Pattern, Hold-to-Confirm Pattern

### Community 96 - "Design System Rationale"
Cohesion: 0.33
Nodes (6): Readout Typography, Clinical Neutral Palette, Instrument Card Component, Liquidation Console, Blueprint Grid System, Engineered Interface Philosophy

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
- **547 isolated node(s):** `activeDraft`, `activeStatus`, `{ addToast }`, `agingInfo`, `{
  approvingId,
  rejectingId,
  confirmPassword,
  rejectionComment,
  isReviewSubmitting,
  openApproveModal,
  openRejectModal,
  cancelApprove,
  cancelReject,
  confirmApprove,
  confirmReject,
}` (+542 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **42 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

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