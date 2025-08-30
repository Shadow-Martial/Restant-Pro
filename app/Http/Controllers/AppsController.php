<?php

namespace App\Http\Controllers;

use Akaunting\Module\Facade as Module;
use App\Models\Company;
use App\Traits\Fields;
use App\Traits\Modules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;
use ZipArchive;

class AppsController extends Controller
{
    use Fields;
    use Modules;

    public function ownerApps(): View
    {

        $company = $this->getCompany();

        //App fields
        $rawFields = $this->vendorFields($company->getAllConfigs());
        //Stripe fields
        if (config('settings.stripe_useVendor')) {
            array_push($rawFields, [
                'separator' => 'Stripe configuration',
                'title' => 'Enable Stripe for payments when ordering',
                'key' => 'stripe_enable',
                'ftype' => 'bool',
                'value' => $company->getConfig('stripe_enable', 'false'),
                'onlyin' => 'qrsaas',
            ], [
                'title' => 'Stripe key',
                'key' => 'stripe_key',
                'value' => $company->getConfig('stripe_key', ''),
                'onlyin' => 'qrsaas',
            ],
                [
                    'title' => 'Stripe secret',
                    'key' => 'stripe_secret',
                    'value' => $company->getConfig('stripe_secret', ''),
                    'onlyin' => 'qrsaas',
                ]);
        }

        $appFields = $this->convertJSONToFields($rawFields);

        $vendorModules = [];
        foreach (Module::all() as $key => $module) {
            if ($module->get('isVendorModule')) {
                array_push($vendorModules, $module->get('alias'));
            }
        }

        $separators = [];
        $icons = [
            'allergens' => 'fas fa-allergies',
            'clients' => 'fas fa-users',
            'ride_tariff' => 'fas fa-car',
            'coupons' => 'fas fa-ticket-alt',
            'tax_config' => 'fas fa-file-invoice-dollar',
            'delivery_areas' => 'fas fa-map-marked-alt',
            'detrack_configuration' => 'fas fa-cog',
            'custom_domain' => 'fas fa-globe',
            'drivers' => 'fas fa-user-check',
            'notifications' => 'fas fa-bell',
            'expenses' => 'fas fa-money-check-alt',
            'fields' => 'fas fa-th',
            'numéro_de_inscription' => 'fas fa-id-card',
            'google_analytics' => 'fab fa-google',
            'google_translate' => 'fab fa-google',
            'impressum' => 'fas fa-info-circle',
            'kitchen_display_system' => 'fas fa-tv',
            'manager' => 'fas fa-user-tie',
            'notes' => 'fas fa-sticky-note',
            'order_date_time' => 'fas fa-calendar-alt',
            'p_o_s' => 'fas fa-tv',
            'print_node' => 'fas fa-print',
            'send_status_templates' => 'fas fa-envelope',
            'social_networks_links' => 'fas fa-share-alt',
            'staff' => 'fas fa-user-friends',
            'stock_settings' => 'fas fa-boxes',
            'theme_switcher' => 'fas fa-palette',
            'custom_timezone' => 'fas fa-clock',
            'email_order_notifications' => 'fas fa-mail-bulk',
            'weather_configuration' => 'fas fa-cloud',
            'web_hooks' => 'fas fa-link'];
        try {
            foreach ($appFields as $key => $field) {
                if ($field['separator']) {
                    $snake = Str::snake($field['separator']);
                    if (isset($field['icon'])) {
                        $icon = $field['icon'];
                    } elseif (isset($icons[$snake])) {
                        $icon = $icons[$snake];
                    } else {
                        $icon = 'fas fa-cog';
                    }
                    $field['snake'] = $snake;
                    //Check if separators is empty array

                    array_push($separators, ['icon' => $icon, 'name' => $field['separator'], 'snake' => $snake]);
                    $separators[count($separators) - 1]['fields'][] = $field;

                } else {
                    //Get the last separator
                    $snake = $separators[count($separators) - 1]['snake'];
                    $field['snake'] = $snake;
                    $separators[count($separators) - 1]['fields'][] = $field;
                }
            }
        } catch (\Throwable $th) {
            if (config('app.debug')) {
                dd($th);
            }
        }

        return view('apps.company', [
            'company' => $company,
            'separators' => $separators,
        ]);
    }

    public function updateApps(Request $request, Company $company): RedirectResponse
    {
        //Update custom fields
        if ($request->has('custom')) {
            $company->setMultipleConfig($request->custom);
        }

        return redirect()->route('admin.owner.apps')->withStatus(__('Company app settings successfully updated.'));
    }

    public function index(): View
    {

        $this->adminOnly();

        //1. Get all available apps
        //$appsLink="https://raw.githubusercontent.com/mobidonia/foodtigerapps/main/apps30.json";
        $appsLink = 'https://apps.poslion.com?version='.config('version.version');

        $installed = [];
        foreach (Module::all() as $key => $module) {
            array_push($installed, $module->alias);

        }
        $installedAsString = implode(',', $installed);
        $appsLink .= '&installed='.$installedAsString;

        //Code
        $appsLink .= '&code='.config('settings.extended_license_download_code');
        $response = (new \GuzzleHttp\Client())->get($appsLink);

        $rawApps = [];
        if ($response->getStatusCode() == 200) {
            $rawApps = json_decode($response->getBody());
        }

        //2. Merge info
        foreach ($rawApps as $key => &$app) {
            $app->installed = Module::has($app->alias);
            if ($app->installed) {
                $app->version = Module::get($app->alias)->get('version');
                if ($app->version == '') {
                    $app->version = '1.0';
                }

                //Check if app needs update
                if ($app->latestVersion) {
                    $app->updateAvailable = $app->latestVersion != $app->version.'';
                } else {
                    $app->updateAvailable = false;
                }

            }
            if (! isset($app->category)) {
                $app->category = ['tools'];
            }
        }

        //Filter apps by type
        $apps = [];
        $newRawApps = unserialize(serialize($rawApps));
        foreach ($newRawApps as $key => $app) {
            if (isset($app->rule) && $app->rule) {
                $rules = explode(',', $app->rule);
                $alreadyAdded = false;
                foreach ($rules as $keyrule => $rule) {
                    if (! $alreadyAdded && config('app.'.$rule)) {
                        $alreadyAdded = true;
                        array_push($apps, $app);
                    }
                }
            } else {
                $alreadyAdded = true;
                array_push($apps, $app);
            }

            //remove
            if ($alreadyAdded && isset($app->rulenot) && $app->rulenot) {
                $alreadyRemoved = false;
                $rulesNot = explode(',', $app->rulenot);
                //dd( $rulesNot);
                foreach ($rulesNot as $keyrulnot => $rulenot) {
                    if (! $alreadyRemoved && config('app.'.$rulenot)) {
                        $alreadyRemoved = true;
                        array_pop($apps);
                    }
                }
            }
        }

        //3. Return view
        return view('apps.index', compact('apps'));

    }

    public function remove($alias): RedirectResponse
    {
        if (! auth()->user()->hasRole('admin') || strlen($alias) < 2 || (config('settings.is_demo') || config('settings.is_demo'))) {
            abort(404);
        }
        $destination = Module::get($alias)->getPath();
        if (File::exists($destination)) {
            File::deleteDirectory($destination);

            return redirect()->route('apps.index')->withStatus(__('Removed'));
        } else {
            abort(404);
        }
    }

    public function store(Request $request): RedirectResponse
    {

        $path = $request->appupload->storeAs('appupload', $request->appupload->getClientOriginalName());

        $fullPath = storage_path('app/'.$path);
        $zip = new ZipArchive;

        if ($zip->open($fullPath)) {

            //Modules folder - for plugins
            $destination = public_path('../modules');
            $message = __('App is installed');

            //If it is language pack
            if (strpos($fullPath, '_lang') !== false) {
                $destination = public_path('../lang');
                $message = __('Language pack is installed');
            }else if(strpos($fullPath, '_update') !== false){
                $destination = public_path('../');
                $message = __('Update is installed. Please go to settings.');
            }

            // Extract file
            $zip->extractTo($destination);

            // Close ZipArchive
            $zip->close();

            return redirect()->route('apps.index')->withStatus($message);
        } else {
            return redirect(route('apps.index'))->withError(__('There was an error on app install. Please try manual install'));
        }
    }
}
