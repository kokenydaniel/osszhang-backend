<?php

use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\HouseholdController;
use App\Http\Controllers\Api\UtilityController;
use App\Http\Controllers\Api\MeterController;
use App\Http\Controllers\Api\BusinessOrderController;
use App\Http\Controllers\Api\DebtController;
use App\Http\Controllers\Api\SavingController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\AIController;
use App\Http\Controllers\Api\AIFinanceController;
use App\Http\Controllers\Api\PlatformController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\InvestmentController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AdminFeatureController;
use App\Http\Controllers\Api\AdminAnnouncementController;
use App\Http\Controllers\Api\DashboardAiCfoController;
use App\Http\Controllers\Api\AiTravelController;
use App\Http\Controllers\Api\CronController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::post('/cron/shopify-sync', [CronController::class, 'shopifySync'])
    ->middleware('cron.secret');

Route::middleware(['auth:sanctum', 'platform.admin'])->prefix('admin')->group(function () {
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::patch('/users/{user}/activate', [AdminUserController::class, 'activate']);
    Route::patch('/users/{user}/deactivate', [AdminUserController::class, 'deactivate']);
    Route::post('/users/{user}/impersonate', [AdminUserController::class, 'impersonate']);
    Route::patch('/users/{user}/tier-grant', [AdminUserController::class, 'updateTierGrant']);

    Route::get('/features', [AdminFeatureController::class, 'index']);
    Route::patch('/features/{key}', [AdminFeatureController::class, 'update']);

    Route::get('/announcements', [AdminAnnouncementController::class, 'index']);
    Route::post('/announcements', [AdminAnnouncementController::class, 'store']);
    Route::put('/announcements/{announcement}', [AdminAnnouncementController::class, 'update']);
    Route::delete('/announcements/{announcement}', [AdminAnnouncementController::class, 'destroy']);
    Route::patch('/announcements/{announcement}/toggle', [AdminAnnouncementController::class, 'toggle']);
});

Route::middleware(['auth:sanctum', 'household.editor'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'update']);
    Route::post('/me/change-password', [AuthController::class, 'changePassword']);
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
        Route::apiResource('business-orders', BusinessOrderController::class);
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
            Route::get('/dashboard/weekly-briefing', [AIFinanceController::class, 'weeklyBriefing']);
        });

        Route::middleware('platform.feature:enable_ai_cfo')->post('/dashboard/ai-cfo', DashboardAiCfoController::class);

        Route::middleware('platform.feature:enable_ai_travel_planner')->prefix('tools/travel')->group(function () {
            Route::post('/plan', [AiTravelController::class, 'plan']);
        });
    });
});
