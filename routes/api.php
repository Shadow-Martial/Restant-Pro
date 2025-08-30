<?php

use App\Http\Controllers\API;
use App\Http\Controllers\DriverController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes  V1 /api
|--------------------------------------------------------------------------
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:api')->group(function () {
    //Driver - Private
    Route::get('/driverorders', [DriverController::class, 'getOrders'])->name('driver.orders');
    Route::get('/updateorderstatus/{order}/{status}', [DriverController::class, 'updateOrderStatus'])->name('driver.updateorderstatus');
    Route::get('/updateorderlocation/{order}/{lat}/{lng}', [DriverController::class, 'orderTracking'])->name('driver.updateorderlocation');
    Route::get('/rejectorder/{order}', [DriverController::class, 'rejectOrder'])->name('driver.rejectorder');
    Route::get('/acceptorder/{order}', [DriverController::class, 'acceptOrder'])->name('driver.acceptorder');
    Route::get('/driveronline', [DriverController::class, 'goOnline'])->name('driver.goonline');
    Route::get('/drveroffline', [DriverController::class, 'goOffline'])->name('driver.gooffline');
});

//Driver - Public
Route::post('/drivergettoken', [DriverController::class, 'getToken'])->name('driver.getToken');

/*
|--------------------------------------------------------------------------
| API Routes  V2 /api/v2/
|--------------------------------------------------------------------------
|
*/

//DRIVER
Route::prefix('v2/driver')->group(function () {
    /**
     * AUTH
     */
    //Auth /api/v2/driver/auth
    Route::prefix('auth')->name('driver.auth.')->group(function () {
        Route::post('gettoken', [API\Driver\AuthController::class, 'getToken'])->name('getToken');
        Route::post('register', [API\Driver\AuthController::class, 'register'])->name('register');
        Route::middleware('auth:api')->group(function () {
            Route::get('data', [API\Driver\AuthController::class, 'getUseData'])->name('getUseData');
            Route::get('deactivate', [API\Driver\AuthController::class, 'deactivate'])->name('deactivate');
            Route::get('driveronline', [API\Driver\AuthController::class, 'goOnline'])->name('goonline');
            Route::get('drveroffline', [API\Driver\AuthController::class, 'goOffline'])->name('gooffline');
        });
    });

    /**
     * Settings - uses the same from client
     */
    //Settings /api/v2/driver/settings
    Route::prefix('settings')->name('driver.settings.')->group(function () {
        Route::get('/', [API\Client\SettingsController::class, 'index'])->name('indexapi');
    });

    //NEEDS AUTHENTICATION
    Route::middleware('auth:api')->group(function () {

        /**
         * ORDERS
         */

        //Orders /api/v2/client/orders
        Route::prefix('orders')->name('driver.orders.')->group(function () {
            Route::get('/', [API\Driver\OrdersController::class, 'index']);
            Route::get('/with_latlng/{lat}/{lng}', [API\Driver\OrdersController::class, 'odersWithLatLng']);
            Route::get('/order/{order}', [API\Driver\OrdersController::class, 'order']);
            Route::get('earnings', [API\Driver\OrdersController::class, 'earnings']);
            Route::get('updateorderdeliveryprice/{order}/{deliveryprice}', [API\Driver\OrdersController::class, 'updateOrderDeliveryPrice'])->name('driver.updateorderdeliveryprice');
            Route::get('updateorderstatus/{order}/{status}', [API\Driver\OrdersController::class, 'updateOrderStatus'])->name('driver.updateorderstatus');
            Route::get('updateorderlocation/{order}/{lat}/{lng}', [API\Driver\OrdersController::class, 'orderTracking'])->name('driver.updateorderlocation');
            Route::get('rejectorder/{order}', [API\Driver\OrdersController::class, 'rejectOrder'])->name('driver.rejectorder');
            Route::get('acceptorder/{order}', [API\Driver\OrdersController::class, 'acceptOrder'])->name('driver.acceptorder');
        });
    });

});

//Vendor
Route::prefix('v2/vendor')->group(function () {
    /**
     * AUTH
     */
    //Auth /api/v2/vendor/auth
    Route::prefix('auth')->name('vendor.auth.')->group(function () {
        Route::post('gettoken', [API\Vendor\AuthController::class, 'getToken'])->name('getToken');
        Route::post('register', [API\Vendor\AuthController::class, 'register'])->name('register');
        Route::middleware('auth:api')->group(function () {
            Route::get('data', [API\Vendor\AuthController::class, 'getUseData'])->name('getUseData');
            Route::get('deactivate', [API\Vendor\AuthController::class, 'deactivate'])->name('deactivate');
        });
    });

    /**
     * Settings - uses the same from client
     */
    //Settings /api/v2/vendor/settings
    Route::prefix('settings')->name('vendor.settings.')->group(function () {
        Route::get('/', [API\Client\SettingsController::class, 'index'])->name('indexapivendor');
    });

    //NEEDS AUTHENTICATION
    Route::middleware('auth:api')->group(function () {

        /**
         * ORDERS
         */

        //Orders /api/v2/client/orders
        Route::prefix('orders')->name('vendor.orders.')->group(function () {
            Route::get('/', [API\Vendor\OrdersController::class, 'index']);
            Route::get('/order/{order}', [API\Vendor\OrdersController::class, 'order']);
            Route::get('earnings', [API\Vendor\OrdersController::class, 'earnings']);
            Route::get('updateorderstatus/{order}/{status}', [API\Vendor\OrdersController::class, 'updateOrderStatus'])->name('vendor.updateorderstatus');
            Route::get('updateorderlocation/{order}/{lat}/{lng}', [API\Vendor\OrdersController::class, 'orderTracking'])->name('vendor.updateorderlocation');
            Route::get('rejectorder/{order}', [API\Vendor\OrdersController::class, 'rejectOrder'])->name('vendor.rejectorder');
            Route::get('acceptorder/{order}', [API\Vendor\OrdersController::class, 'acceptOrder'])->name('vendor.acceptorder');
        });
    });

});

//CLIENT
Route::prefix('v2/client')->group(function () {

    /**
     * AUTH
     */
    //Auth /api/v2/client/auth
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('gettoken', [API\Client\AuthController::class, 'getToken'])->name('getToken');
        Route::post('register', [API\Client\AuthController::class, 'register'])->name('register');
        Route::post('loginfb', [API\Client\AuthController::class, 'loginFacebook']);
        Route::post('logingoogle', [API\Client\AuthController::class, 'loginGoogle']);
        Route::middleware('auth:api')->group(function () {
            Route::get('data', [API\Client\AuthController::class, 'getUseData'])->name('getUseData');
            Route::get('deactivate', [API\Client\AuthController::class, 'deactivate'])->name('deactivate');
        });
    });

    /**
     * Settings
     */
    //Settings /api/v2/client/settings
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [API\Client\SettingsController::class, 'index'])->name('indexapiclient');
    });

    /**
     * VENDOR
     */

    //Vendor /api/v2/client/vendor
    Route::prefix('vendor')->name('vendor.')->group(function () {
        Route::get('cities', [API\Client\VendorController::class, 'getCities'])->name('cities');
        Route::get('list/{city_id}', [API\Client\VendorController::class, 'getVendors'])->name('list');
        Route::get('{id}/items', [API\Client\VendorController::class, 'getVendorItems'])->name('items');
        Route::get('{id}/hours', [API\Client\VendorController::class, 'getVendorHours'])->name('hours');
        Route::get('/deliveryfee/{res}/{adr}', [API\Client\VendorController::class, 'getDeliveryFee'])->name('delivery.fee');
    });

    //NEEDS AUTHENTICATION
    Route::middleware('auth:api')->group(function () {

        /**
         * ORDERS
         */

        //Orders /api/v2/client/orders
        Route::prefix('orders')->name('orders.')->group(function () {
            Route::get('/', [API\Client\OrdersController::class, 'index']);
            Route::post('/', [API\Client\OrdersController::class, 'store'])->name('storeapi');
        });

        /**
         * Addresses
         */

        //Addresses /api/v2/client/addresses
        Route::prefix('addresses')->name('orders.')->group(function () {
            Route::get('/', [API\Client\AddressController::class, 'getMyAddresses']);
            Route::get('/fees/{restaurant_id}', [API\Client\AddressController::class, 'getMyAddressesWithFees']);
            Route::post('/', [API\Client\AddressController::class, 'makeAddress'])->name('make.address');
            Route::post('/delete', [API\Client\AddressController::class, 'deleteAddress'])->name('delete.address');
        });

        /**
         * Notifications
         */

        //Notifications /api/v2/client/notifications
        Route::prefix('notifications')->name('orders.')->group(function () {
            Route::get('/', [API\Client\NotificationsController::class, 'index']);
        });

    });
});

//KDS
Route::prefix('v2/kds')->group(function () {

    /**
     * ORDERS
     */
    //Auth /api/v2/kds/orders
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::middleware('auth:api')->group(function () {
            Route::get('/{finished}', [API\KDS\OrdersController::class, 'index'])->name('index');
            Route::get('/finishItem/{orderid}/{itemId}/{isDBtypeOrder}', [API\KDS\OrdersController::class, 'finishItem'])->name('finishItem');
            Route::get('/unfinishItem/{orderid}/{itemId}/{isDBtypeOrder}', [API\KDS\OrdersController::class, 'unfinishItem'])->name('unfinishItem');
            Route::get('/finishOrder/{orderid}/{isDBtypeOrder}', [API\KDS\OrdersController::class, 'finishOrder'])->name('finishOrder');
            Route::get('/unfinishOrder/{orderid}/{isDBtypeOrder}', [API\KDS\OrdersController::class, 'unfinishOrder'])->name('unfinishOrder');
        });
    });
});
