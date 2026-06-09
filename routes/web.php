<?php

use Inertia\Inertia;
use App\Models\Payee;
use App\Models\Voucher;
use Laravel\Fortify\Features;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\MdaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Admin\SectorController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\VouchersController;
use App\Http\Controllers\UserActivitiesController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\ActivityStatsController;

use App\Http\Controllers\Admin\FinalAccountsController;
use App\Http\Controllers\Admin\ImportVoucherController;
use App\Http\Controllers\Admin\InternalAuditController;
use App\Http\Controllers\Admin\ChangePasswordController;
use App\Http\Controllers\Admin\AccountantGeneralController;
use App\Http\Controllers\Admin\ExpenditureControlController;
use App\Http\Controllers\Admin\ReceiptController;
use App\Http\Controllers\Admin\RemittanceController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\PayeeController;
use App\Http\Controllers\Admin\AdministrativeCodeController;
use App\Http\Controllers\Admin\CashBookBalanceBfwController;
use App\Http\Controllers\Admin\CashbookFinancialYearController;

use App\Http\Controllers\Admin\BankController;
// use App\Http\Controllers\Admin\ActivitySummaryController;
use App\Http\Controllers\Admin\BankActivityController;
use App\Http\Controllers\Admin\ReceiptActivityController;
use App\Http\Controllers\Admin\FinancialYearController;

use App\Http\Controllers\Admin\CashbookController;
use App\Http\Controllers\Admin\EconomyCodeController;
use App\Http\Controllers\Admin\EconomyCodeItemController;
use App\Http\Controllers\Admin\RetirementController;
use App\Http\Controllers\Admin\AdministrativeCodeItemController;

use App\Http\Controllers\Admin\JournalController;

// Mara

use App\Http\Controllers\Finance\FinancialReportController;
use App\Http\Controllers\Finance\FinancialPositionController;
use App\Http\Controllers\Finance\CashFlowController;
use App\Http\Controllers\Finance\AssetsEquityController;
use App\Http\Controllers\Finance\NoteToGpfsController;
use App\Http\Controllers\Finance\CashAndBankBalancesController;
use App\Http\Controllers\Admin\MdaBankBalanceController;


// end Mara



Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

// Route::get('dashboard', function () {
//     return Inertia::render('Dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/user-activities', [UserActivitiesController::class, 'index'])->name('user-activities');
// Route::get('/activity-stats/summary', [ActivitySummaryController::class, 'getSummary']);

Route::middleware(['auth', 'verified'])->group(function () {
    // Route::resource('users', UsersController::class);
    Route::resource('users', UsersController::class)->except(['create', 'edit']);
    Route::get('/users/{user}/permissions', [UsersController::class, 'permissions'])->name('users.permissions');
    Route::post('/users/{user}/roles', [UsersController::class, 'updateRoles'])->name('userz.roles.update');
    Route::post('/users/{user}/permissions', [UsersController::class, 'updatePermissions'])->name('userz.permissions.update');

    // Roles and Roles Management Routes
    // Route::get('/listRoles', [RoleController::class, 'listRoles'])->name('listRoles')->middleware(['permission:role-list', 'verified']);
    // Route::resource('roles', RoleController::class)->middleware(['role_or_permission:Roles|role-create|role-edit|role-update|role-delete', 'verified']);
    Route::resource('roles', RoleController::class)->middleware(['verified']);

    // Permissions and Permissions Management Routes
    // Route::get('/listPermissions', [PermissionController::class, 'listPermissions'])->name('listPermissions')->middleware(['permission:permission-list', 'verified']);
    // Route::resource('permissions', PermissionController::class)->middleware(['role_or_permission:Permissions|permission-create|permission-edit|permission-update|permission-delete', 'verified']);
    Route::resource('permissions', PermissionController::class)->middleware(['verified']);

    // Route::get('roles', [UsersController::class, 'index'] )->name('users.index');
    // Route::get('permissions', [UsersController::class, 'index'] )->name('users.index');

    // Voucher Management Routes
    Route::resource('vouchers', VouchersController::class);
    Route::get('/vouchers/{voucher}/print', [VouchersController::class, 'print'])->name('vouchers.print');
    Route::get('vsearch', [VouchersController::class, 'search'])->name('vouchers.index2');
    Route::get('avsearch', [InternalAuditController::class, 'search'])->name('vouchers.audit.index');
    Route::get('dashboardStats', [ActivityLogController::class, 'dashboardStats'])->name('dashboardStats');

    Route::get('schedules/next-number', [ScheduleController::class, 'getNextNumber'])->name('schedules.next-number');
    Route::resource('schedules', ScheduleController::class);
    Route::get('/schedules/{schedule}/print', [ScheduleController::class, 'print'])->name('schedules.print');
    Route::get('sssearch', [ScheduleController::class, 'search'])->name('receipts.search.index');

    Route::resource('receipts', ReceiptController::class);


    Route::prefix('vouchers')->name('vouchers.')->group(function () {
        // Retirement routes for vouchers (SPECIFIC ROUTES FIRST)
        Route::get('/{voucher}/retire', [RetirementController::class, 'create'])->name('retire.create');
        Route::post('/{voucher}/retire', [RetirementController::class, 'store'])->name('retire.store');

        // Approve retirement
        Route::put('/{voucher}/approve', [VouchersController::class, 'approve'])->name('vouchers.approve');
        Route::put('/{voucher}/draft', [VouchersController::class, 'makeDraft'])->name('vouchers.draft');


        // Print route
        Route::get('/{voucher}/print', [VouchersController::class, 'print'])->name('print');

        // Generic resource route LAST
        Route::resource('/', VouchersController::class)->parameters(['' => 'voucher']);
    });


    Route::prefix('retirements')->name('retirements.')->group(function () {
        // Main retirement pages
        Route::get('/', [RetirementController::class, 'index'])->name('index');
        Route::get('/pending', [RetirementController::class, 'pending'])->name('pending');
        Route::get('/stats', [RetirementController::class, 'stats'])->name('stats');

        // Retirement voucher management
        Route::get('/{retirementVoucher}', [RetirementController::class, 'show'])->name('show');
        Route::delete('/{retirementVoucher}', [RetirementController::class, 'destroy'])->name('destroy');

        // Retirement approval actions
        Route::post('/{retirementVoucher}/approve', [RetirementController::class, 'approve'])->name('approve');
        Route::post('/{retirementVoucher}/reject', [RetirementController::class, 'reject'])->name('reject');
    });

    // API endpoints (for AJAX calls)
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/vouchers/{voucher}/retirement-status', [RetirementController::class, 'checkRetirementStatus'])->name('vouchers.retirement-status');
        Route::get('/vouchers/{voucher}/retirement-history', [RetirementController::class, 'history'])->name('vouchers.retirement-history');
    });


    // // API endpoints (for AJAX calls)
    // Route::prefix('api')->name('api.')->group(function () {
    //     Route::get('/vouchers/{voucher}/retirement-status', [RetirementController::class, 'checkRetirementStatus'])->name('vouchers.retirement-status');
    //     Route::get('/vouchers/{voucher}/retirement-history', [RetirementController::class, 'history'])->name('vouchers.retirement-history');
    // });







    // Economic Codes routes
    Route::get('/economy-codes', [ScheduleController::class, 'getEconomyCodes']);
    Route::get('/economy-code-items', [ScheduleController::class, 'getAllEconomyCodeItems']);
    Route::get('/economy-code-items/{economyCodeId}', [ScheduleController::class, 'getEconomyCodeItems']);
    Route::get('/payeeList', [ScheduleController::class, 'getPayees'])->name('payeeList');
    Route::get('/bankActivityList', [VouchersController::class, 'getBankActivities'])->name('bankActivityList');


    // Internal Audit Management Routes
    // Route::resource('internal-audits', InternalAuditController::class);
    // Route::post('/{voucher}/approve', [InternalAuditController::class, 'approve'])->name('approve');
    // Route::post('/{voucher}/reject', [InternalAuditController::class, 'reject'])->name('reject');

    Route::resource('final-accounts', FinalAccountsController::class);

    Route::resource('remittances', RemittanceController::class);
    Route::get('/remittances/{remittance}/print', [RemittanceController::class, 'print'])->name('remittances.print');





    Route::resource('expenditure-control', ExpenditureControlController::class);
    Route::resource('accountant-general', AccountantGeneralController::class);

    // 
    Route::resource('mdas', MdaController::class);
    Route::resource('sectors', SectorController::class);
    // Define a named route for fetching sectors
    Route::get('/mdas/{mda}/sectors', [MdaController::class, 'fetchSectors'])
        ->name('mdas.sectors.fetch');


    // Import Vouchers
    Route::post('/import', [ImportVoucherController::class, 'import'])->name('import.upload');
    Route::get('/import', [ImportVoucherController::class, 'index'])->name('import.index');

    // Import Bank Activities
    Route::post('/importBankActivities', [ImportVoucherController::class, 'importBankActivity'])->name('bankActivity.upload');
    Route::get('/importBankActivities', [ImportVoucherController::class, 'showBankActivity'])->name('bankActivity.show');

    Route::post('/importReceipts', [ImportVoucherController::class, 'importReceipt'])->name('import.receipt.upload');
    Route::get('/importReceipts', [ImportVoucherController::class, 'showReceipt'])->name('import.receipt.show');


    Route::get('/change-password', [ChangePasswordController::class, 'showForm'])->name('password2.change');
    Route::post('/change-password', [ChangePasswordController::class, 'update'])->name('password2.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('internal-audits')->name('internal-audits.')->group(function () {
        Route::get('/', [InternalAuditController::class, 'index'])->name('index');
        Route::get('/{voucher}', [InternalAuditController::class, 'show'])->name('show');
        Route::post('/{voucher}/approve', [InternalAuditController::class, 'approve'])->name('approve');
        Route::post('/{voucher}/reject', [InternalAuditController::class, 'reject'])->name('reject');

        // API endpoints for frontend
        Route::get('/{voucher}/check-documents', [InternalAuditController::class, 'checkDocuments'])->name('check-documents');
        Route::get('/required-documents', [InternalAuditController::class, 'getRequiredDocuments'])->name('required-documents');
    });
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('activity-logs')->group(function () {
        Route::get('/', [ActivityLogController::class, 'index']);
        Route::get('/{id}', [ActivityLogController::class, 'show']);
        Route::get('/stats/metrics', [ActivityLogController::class, 'dashboardMetrics']);
        Route::get('/stats/overview', [ActivityLogController::class, 'stats']);
        Route::post('/export', [ActivityLogController::class, 'export']);
    });
});




Route::middleware(['auth', 'verified'])->group(function () {
    // Activity statistics routes
    Route::get('/activities', [ActivityStatsController::class, 'index'])->name('activities.index');
    Route::get('/activity-stats', [ActivityStatsController::class, 'getStats']);
    Route::get('/user-activities', [ActivityStatsController::class, 'getUserActivities']);
    Route::post('/user-activities/export', [ActivityStatsController::class, 'exportUserActivities']);

    route::get('/reports/trialbalance', [ReportController::class, 'index'])->name('reports.trialbalance.index');
    route::post('/reports/trialbalance', [ReportController::class, 'newTrialBalance'])->name('reports.trialbalance.view');
    route::post('/reports/MDAtrialbalance', [ReportController::class, 'mdaTrialBalance'])->name('reports.mda.trialbalance.view');
    route::post('/reports/trialbalanceDetails', [ReportController::class, 'trialBalanceDetails'])->name('reports.trialbalance.details');



    // Reports routes
    Route::get('/reports/financial-report', [FinancialReportController::class, 'index'])->name('financial-report.index');
    Route::get('/reports/balance-sheet', [FinancialPositionController::class, 'index'])->name('finance.balance-sheet');
    Route::get('/reports/cash-flow', [CashFlowController::class, 'index'])->name('finance.cash-flow');
    Route::get('/reports/assets-equity', [AssetsEquityController::class, 'index'])->name('finance.assets-equity');
    Route::get('/reports/notes', [NoteToGpfsController::class, 'index'])->name('finance.notes');
    Route::get('/reports/note-to-inventory', [NoteToGpfsController::class, 'getInventoryAndReceivables'])
        ->name('finance.inventory');
    Route::get('/reports/cash-and-bank-balances', [CashAndBankBalancesController::class, 'index'])
        ->name('reports.cash-and-bank');
    Route::get('/reports/mda-cash-and-bank-balances', [MdaBankBalanceController::class, 'cashAndBankBalanceHeldByMda'])
        ->name('reports.mda-cash-and-bank-balances');
    Route::get('/reports/mda-bank-balances/report/grouped', [MdaBankBalanceController::class, 'getGroupedBalances'])
        ->name('mda-bank-balances.grouped');
    Route::get('/reports/other-bank-of-the-treasury', [CashAndBankBalancesController::class, 'getOtherBankOfTheTreasury'])
        ->name('reports.other-bank-of-the-treasury');
});


Route::middleware(['auth', 'verified'])->group(function () {
    // This single line registers receipts.index, receipts.show, receipts.store, etc.
    Route::resource('receipts', ReceiptController::class);

    // Ensure custom routes come BEFORE the resource if they conflict, 
    // but for data/import it's fine:
    Route::get('receipts/data', [ReceiptController::class, 'data'])->name('receipts.data');
    Route::get('receipts/{id}/print', [ReceiptController::class, 'print'])->name('receipts.print');
    Route::post('receipts/import', [ReceiptController::class, 'import'])->name('receipts.import');
    Route::get('arsearch', [ReceiptController::class, 'search'])->name('receipts.search.index');
});
Route::middleware(['auth', 'verified'])->group(function () {

    // Page 1: The Master List (cashbook_financial_years)
    Route::resource('cashbook-years', CashbookFinancialYearController::class);

    // Page 2: The 12 Month Cards for a specific year
    // URL: /cashbook-years/5/months
    Route::get('cashbook-years/{cashbook_year}/months', [CashbookFinancialYearController::class, 'showMonths'])
        ->name('cashbook-years.months');

    // New route: Show all accounts for a specific month
    Route::get(
        'cashbook-years/{cashbook_year}/month/{month_id}',
        [CashbookFinancialYearController::class, 'showMonthAccounts']
    )
        ->whereNumber('month_id') // Add this line
        ->name('cashbook-years.month-accounts');

    Route::post('/cashbook/{cashbook}/generate-entries', [CashbookController::class, 'generateEntries']);


    Route::get('cashbook-entries/{entry}', [CashbookController::class, 'showEntry'])
        ->name('cashbook-entries.show');

    Route::put('cashbook-entries/{entry}/status', [CashbookController::class, 'updateEntryStatus'])
        ->name('cashbook-entries.update-status');

    Route::get('cashbook-entries/search', [CashbookController::class, 'searchEntries'])
        ->name('cashbook-entries.search');

    Route::post('/cashbook/generate-batch-entries', [CashbookController::class, 'generateBatchEntries'])
        ->name('cashbook.generate-batch');

    Route::post('/cashbook/recalculate-balances/{financialYear}', [CashbookController::class, 'recalculateBalances'])
        ->name('cashbook.recalculate-balances');

    // Cashbook balance routes
    Route::get('/cashbook/{cashbook}/previous-balance', [CashbookController::class, 'getPreviousMonthBalance'])
        ->name('cashbook.previous-balance');

    Route::get('/cashbook/{cashbook}/next-month-info', [CashbookController::class, 'getNextMonthInfo'])
        ->name('cashbook.next-month-info');

    Route::get('/cashbook/{cashbook}/balance-chain', [CashbookController::class, 'getBalanceChain'])
        ->name('cashbook.balance-chain');

    Route::get('/cashbooks/{cashbook}/print', [CashbookController::class, 'print'])
        ->name('cashbooks.print');

    // Page 3: The Actual Ledger (Treasury Cash Book as seen in your image)
    // URL: /cashbook/ledger/12
    Route::get('cashbook/ledger/{cashbook}', [CashbookController::class, 'showLedger'])
        ->name('cashbook.ledger');

    // Action: Post a payment or receipt to the ledger
    Route::post('cashbook/ledger/{cashbook}/entry', [CashbookController::class, 'storeEntry'])
        ->name('cashbook.entry.store');

    // Action: Finalize and Close Month
    Route::post('cashbook/ledger/{cashbook}/close', [CashbookController::class, 'closeMonth'])
        ->name('cashbook.close');
    // Defining two required parameters in the URL
    Route::get('/cashbook/generate/{month_id}/{year}', [CashbookController::class, 'GenerateTransaction'])
        ->name('cashbook.generate');
});


Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('remittances', RemittanceController::class);
    Route::get('/remittances/{remittance}/print', [RemittanceController::class, 'print'])->name('remittances.print');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('bank-activities', BankActivityController::class);
    Route::resource('economy-codess', EconomyCodeController::class)->names('economy_code');
    Route::resource('economy-code-itemss', EconomyCodeItemController::class);
    // Route::resource('financial-years', FinancialYearController::class);

    Route::resource('administrative-code-itemss', AdministrativeCodeItemController::class);
});

Route::prefix('administrative-codes')->name('administrative-codes.')->group(function () {
    Route::get('/', [AdministrativeCodeController::class, 'index'])->name('index');
    Route::post('/', [AdministrativeCodeController::class, 'store'])->name('store');

    // Use route model binding with parameter name
    Route::put('/{administrative_code}', [AdministrativeCodeController::class, 'update'])->name('update');
    Route::patch('/{administrative_code}/toggle-status', [AdministrativeCodeController::class, 'toggleStatus'])->name('toggle-status');
    Route::get('/{administrative_code}', [AdministrativeCodeController::class, 'show'])->name('show');
});

// Route::prefix('administrative-codes')->name('administrative-codes.')->group(function () {

//     Route::resource('administrative-code-itemss', AdministrativeCodeItemController::class);
// });



Route::prefix('receipt-activities')->name('receipt-activities.')->group(function () {
    Route::get('/', [ReceiptActivityController::class, 'index'])->name('index');
    Route::post('/', [ReceiptActivityController::class, 'store'])->name('store');

    // Use consistent parameter naming - choose either receipt_activity or receiptActivity
    Route::put('/{receipt_activity}', [ReceiptActivityController::class, 'update'])->name('update');
    Route::post('/{receipt_activity}/toggle-status', [ReceiptActivityController::class, 'toggleStatus'])->name('toggle-status');
});



Route::middleware(['auth'])->group(function () {
    // Standard CRUD resource
    Route::get('/banks', [BankController::class, 'index'])->name('banks.index');
    Route::post('/banks', [BankController::class, 'store'])->name('banks.store');
    Route::put('/banks/{bank}', [BankController::class, 'update'])->name('banks.update');

    // Custom status toggle route
    Route::patch('/banks/{bank}/toggle-status', [BankController::class, 'toggleStatus'])->name('banks.toggle-status');
});


Route::middleware(['auth'])->group(function () {
    // Standard Resource Routes (excluding create/edit/show as we use a Modal)
    Route::get('/cash-book', [CashBookBalanceBfwController::class, 'index'])->name('cashbook.index');
    Route::post('/cash-book', [CashBookBalanceBfwController::class, 'store'])->name('cashbook.store');
    Route::put('/cash-book/{id}', [CashBookBalanceBfwController::class, 'update'])->name('cashbook.update');

    // Custom Toggle Route
    Route::patch('/cash-book/{id}/toggle-status', [CashBookBalanceBfwController::class, 'toggleStatus'])
        ->name('cashbook.toggle-status');

    Route::post('/cash-book/generate', [CashBookBalanceBfwController::class, 'generate_year_account_Bbfw']);
});

Route::middleware(['auth'])->group(function () {
    // Standard Resource routes (Index, Store, Update)
    Route::get('/payees', [PayeeController::class, 'index'])->name('payees.index');
    Route::post('/payees', [PayeeController::class, 'store'])->name('payees.store');
    Route::put('/payees/{payee}', [PayeeController::class, 'update'])->name('payees.update');

    // Custom route for the status toggle switch
    Route::patch('/payees/{payee}/toggle-status', [PayeeController::class, 'toggleStatus'])
        ->name('payees.toggle-status');



    Route::prefix('journals')->name('journals.')->group(function () {
        Route::get('/', [JournalController::class, 'index'])->name('index');
        Route::get('/create', [JournalController::class, 'create'])->name('create');
        Route::post('/', [JournalController::class, 'store'])->name('store');
        Route::get('/{journal}', [JournalController::class, 'show'])->name('show');
        Route::get('/{journal}/edit', [JournalController::class, 'edit'])->name('edit');
        Route::get('/{journal}/edit-data', [JournalController::class, 'editData'])->name('edit-data'); // Add this
        Route::put('/{journal}', [JournalController::class, 'update'])->name('update');
        Route::delete('/{journal}', [JournalController::class, 'destroy'])->name('destroy');

        // Additional routes
        Route::get('/{journal}/print', [JournalController::class, 'print'])->name('print');
        Route::post('/{journal}/approve', [JournalController::class, 'approve'])->name('approve');
        Route::post('/{journal}/reject', [JournalController::class, 'reject'])->name('reject');
        Route::get('/{journal}/summary', [JournalController::class, 'summary'])->name('summary');
        Route::get('/export', [JournalController::class, 'export'])->name('export');
    });

    // API Routes for journals
    Route::prefix('api')->group(function () {
        Route::post('/journals/validate-entries', [JournalController::class, 'validateEntries']);
        Route::get('/gl-accounts/{accountCode}', [JournalController::class, 'getGlAccount']);
        Route::get('/account-types', [JournalController::class, 'getAccountTypes']);
    });


    // New API routes for dropdowns
    Route::get('/api/mdas', [JournalController::class, 'getMdas'])->name('api.mdas');
    Route::get('/api/administrative-codes', [JournalController::class, 'getAdministrativeCodes'])->name('api.administrative-codes');
    Route::get('/api/administrative-sector-codes/{administrativeCodeId}', [JournalController::class, 'getAdministrativeSectorCodes'])->name('api.administrative-sector-codes');
    Route::get('/api/economic-codes', [JournalController::class, 'getEconomicCodes'])->name('api.economic-codes');
    Route::get('/api/economic-code-items/{economyCodeId}', [JournalController::class, 'getEconomicCodeItems'])->name('api.economic-code-items');
    Route::get('/api/economic-code-items-by-series/{series}', [JournalController::class, 'getEconomicCodeItemsBySeries'])->name('api.economic-code-items-by-series');
    Route::get('/api/generate-journal-number', [JournalController::class, 'generateJournalNumber'])->name('api.generate-journal-number');
});






require __DIR__ . '/settings.php';
