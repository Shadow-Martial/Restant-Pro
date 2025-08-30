<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class QRController extends Controller
{
    public function index(): View
    {
        $vendor = auth()->user()->restorant;
        $linkToTheMenu = $vendor->getLinkAttribute();

        $areas = $vendor->areas()->with('tables')->get()->toArray();
        $tables = [];
        foreach ($areas as $key => $area) {
            foreach ($area['tables'] as $table) {
                $tables[$table['id']] = $area['name'].' - '.$table['name'];
            }
        }

        $dataToPass = [
            'url' => $linkToTheMenu,
            'titleGenerator' => __('Restaurant QR Generators'),
            'selectQRStyle' => __('SELECT QR STYLE'),
            'selectQRColor' => __('SELECT QR COLOR'),
            'color1' => __('Color 1'),
            'color2' => __('Color 2'),
            'titleDownload' => __('QR Downloader'),
            'selectTable' => __('Select Table'),
            'tables' => $tables,
            'allTables' => __('No specific table'),
            'downloadJPG' => __('Download JPG'),
            'titleTemplate' => __('Menu Print template'),
            'downloadPrintTemplates' => __('Download Print Templates'),
            'templates' => explode(',', config('settings.templates')),
            'linkToTemplates' => env('linkToTemplates', '/impactfront/img/templates.zip'),
        ];

        return view('qrsaas.qrgen')->with('data', json_encode($dataToPass));
    }
}
