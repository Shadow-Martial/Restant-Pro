<?php

use App\Http\Controllers\AddressControler;
use App\Http\Controllers\AppsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BannersController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\CitiesController;
use App\Http\Controllers\CKEditorController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CRUD;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\FeaturesController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\FrontEndController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Items;
use App\Http\Controllers\ItemsController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PhoneVerificationController;
use App\Http\Controllers\PlansController;
use App\Http\Controllers\ProcessController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QRController;
use App\Http\Controllers\RestoareasController;
use App\Http\Controllers\RestorantController;
use App\Http\Controllers\ReviewsController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SimpleDeliveryController;
use App\Http\Controllers\TablesController;
use App\Http\Controllers\TestimonialsController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VisitsController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', [FrontEndController::class, 'index'])->name('front');
Route::get('/'.config('settings.url_route').'/{alias}', [FrontEndController::class, 'restorant'])->name('vendor');
Route::get('/city/{city}', [FrontEndController::class, 'showStores'])->name('show.stores');
Route::get('/lang', [FrontEndController::class, 'langswitch'])->name('lang.switch');

Route::post('/search/location', [FrontEndController::class, 'getCurrentLocation'])->name('search.location');

Auth::routes();

Route::get('/selectpay/{order}', [PaymentController::class, 'selectPaymentGateway'])->name('selectpay');
Route::get('/selectedpaymentt/{order}/{payment}', [PaymentController::class, 'selectedPaymentGateway'])->name('selectedpaymentt');

Route::middleware('auth', 'impersonate')->group(function () {
    Route::get('/home/{lang?}', [HomeController::class, 'index'])->name('home')->middleware(['isOwnerOnPro', 'verifiedSetup']);

    Route::resource('user', UserController::class)->except('show');
    Route::post('/user/push', [UserController::class, 'checkPushNotificationId']);

    Route::name('admin.')->group(function () {
        Route::get('syncV1UsersToAuth0', [SettingsController::class, 'syncV1UsersToAuth0'])->name('syncV1UsersToAuth0');
        Route::get('dontsyncV1UsersToAuth0', [SettingsController::class, 'dontsyncV1UsersToAuth0'])->name('dontsyncV1UsersToAuth0');
        Route::resource(config('settings.url_route_plural'), RestorantController::class, [
            'names' => [
                'index' => 'restaurants.index',
                'store' => 'restaurants.store',
                'edit' => 'restaurants.edit',
                'create' => 'restaurants.create',
                'destroy' => 'restaurants.destroy',
                'update' => 'restaurants.update',
                'show' => 'restaurants.show',
            ],
        ]);
        Route::get(config('settings.url_route_plural', 'company').'_apps', [AppsController::class, 'ownerApps'])->name('owner.apps');
        Route::put('owner_app_update/{company}', [AppsController::class, 'updateApps'])->name('owner.updateApps');

        Route::get('restaurants_add_new_shift/{restaurant}', [RestorantController::class, 'addnewshift'])->name('restaurant.addshift');

        Route::get('restaurants/loginas/{restaurant}', [RestorantController::class, 'loginas'])->name('restaurants.loginas');
        Route::get('stopimpersonate', [RestorantController::class, 'stopImpersonate'])->name('restaurants.stopImpersonate');

        Route::get('removedemodata', [RestorantController::class, 'removedemo'])->name('restaurants.removedemo');
        Route::get('sitemap', [SettingsController::class, 'regenerateSitemap'])->name('regenerate.sitemap');

        // Landing page settings
        Route::get('landing', [SettingsController::class, 'landing'])->name('landing');
        Route::prefix('landing')->name('landing.')->group(function () {
            Route::get('posts/{type}', [CRUD\PostsController::class, 'index'])->name('posts');
            Route::get('posts/{type}/create', [CRUD\PostsController::class, 'create'])->name('posts.create');
            Route::post('posts/{type}', [CRUD\PostsController::class, 'store'])->name('posts.store');

            Route::get('posts/edit/{post}', [CRUD\PostsController::class, 'edit'])->name('posts.edit');
            Route::put('posts/{post}', [CRUD\PostsController::class, 'update'])->name('posts.update');
            Route::get('posts/del/{post}', [CRUD\PostsController::class, 'destroy'])->name('posts.delete');

            Route::resource('features', FeaturesController::class);
            Route::get('/features/del/{feature}', [FeaturesController::class, 'destroy'])->name('features.delete');

            Route::resource('testimonials', TestimonialsController::class);
            Route::get('/testimonials/del/{testimonial}', [TestimonialsController::class, 'destroy'])->name('testimonials.delete');

            Route::resource('processes', ProcessController::class);
            Route::get('/processes/del/{process}', [ProcessController::class, 'destroy'])->name('processes.delete');
        });

        Route::resource('allergens', CRUD\AllergensController::class);
        Route::get('/allergens/del/{allergen}', [CRUD\AllergensController::class, 'destroy'])->name('allergens.delete');

        Route::name('restaurant.')->group(function () {

            //Remove restaurant
            Route::get('removerestaurant/{restaurant}', [RestorantController::class, 'remove'])->name('remove');

            // Tables
            Route::get('tables', [TablesController::class, 'index'])->name('tables.index')->middleware('isOwnerOnPro');
            Route::get('tables/{table}/edit', [TablesController::class, 'edit'])->name('tables.edit');
            Route::get('tables/create', [TablesController::class, 'create'])->name('tables.create');
            Route::post('tables', [TablesController::class, 'store'])->name('tables.store');
            Route::put('tables/{table}', [TablesController::class, 'update'])->name('tables.update');
            Route::get('tables/del/{table}', [TablesController::class, 'destroy'])->name('tables.delete');

            // Delivery areas
            Route::get('simpledelivery', [SimpleDeliveryController::class, 'index'])->name('simpledelivery.index')->middleware('isOwnerOnPro');
            Route::get('simpledelivery/{delivery}/edit', [SimpleDeliveryController::class, 'edit'])->name('simpledelivery.edit');
            Route::get('simpledelivery/create', [SimpleDeliveryController::class, 'create'])->name('simpledelivery.create');
            Route::post('simpledelivery', [SimpleDeliveryController::class, 'store'])->name('simpledelivery.store');
            Route::put('simpledelivery/{delivery}', [SimpleDeliveryController::class, 'update'])->name('simpledelivery.update');
            Route::get('simpledelivery/del/{delivery}', [SimpleDeliveryController::class, 'destroy'])->name('simpledelivery.delete');

            // Areas
            Route::resource('restoareas', RestoareasController::class);
            Route::get('restoareas/del/{restoarea}', [RestoareasController::class, 'destroy'])->name('restoareas.delete');

            // Areas
            Route::resource('visits', VisitsController::class);
            Route::get('visits/del/{visit}', [VisitsController::class, 'destroy'])->name('visits.delete');

            //Banners
            Route::get('banners', [BannersController::class, 'index'])->name('banners.index');
            Route::get('banners/{banner}/edit', [BannersController::class, 'edit'])->name('banners.edit');
            Route::get('banners/create', [BannersController::class, 'create'])->name('banners.create');
            Route::post('banners', [BannersController::class, 'store'])->name('banners.store');
            Route::put('banners/{banner}', [BannersController::class, 'update'])->name('banners.update');
            Route::get('banners/del/{banner}', [BannersController::class, 'destroy'])->name('banners.delete');

            //Language menu
            Route::post('storenewlanguage', [RestorantController::class, 'storeNewLanguage'])->name('storenewlanguage');
        });
    });

    Route::resource('cities', CitiesController::class);
    Route::get('/cities/del/{city}', [CitiesController::class, 'destroy'])->name('cities.delete');

    Route::post('/updateres/location/{restaurant}', [RestorantController::class, 'updateLocation']);
    Route::post('/updateres/radius/{restaurant}', [RestorantController::class, 'updateRadius']);
    Route::post('/updateres/delivery/{restaurant}', [RestorantController::class, 'updateDeliveryArea']);
    Route::post('/import/restaurants', [RestorantController::class, 'import'])->name('import.restaurants');
    Route::get('/restaurant/{restaurant}/activate', [RestorantController::class, 'activateRestaurant'])->name('restaurant.activate');
    Route::post('/restaurant/workinghours', [RestorantController::class, 'workingHours'])->name('restaurant.workinghours');
    Route::get('restaurants/working_hours/remove/{hours}', [RestorantController::class, 'workingHoursremove'])->name('restaurant.workinghoursremove');
    Route::post('/restaurant/address', [RestorantController::class, 'getCoordinatesForAddress'])->name('restaurant.coordinatesForAddress');

    Route::prefix('finances')->name('finances.')->group(function () {
        Route::get('admin', [FinanceController::class, 'adminFinances'])->name('admin');
        Route::get('owner', [FinanceController::class, 'ownerFinances'])->name('owner');
    });

    Route::prefix('stripe')->name('stripe.')->group(function () {
        Route::get('connect', [FinanceController::class, 'connect'])->name('connect');
    });

    Route::resource('reviews', ReviewsController::class);
    Route::get('/reviewsdelete/{rating}', [ReviewsController::class, 'destroy'])->name('reviews.destroyget');

    Route::resource('drivers', DriverController::class);
    Route::get('/driver/{driver}/activate', [DriverController::class, 'activateDriver'])->name('driver.activate');
    Route::get('/nearest_driver/', [DriverController::class, 'getNearestDrivers'])->name('drivers.nearest');

    Route::resource('clients', ClientController::class);
    Route::get('/clients_export', [ClientController::class, 'exportCSV'])->name('clients.export');

    Route::resource('orders', OrderController::class);
    Route::post('/rating/{order}', [OrderController::class, 'rateOrder'])->name('rate.order');
    Route::get('/check/rating/{order}', [OrderController::class, 'checkOrderRating'])->name('check.rating');

    Route::get('ordertracingapi/{order}', [OrderController::class, 'orderLocationAPI']);
    Route::get('liveapi', [OrderController::class, 'liveapi']);
    Route::get('driverlocations', [DriverController::class, 'driverlocations']);
    Route::get('restaurantslocations', [RestorantController::class, 'restaurantslocations']);

    Route::get('live', [OrderController::class, 'live'])->middleware('isOwnerOnPro');
    Route::get('/updatestatus/{alias}/{order}', [OrderController::class, 'updateStatus'])->name('update.status');

    Route::resource('settings', SettingsController::class);
    Route::get('apps', [AppsController::class, 'index'])->name('apps.index');
    Route::get('appremove/{alias}', [AppsController::class, 'remove'])->name('apps.remove');
    Route::post('apps', [AppsController::class, 'store'])->name('apps.store');
    Route::get('cloudupdate', [SettingsController::class, 'cloudupdate'])->name('settings.cloudupdate');
    Route::get('systemstatus', [SettingsController::class, 'systemstatus'])->name('systemstatus');
    Route::get('translatemenu', [SettingsController::class, 'translateMenu'])->name('translatemenu');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'password'])->name('profile.password');

    Route::resource('items', ItemsController::class)->middleware('isOwnerOnPro');
    Route::prefix('items')->name('items.')->group(function () {
        Route::get('reorder/{up}', [ItemsController::class, 'reorderCategories'])->name('reorder');
        Route::get('list/{restorant}', [ItemsController::class, 'indexAdmin'])->name('admin');

        // Options
        Route::get('options/{item}', [Items\OptionsController::class, 'index'])->name('options.index');
        Route::get('options/{option}/edit', [Items\OptionsController::class, 'edit'])->name('options.edit');
        Route::get('options/{item}/create', [Items\OptionsController::class, 'create'])->name('options.create');
        Route::post('options/{item}', [Items\OptionsController::class, 'store'])->name('options.store');
        Route::put('options/{option}', [Items\OptionsController::class, 'update'])->name('options.update');
        Route::get('options/del/{option}', [Items\OptionsController::class, 'destroy'])->name('options.delete');

        // Variants
        Route::get('variants/{item}', [Items\VariantsController::class, 'index'])->name('variants.index');
        Route::get('variants/{variant}/edit', [Items\VariantsController::class, 'edit'])->name('variants.edit');
        Route::get('variants/{item}/create', [Items\VariantsController::class, 'create'])->name('variants.create');
        Route::post('variants/{item}', [Items\VariantsController::class, 'store'])->name('variants.store');
        Route::put('variants/{variant}', [Items\VariantsController::class, 'update'])->name('variants.update');

        Route::get('variants/del/{variant}', [Items\VariantsController::class, 'destroy'])->name('variants.delete');
    });

    Route::post('/import/items', [ItemsController::class, 'import'])->name('import.items');
    Route::post('/item/change/{item}', [ItemsController::class, 'change']);
    Route::post('/{item}/extras', [ItemsController::class, 'storeExtras'])->name('extras.store');
    Route::post('/{item}/extras/edit', [ItemsController::class, 'editExtras'])->name('extras.edit');
    Route::delete('/{item}/extras/{extras}', [ItemsController::class, 'deleteExtras'])->name('extras.destroy');

    Route::resource('categories', CategoriesController::class);

    Route::resource('addresses', AddressControler::class);
    Route::get('/new/address/autocomplete', [AddressControler::class, 'newAddressAutocomplete']);
    Route::post('/new/address/details', [AddressControler::class, 'newAdressPlaceDetails']);
    Route::post('/address/delivery', [AddressControler::class, 'AddressInDeliveryArea']);

    Route::post('/change/{page}', [PagesController::class, 'change'])->name('changes');

    Route::post('ckeditor/image_upload', [CKEditorController::class, 'upload'])->name('upload');
    Route::get('/payment', [PaymentController::class, 'view'])->name('payment.view');

    if (config('app.isft')) {
        Route::get('/cart-checkout', [CartController::class, 'cart'])->middleware('verifiedphone')->name('cart.checkout');
    }

    Route::resource('plans', PlansController::class);
    Route::get('/plan', [PlansController::class, 'current'])->name('plans.current');
    Route::post('/subscribe/plan', [PlansController::class, 'subscribe'])->name('plans.subscribe');
    Route::get('/subscribe/cancel', [PlansController::class, 'cancelStripeSubscription'])->name('plans.cancel');
    Route::get('/subscribe/plan3d/{plan}/{user}', [PlansController::class, 'subscribe3dStripe'])->name('plans.subscribe_3d_stripe');
    Route::post('/subscribe/update', [PlansController::class, 'adminupdate'])->name('update.plan');

    Route::get('qr', [QRController::class, 'index'])->name('qr');

    Route::post('/pay', [PaymentController::class, 'redirectToGateway'])->name('pay');
    Route::get('/payment/callback', [PaymentController::class, 'handleGatewayCallback']);

    Route::get('/share/menu', [RestorantController::class, 'shareMenu'])->name('share.menu');
    Route::get('/downloadqr', [RestorantController::class, 'downloadQR'])->name('download.menu');
});

if (config('app.isqrsaas')) {
    Route::get('/cart-checkout', [CartController::class, 'cart'])->name('cart.checkout');
    Route::get('/guest-orders', [OrderController::class, 'guestOrders'])->name('guest.orders');
    Route::post('/whatsapp/store', [OrderController::class, 'storeWhatsappOrder'])->name('whatsapp.store');
}

Route::get('/handleOrderPaymentStripe/{order}', [PaymentController::class, 'handleOrderPaymentStripe'])->name('handle.order.payment.stripe');

Route::get('/get/rlocation/{restaurant}', [RestorantController::class, 'getLocation']);
Route::get('/footer-pages', [PagesController::class, 'getPages']);
Route::get('/cart-getContent', [CartController::class, 'getContent'])->name('cart.getContent');
Route::get('/cart-getContent-POS', [CartController::class, 'getContentPOS'])->name('cart.getContentPOS');
Route::post('/cart-add', [CartController::class, 'add'])->name('cart.add');
Route::post('/cart-remove', [CartController::class, 'remove'])->name('cart.remove');
Route::get('/cart-update', [CartController::class, 'update'])->name('cart.update');
Route::get('/cartinc/{item}', [CartController::class, 'increase'])->name('cart.increase');
Route::get('/cartdec/{item}', [CartController::class, 'decrease'])->name('cart.decrease');

Route::post('/order', [OrderController::class, 'store'])->name('order.store');

Route::resource('pages', PagesController::class);
Route::get('/blog/{slug}', [PagesController::class, 'blog'])->name('blog');

Route::get('/login/google', [LoginController::class, 'googleRedirectToProvider'])->name('google.login');
Route::get('/login/google/redirect', [LoginController::class, 'googleHandleProviderCallback']);

Route::get('/login/facebook', [LoginController::class, 'facebookRedirectToProvider'])->name('facebook.login');
Route::get('/login/facebook/redirect', [LoginController::class, 'facebookHandleProviderCallback']);

Route::get('/new/'.config('settings.url_route').'/register', [RestorantController::class, 'showRegisterRestaurant'])->name('newrestaurant.register');
Route::get('/new', [RestorantController::class, 'showRegisterRestaurant'])->name('newcompany.register');

Route::post('/new/restaurant/register/store', [RestorantController::class, 'storeRegisterRestaurant'])->name('newrestaurant.store');

Route::get('phone/verify', [PhoneVerificationController::class, 'show'])->name('phoneverification.notice');
Route::post('phone/verify', [PhoneVerificationController::class, 'verify'])->name('phoneverification.verify');

Route::get('/get/rlocation/{restorant}', [RestorantController::class, 'getLocation']);
Route::get('/items/variants/{variant}/extras', [Items\VariantsController::class, 'extras'])->name('items.variants.extras');

//Languages routes
$availableLanguagesENV = ENV('FRONT_LANGUAGES', 'EN,English,IT,Italian,FR,French,DE,German,ES,Spanish,RU,Russian,PT,Portuguese,TR,Turkish,ar,Arabic');
$exploded = explode(',', $availableLanguagesENV);
if (count($exploded) > 3) {

    $mode = 'qrsaasMode';
    if (config('settings.landing_to_use') != 'system') {
        if (config('settings.landing_to_use') == 'whatsapp') {
            $mode = 'whatsappMode';
        } elseif (config('settings.landing_to_use') == 'pos') {
            $mode = 'posMode';
        }
    } else {
        if (config('settings.is_whatsapp_ordering_mode')) {
            $mode = 'whatsappMode';
        }
        if (config('settings.is_pos_cloud_mode')) {
            $mode = 'posMode';
        }
        if (config('app.issd')) {
            $mode = 'taxiMode';
        }
    }
    if (config('app.isft')) {
        $mode = 'index';
    }

    for ($i = 0; $i < count($exploded); $i += 2) {

        Route::get('/'.strtolower($exploded[$i]), FrontEndController::class.'@'.$mode)->name('lang.'.strtolower($exploded[$i]));
    }
}

Route::get('register/visit/{restaurant_id}', [VisitsController::class, 'register'])->name('register.visit');
Route::post('register/visit', [VisitsController::class, 'registerstore'])->name('register.visit.store');

//Call Waiter
Route::post('call/waiter/', [RestorantController::class, 'callWaiter'])->name('call.waiter');

//Register driver
Route::get('new/driver/register', [DriverController::class, 'register'])->name('driver.register');
Route::post('new/driver/register/store', [DriverController::class, 'registerStore'])->name('driver.register.store');

Route::get('order/success', [OrderController::class, 'success'])->name('order.success');
Route::get('order/successwhatsapp/{order}', [OrderController::class, 'silentWhatsAppRedirect'])->name('order.successwhatsapp');

Route::get('order/cancel', [OrderController::class, 'cancel'])->name('order.cancel');

Route::post('/fb-order', [OrderController::class, 'fbOrderMsg'])->name('fb.order');

Route::get('onboarding', [FrontEndController::class, 'onboarding'])->name('sd.onboarding');

Route::get('/{alias}', [FrontEndController::class, 'restorant'])->where('alias', '.*')->name('vendrobyalias');
