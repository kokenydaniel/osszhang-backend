<?php

use App\Http\Controllers\Api\AdminProductUpdateController;
use App\Http\Controllers\Api\AdminAnnouncementController;
use App\Http\Controllers\Api\AdminAuditLogController;
use App\Http\Controllers\Api\AdminFeatureController;
use App\Http\Controllers\Api\AdminHouseholdController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AdminWebhookController;
use App\Http\Controllers\Api\AIController;
use App\Http\Controllers\Api\AIFinanceController;
use App\Http\Controllers\Api\AiTravelController;
use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessDocumentController;
use App\Http\Controllers\Api\BusinessOrderController;
use App\Http\Controllers\Api\CronController;
use App\Http\Controllers\Api\DashboardAiCfoController;
use App\Http\Controllers\Api\FeedbackReportController;
use App\Http\Controllers\Api\AdminFeedbackReportController;
use App\Http\Controllers\Api\DebtController;
use App\Http\Controllers\Api\HouseholdController;
use App\Http\Controllers\Api\InsuranceController;
use App\Http\Controllers\Api\InvestmentController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\MeterController;
use App\Http\Controllers\Api\PocketMoneyController;
use App\Http\Controllers\Api\ProductUpdateController;
use App\Http\Controllers\Api\ReceivableController;
use App\Http\Controllers\Api\RentalController;
use App\Http\Controllers\Api\RentalIncomeController;
use App\Http\Controllers\Api\RentalExpenseController;
use App\Http\Controllers\Api\SavingController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UtilityController;
use App\Http\Controllers\Api\WalletController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::post('/cron/shopify-sync', [CronController::class, 'shopifySync'])
    ->middleware('cron.secret');

Route::middleware(['auth:sanctum', 'platform.admin'])->prefix('admin')->group(function () {
    Route::get('/households', [AdminHouseholdController::class, 'index']);
    Route::get('/households/{household}', [AdminHouseholdController::class, 'show']);
    Route::patch('/households/{household}/tier-grant', [AdminHouseholdController::class, 'updateTierGrant']);
    Route::patch('/households/{household}/ai-settings', [AdminHouseholdController::class, 'updateAiSettings']);
    Route::delete('/households/{household}', [AdminHouseholdController::class, 'destroy']);

    Route::get('/users', [AdminUserController::class, 'index']);
    Route::patch('/users/{user}/activate', [AdminUserController::class, 'activate']);
    Route::patch('/users/{user}/deactivate', [AdminUserController::class, 'deactivate']);
    Route::post('/users/{user}/impersonate', [AdminUserController::class, 'impersonate']);
    Route::patch('/users/{user}/tier-grant', [AdminUserController::class, 'updateTierGrant']);
    Route::post('/users/{user}/reset-password', [AdminUserController::class, 'resetPassword']);

    Route::get('/features', [AdminFeatureController::class, 'index']);
    Route::patch('/features/{key}', [AdminFeatureController::class, 'update']);

    Route::get('/audit-logs', [AdminAuditLogController::class, 'index']);
    Route::get('/webhooks', [AdminWebhookController::class, 'index']);
    Route::post('/webhooks', [AdminWebhookController::class, 'store']);
    Route::delete('/webhooks/{webhook}', [AdminWebhookController::class, 'destroy']);

    Route::get('/announcements', [AdminAnnouncementController::class, 'index']);
    Route::post('/announcements', [AdminAnnouncementController::class, 'store']);
    Route::put('/announcements/{announcement}', [AdminAnnouncementController::class, 'update']);
    Route::delete('/announcements/{announcement}', [AdminAnnouncementController::class, 'destroy']);
    Route::patch('/announcements/{announcement}/toggle', [AdminAnnouncementController::class, 'toggle']);

    Route::get('/product-updates', [AdminProductUpdateController::class, 'index']);
    Route::post('/product-updates', [AdminProductUpdateController::class, 'store']);
    Route::put('/product-updates/{productUpdate}', [AdminProductUpdateController::class, 'update']);
    Route::delete('/product-updates/{productUpdate}', [AdminProductUpdateController::class, 'destroy']);
    Route::patch('/product-updates/{productUpdate}/toggle', [AdminProductUpdateController::class, 'toggle']);

    Route::get('/feedback-reports/attention-count', [AdminFeedbackReportController::class, 'attentionCount']);
    Route::get('/feedback-reports', [AdminFeedbackReportController::class, 'index']);
    Route::get('/feedback-reports/{feedbackReport}', [AdminFeedbackReportController::class, 'show']);
    Route::post('/feedback-reports/{feedbackReport}/messages', [AdminFeedbackReportController::class, 'storeMessage']);
    Route::patch('/feedback-reports/{feedbackReport}', [AdminFeedbackReportController::class, 'update']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/feedback-reports/mine', [FeedbackReportController::class, 'index']);
    Route::get('/feedback-reports/attachments/{attachment}/download', [FeedbackReportController::class, 'downloadAttachment']);
    Route::get('/feedback-reports/{feedbackReport}', [FeedbackReportController::class, 'show']);
    Route::post('/feedback-reports/{feedbackReport}/messages', [FeedbackReportController::class, 'storeMessage']);
    Route::post('/feedback-reports', [FeedbackReportController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'household.editor'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'update']);
    Route::post('/me/change-password', [AuthController::class, 'changePassword']);
    Route::post('/me/product-updates/{productUpdate}/dismiss', [ProductUpdateController::class, 'dismiss']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Household management
    Route::get('/household', [HouseholdController::class, 'show']);
    Route::put('/household', [HouseholdController::class, 'update']);
    Route::delete('/household', [HouseholdController::class, 'destroy']);
    Route::put('/household/categories', [HouseholdController::class, 'updateCategories']);
    Route::put('/household/code', [HouseholdController::class, 'updateInviteCode']);
    Route::post('/household/members', [HouseholdController::class, 'addMember']);
    Route::put('/household/members/{member}', [HouseholdController::class, 'updateMember']);
    Route::delete('/household/members/{member}', [HouseholdController::class, 'deleteMember']);

    // Wallets
    Route::apiResource('wallets', WalletController::class)->except(['show']);
    Route::put('/wallets/{wallet}/manual-balance', [WalletController::class, 'updateManualBalance']);

    // Subscription / billing (dummy data until payment provider)
    Route::get('/subscription/billing', [SubscriptionController::class, 'billing']);
    Route::get('/subscription/invoices/{invoice}/download', [SubscriptionController::class, 'downloadInvoice']);
    Route::post('/subscription/checkout', [SubscriptionController::class, 'checkout']);
    Route::get('/subscription/portal', [SubscriptionController::class, 'portal']);

    // Transactions / Budget
    Route::get('/transactions/goal-rows', [TransactionController::class, 'goalRows']);
    Route::apiResource('transactions', TransactionController::class);
    Route::post('/transactions/clone', [TransactionController::class, 'cloneMonth']);
    Route::post('/transactions/{transaction}/items', [TransactionController::class, 'addItem']);
    Route::put('/transactions/{transaction}/items/{item}', [TransactionController::class, 'updateItem']);
    Route::delete('/transactions/{transaction}/items/{item}', [TransactionController::class, 'deleteItem']);

    // Utilities (Pro+)
    Route::middleware('tier.module:utilities')->group(function () {
        Route::post('/utilities/clone', [UtilityController::class, 'cloneMonth']);
        Route::post('/utilities/settlement', [UtilityController::class, 'settleMonth']);
        Route::delete('/utilities/settlement', [UtilityController::class, 'unsettleMonth']);
        Route::apiResource('utilities', UtilityController::class);
    });

    // Meters (Pro+)
    Route::middleware('tier.module:meters')->group(function () {
        Route::apiResource('meters', MeterController::class);
        Route::post('/meters/{meter}/readings', [MeterController::class, 'addReading']);
        Route::put('/meters/{meter}/readings/{reading}', [MeterController::class, 'updateReading']);
        Route::delete('/meters/{meter}/readings/{reading}', [MeterController::class, 'deleteReading']);
    });

    // Business (Premium)
    Route::middleware('tier.module:business')->group(function () {
        Route::post('/business-orders/shopify-import', [BusinessOrderController::class, 'shopifyImport'])
            ->middleware('tier.feature:shopify_import');
        Route::post('/business-orders/woocommerce-import', [BusinessOrderController::class, 'woocommerceImport'])
            ->middleware(['tier.feature:shopify_import', 'platform.feature:enable_woocommerce_import']);
        Route::post('/business-orders/unas-import', [BusinessOrderController::class, 'unasImport'])
            ->middleware(['tier.feature:shopify_import', 'platform.feature:enable_unas_import']);
        Route::apiResource('business-orders', BusinessOrderController::class);

        Route::middleware('platform.feature:enable_attachments')->group(function () {
            Route::get('/business-documents', [BusinessDocumentController::class, 'index']);
            Route::post('/business-documents', [BusinessDocumentController::class, 'store']);
            Route::post('/business-documents/sumup-import', [BusinessDocumentController::class, 'sumupImport'])
                ->middleware('tier.feature:sumup_import');
            Route::get('/business-documents/bundle', [BusinessDocumentController::class, 'bundle']);
            Route::get('/business-documents/{businessDocument}/download', [BusinessDocumentController::class, 'download']);
            Route::delete('/business-documents/{businessDocument}', [BusinessDocumentController::class, 'destroy']);
        });
    });

    Route::middleware('tier.module:pocket_money')->group(function () {
        Route::post('pocket-money/apply-interest', [PocketMoneyController::class, 'applyInterest']);
        Route::apiResource('pocket-money', PocketMoneyController::class)->except(['show']);
    });

    Route::middleware('tier.module:insurance')->group(function () {
        Route::apiResource('insurance-policies', InsuranceController::class)->except(['show']);
    });

    Route::middleware('tier.module:rental')->group(function () {
        Route::get('rental-properties/export', [RentalController::class, 'export']);
        Route::apiResource('rental-properties', RentalController::class);
        Route::apiResource('rental-income-entries', RentalIncomeController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('rental-expenses', RentalExpenseController::class)->only(['store', 'update', 'destroy']);
    });

    Route::middleware('tier.module:receivables')->group(function () {
        Route::get('receivables', [ReceivableController::class, 'index']);
        Route::post('receivables/contacts', [ReceivableController::class, 'storeContact']);
        Route::patch('receivables/contacts/{receivable_contact}', [ReceivableController::class, 'updateContact']);
        Route::delete('receivables/contacts/{receivable_contact}', [ReceivableController::class, 'destroyContact']);
        Route::post('receivables/contacts/{receivable_contact}/entries', [ReceivableController::class, 'storeEntry']);
        Route::patch('receivables/entries/{receivable_entry}', [ReceivableController::class, 'updateEntry']);
        Route::delete('receivables/entries/{receivable_entry}', [ReceivableController::class, 'destroyEntry']);
    });

    Route::middleware(['platform.feature:enable_attachments'])->group(function () {
        Route::get('/transactions/{transaction}/attachments', [AttachmentController::class, 'indexForTransaction']);
        Route::post('/transactions/{transaction}/attachments', [AttachmentController::class, 'storeForTransaction']);
        Route::get('/ledger-entries/{ledgerEntry}/attachments', [AttachmentController::class, 'indexForLedgerEntry']);
        Route::post('/ledger-entries/{ledgerEntry}/attachments', [AttachmentController::class, 'storeForLedgerEntry']);
        Route::get('/transactions/{transaction}/budget-items/attachments', [AttachmentController::class, 'indexForBudgetLedgerItem']);
        Route::post('/transactions/{transaction}/budget-items/attachments', [AttachmentController::class, 'storeForBudgetLedgerItem']);
        Route::get('/insurance-policies/{insurancePolicy}/attachments', [AttachmentController::class, 'indexForInsurancePolicy'])
            ->middleware('tier.module:insurance');
        Route::post('/insurance-policies/{insurancePolicy}/attachments', [AttachmentController::class, 'storeForInsurancePolicy'])
            ->middleware('tier.module:insurance');
        Route::get('/rental-properties/{rental_property}/attachments', [AttachmentController::class, 'indexForRentalProperty'])
            ->middleware('tier.module:rental');
        Route::post('/rental-properties/{rental_property}/attachments', [AttachmentController::class, 'storeForRentalProperty'])
            ->middleware('tier.module:rental');
        Route::get('/debts/{debt}/attachments', [AttachmentController::class, 'indexForDebt'])
            ->middleware('tier.module:debts');
        Route::post('/debts/{debt}/attachments', [AttachmentController::class, 'storeForDebt'])
            ->middleware('tier.module:debts');
        Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download']);
        Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy']);
    });

    // Debts (Pro+)
    Route::middleware('tier.module:debts')->group(function () {
        Route::apiResource('debts', DebtController::class);
    });

    // Savings + investments (Pro+)
    Route::middleware('tier.module:savings')->group(function () {
        Route::apiResource('savings', SavingController::class);
        Route::post('/savings/{saving}/entries', [SavingController::class, 'addEntry']);
        Route::put('/savings/{saving}/entries/{entry}', [SavingController::class, 'updateEntry']);
        Route::delete('/savings/{saving}/entries/{entry}', [SavingController::class, 'deleteEntry']);
        Route::put('/savings/{saving}/monthly-contribution', [SavingController::class, 'upsertMonthlyContribution']);
        Route::apiResource('investments', InvestmentController::class);
    });

    // Invitations
    Route::apiResource('invitations', InvitationController::class);

    // AI Integration (Premium tier or lifetime_admin only)
    Route::middleware('premium.ai')->group(function () {
        Route::post('/ai/query', [AIController::class, 'query']);

        Route::prefix('ai/v1')->group(function () {
            Route::post('/transactions/auto-categorize', [AIFinanceController::class, 'autoCategorizeTransaction']);
            Route::get('/budget/overspend-root-cause', [AIFinanceController::class, 'overspendRootCause']);
            Route::get('/budget/cashflow-forecast', [AIFinanceController::class, 'cashflowForecast']);
            Route::get('/utilities/anomalies', [AIFinanceController::class, 'utilityAnomalies']);
            Route::post('/savings/recommendations', [AIFinanceController::class, 'savingsRecommendations']);
            Route::post('/debts/optimize', [AIFinanceController::class, 'optimizeDebts']);
            Route::get('/dashboard/weekly-briefing', [AIFinanceController::class, 'weeklyBriefing'])
                ->middleware('platform.feature:enable_ai_weekly_briefing');
            Route::get('/budget/payment-priority', [AIFinanceController::class, 'paymentPriority'])
                ->middleware('platform.feature:enable_ai_payment_priority');
            Route::get('/budget/vat-estimate', [AIFinanceController::class, 'vatEstimate'])
                ->middleware('platform.feature:enable_ai_vat_estimate');
            Route::get('/budget/cost-reduction', [AIFinanceController::class, 'costReductionSuggestions'])
                ->middleware('platform.feature:enable_ai_cost_reduction');
        });

        Route::middleware('platform.feature:enable_ai_cfo')->post('/dashboard/ai-cfo', DashboardAiCfoController::class);

        Route::middleware('platform.feature:enable_ai_travel_planner')->prefix('tools/travel')->group(function () {
            Route::post('/plan', [AiTravelController::class, 'plan']);
        });
    });
});
