<?php

use App\Http\Middleware\CorsMiddleware;
use App\Http\Controllers\FetchUserDetails;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SignInController;
use App\Http\Controllers\Vs2SignUpController;
use App\Http\Controllers\Vs1SignUpController;
use App\Http\Controllers\Vs2SignInController;
use App\Http\Controllers\OtpVerificationController;
use App\Http\Controllers\CreatePasscodeController;
use App\Http\Controllers\CreateTransactionPinController;
use App\Http\Controllers\WelcomeMailController;
use App\Http\Controllers\WalletBalanceController;
use App\Http\Controllers\FetchTransactionController;
use App\Http\Controllers\VerifyUserController;
use App\Http\Controllers\VerifyPin;
use App\Http\Controllers\TamopeiTransfer;
use App\Http\Controllers\ReceiptsController;
use App\Http\Controllers\FetchBank;
use App\Http\Controllers\ResolveAccount;
use App\Http\Controllers\KycTier1;
use App\Http\Controllers\KycTier2;
use App\Http\Controllers\BvnVerification;
use App\Http\Controllers\VerifyBvnOtp;
use App\Http\Controllers\Checkout;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\NairaTransfer;
use App\Http\Controllers\Airtimelist;
use App\Http\Controllers\Airtime;
use App\Http\Controllers\DataList;
use App\Http\Controllers\DataBundle;
use App\Http\Controllers\Data;
use App\Http\Controllers\BillList;
use App\Http\Controllers\BillsProvider;
use App\Http\Controllers\ZarBanks;
use App\Http\Controllers\KesBanks;
use App\Http\Controllers\ZarPayout;
use App\Http\Controllers\KlashaTokenController;
use App\Http\Controllers\CreateCard;
use App\Http\Controllers\FetchCard;
use App\Http\Controllers\FreezeCard;
use App\Http\Controllers\UnfreezeCard;
use App\Http\Controllers\FundCard;
use App\Http\Controllers\CardWithdrawal;
use App\Http\Controllers\CardTransaction;
use App\Http\Controllers\MapCountries;
use App\Http\Controllers\MapMOMO;
use App\Http\Controllers\MomoCollection;
use App\Http\Controllers\CreateTrade;
use App\Http\Controllers\FetchTrades;
use App\Http\Controllers\Rate;
use App\Http\Controllers\UpdateTrade;
use App\Http\Controllers\Ussd;
use App\Http\Controllers\BillCableVerify;
use App\Http\Controllers\Services;
use App\Http\Controllers\Cable;
use App\Http\Controllers\CablePackage;
use App\Http\Controllers\CablePay;
use App\Http\Controllers\UtilityPay;
use App\Http\Controllers\Webhook;
use App\Http\Controllers\MomoTransfer;
use App\Http\Controllers\KycVerification;








Route::middleware([CorsMiddleware::class])->group(function () {
    Route::post('/vs2/fetch/users', [FetchUserDetails::class, 'fetchUserWithToken']);
    Route::post('/vs2/fetch/bank', [FetchUserDetails::class, 'UsersAccountDetails']);
    Route::post('/vs1/signin', [SignInController::class, 'postSignIn']);
    Route::post('/vs2/signup', [Vs2SignUpController::class, 'register']);
    Route::post('/vs1/signup', [Vs1SignUpController::class, 'signUp']);
    Route::post('/vs2/signin', [Vs2SignInController::class, 'signIn']);
    Route::post('/verify-otp', [OtpVerificationController::class, 'verifyOtp']);
    Route::post('/create/passcode', [CreatePasscodeController::class, 'createPasscode']);
    Route::post('/create/pin', [CreateTransactionPinController::class, 'createTransactionPin']);
    Route::post('/send-welcome-email', [WelcomeMailController::class, 'sendWelcomeEmail']);
    Route::post('/wallet/balance', [WalletBalanceController::class, 'getBalance']);
    Route::post('/fetch/transactions', [FetchTransactionController::class, 'fetchTransactions']);
    Route::post('/account/resolve/user', [VerifyUserController::class, 'verifyUser']);
    Route::post('/verify/pin', [VerifyPin::class, 'verify']);
    Route::post('/wallet/transfer', [TamopeiTransfer::class, 'transfer']);
    Route::post('/receipt', [ReceiptsController::class, 'getReceipt']); 
    Route::get('/fetch/banks', [FetchBank::class, 'fetchBanks']);
    Route::post('/resolve/account', [ResolveAccount::class, 'resolveAccount']);
    Route::post('/kycTier1', [KycTier1::class, 'kycTier1']);
    Route::post('/kycTier2', [KycTier2::class, 'upgradeToTier2']);
    Route::post('/verify-bvn', [BvnVerification::class, 'verify']);
    Route::post('/verify-bvn-otp', [VerifyBvnOtp::class, 'verify']);
    Route::post('/checkout', [Checkout::class, 'handleCheckout']);
    Route::post('/make-payment', [PaymentController::class, 'makePayment']);
    Route::post('/naira-transfer', [NairaTransfer::class, 'transfer']);
    Route::get('/service-categories', [Airtimelist::class, 'fetchServiceCategories']);
    Route::get('/service/categories', [DataList::class, 'getServiceCategories']);
    Route::post('/service-bundles', [DataBundle::class, 'getBundlesForNetwork']);
    Route::post('/purchase-airtime', [Airtime::class, 'handleAirtimePurchaseRequest']);
    Route::post('/purchase-data', [Data::class, 'handlePostRequest']);
    Route::get('/bill/list', [BillList::class, 'getServiceCategories']);
    Route::post('/bill/providers', [BillsProvider::class, 'getProviderProducts']);
    Route::post('/kyc/upload', [KycTier2::class, 'handleKycTier2Upload']);
    Route::get('/zar-banks', [ZarBanks::class, 'fetchBanks']);
    Route::get('/kes-banks', [KesBanks::class, 'fetchBanks']);
    Route::post('/zar-payout', [ZarPayout::class, 'resolveAccount']);
    Route::get('/klasha-token', [App\Http\Controllers\KlashaTokenController::class, 'fetchToken']);
    Route::post('/create-card', [CreateCard::class, 'createCard']);
    Route::post('get-card', [FetchCard::class, 'getCardDetails']);
    Route::post('fetch-card', [FetchCard::class, 'fetchCardDetails']);
    Route::patch('/freeze-card', [FreezeCard::class, 'freezeCard']);
    Route::patch('/unfreeze-card', [UnfreezeCard::class, 'unfreezeCard']);
    Route::post('/fund-card', [FundCard::class, 'fundCard']);
    Route::post('/card-withdraw', [CardWithdrawal::class, 'withdraw']);
    Route::post('/card-transaction', [CardTransaction::class, 'getCardTransactions']);
    Route::get('/countries', [MapCountries::class, 'getCountries']);
    Route::post('/institutions', [MapMOMO::class, 'getInstitutions']);
    Route::post('/momo-collection', [MomoCollection::class, 'createCollection']);
    Route::post('/create/trade', [CreateTrade::class, 'store']);
    Route::post('/fetch/users/trades', [FetchTrades::class, 'fetchUsersTrades']);
    Route::post('/fetch/market/trades', [FetchTrades::class, 'fetchMarketTrades']);
    Route::post('/cancel/trade', [CreateTrade::class, 'cancelTrades']);
    Route::get('/get/rate', [Rate::class, 'getRate']);
    Route::post('/confirmTrade', [UpdateTrade::class, 'handleTrade']);
    Route::get('/ussd', [Ussd::class, 'getUssd']);
    Route::post('/bill-verify', [BillCableVerify::class, 'getProviderProducts']);
    Route::get('/services/all', [Services::class, 'GetAllServices']);
    Route::get('/cable/list', [Cable::class, 'getCableServices']);
    Route::post('/cable/package', [CablePackage::class, 'getCablePackage']);
    Route::post('/purchase-cable', [CablePay::class, 'handleCablePurchaseRequest']);
    Route::post('/purchase-utility', [UtilityPay::class, 'purchaseUtilityRequest']);
    Route::post('/webhook', [Webhook::class, 'handle']);
    Route::post('/momo-transfer', [MomoTransfer::class, 'createTransfer']);
    Route::post('/verify-user', [KycVerification::class, 'verifyUserByEmail']);


});
