<?php

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application. This value is used when the
    | framework needs to place the application's name in a notification or
    | any other location as required by the application or its packages.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Type
    |--------------------------------------------------------------------------
    |
    | To organize the multiple projects into single git repo, we user projecttype
    | ft
    | qrsaas
    |
    */

    'projecttype' => env('APP_PROJECT_TYPE', 'ft'),
    'isqrsaas' => env('APP_PROJECT_TYPE', 'ft') == 'qrsaas',
    'iswp' => env('IS_WHATSAPP_ORDERING_MODE', false),
    'isft' => env('APP_PROJECT_TYPE', 'ft') == 'ft',
    'ispc' => env('IS_POS_CLOUD_MODE', false),
    'isag' => env('IS_AGRIS_MODE', false),
    'isloyalty' => env('IS_LOYALTY_MODE', false),
    'issd' => env('IS_SOCIAL_DRIVE_MODE', false) && env('APP_PROJECT_TYPE', 'ft') == 'qrsaas',
    'isdrive' => env('IS_SOCIAL_DRIVE_MODE', false) && env('APP_PROJECT_TYPE', 'ft') == 'ft',
    'isqrexact' => env('APP_PROJECT_TYPE', 'ft') == 'qrsaas' && ! (env('IS_POS_CLOUD_MODE', false) || env('IS_WHATSAPP_ORDERING_MODE', false) || env('IS_AGRIS_MODE', false) || env('IS_SOCIAL_DRIVE_MODE', false) || env('IS_LOYALTY_MODE', false)),
    'ordering' => env('APP_PROJECT_TYPE', 'ft') != 'qrsaas' || env('APP_PROJECT_TYPE', 'ft') == 'qrsaas' && ! env('QRSAAS_DISABLE_ODERING', false),

    /*
    |--------------------------------------------------------------------------
    | SETUP
    |--------------------------------------------------------------------------
    |
    */
    'images_upload_path' => env('IMAGES_UPLOAD_PATH', 'uploads/companies/'),
    'ignore_subdomains' => explode(',', env('IGNORE_SUBDOMAINS', 'www')),
    'order_approve_directly' => env('APP_ORDER_APPROVE_DIRECTLY', false),
    'allow_self_deliver' => env('APP_ALLOW_SELF_DELIVER', false),

    'twilio' => [
        'TWILIO_AUTH_TOKEN' => env('TWILIO_AUTH_TOKEN'),
        'TWILIO_ACCOUNT_SID' => env('TWILIO_ACCOUNT_SID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => env('APP_DEBUG', false),
    'debug_blacklist' => [
        '_COOKIE' => array_keys($_COOKIE),
        '_SERVER' => array_keys($_SERVER),
        '_ENV' => array_keys($_ENV),
    ],

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | your application so that it is used when running Artisan tasks.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    'asset_url' => env('ASSET_URL', null),
    'company_images' => env('COMPANY_IMAGES_PATH', '/uploads/restorants/'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. We have gone
    | ahead and set this to a sensible default for you out of the box.
    |
    */

    'timezone' => env('TIME_ZONE', 'UTC'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by the translation service provider. You are free to set this value
    | to any of the locales which will be supported by the application.
    |
    */

    'locale' => strtolower(env('APP_LOCALE', 'en')),

    /*
    |--------------------------------------------------------------------------
    | Application Fallback Locale
    |--------------------------------------------------------------------------
    |
    | The fallback locale determines the locale to use when the current one
    | is not available. You may change the value to correspond to any of
    | the language folders that are provided through your application.
    |
    */

    'fallback_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Faker Locale
    |--------------------------------------------------------------------------
    |
    | This locale will be used by the Faker PHP library when generating fake
    | data for your database seeds. For example, this will be used to get
    | localized telephone numbers, street address information and more.
    |
    */

    'faker_locale' => 'en_US',

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is used by the Illuminate encrypter service and should be set
    | to a random, 32 character string, otherwise these encrypted strings
    | will not be safe. Please do this before deploying an application!
    |
    */

    'key' => env('APP_KEY'),

    'cipher' => 'AES-256-CBC',

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => 'file',
        // 'store'  => 'redis',
    ],

    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    |
    | The service providers listed here will be automatically loaded on the
    | request to your application. Feel free to add your own services to
    | this array to grant expanded functionality to your applications.
    |
    */

    'providers' => ServiceProvider::defaultProviders()->merge([
        JoeDixon\Translation\TranslationServiceProvider::class,
        Akaunting\Money\Provider::class,
        Intervention\Image\ImageServiceProvider::class,
        Biscolab\ReCaptcha\ReCaptchaServiceProvider::class,

        /*
         * Package Service Providers...
         */
        Darryldecode\Cart\CartServiceProvider::class,
        /*
         * Application Service Providers...
         */
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        App\Providers\TranslationServiceProvider::class,
        // App\Providers\BroadcastServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
        Maatwebsite\Excel\ExcelServiceProvider::class,
        Spatie\Geocoder\GeocoderServiceProvider::class,
        Spatie\CookieConsent\CookieConsentServiceProvider::class,
        Spatie\EloquentSortable\EloquentSortableServiceProvider::class,
        Unicodeveloper\Paystack\PaystackServiceProvider::class,
        Mckenziearts\Notify\LaravelNotifyServiceProvider::class,
    ])->toArray(),

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    |
    | This array of class aliases will be registered when this application
    | is started. However, feel free to register as many as you wish as
    | the aliases are "lazy" loaded so they don't hinder performance.
    |
    */

    'aliases' => Facade::defaultAliases()->merge([
        'Cart' => Darryldecode\Cart\Facades\CartFacade::class,
        'Excel' => Maatwebsite\Excel\Facades\Excel::class,
        'Geocoder' => Spatie\Geocoder\Facades\Geocoder::class,
        'Image' => Intervention\Image\ImageManagerStatic::class,
        'Paystack' => Unicodeveloper\Paystack\Facades\Paystack::class,
        'Pusher' => Pusher\Pusher::class,
        'ReCaptcha' => Biscolab\ReCaptcha\Facades\ReCaptcha::class,
        'Redis' => Illuminate\Support\Facades\Redis::class
    ])->toArray(),

];
