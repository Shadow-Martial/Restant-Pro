<?php

namespace App\Http\Controllers;

use App\Models\Features;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeaturesController extends Controller
{
    protected $imagePath = 'uploads/restorants/';

    private function validateAccess()
    {
        if (! auth()->user()->hasRole('admin')) {
            abort(404);
        }
    }

    private function getFields()
    {
        return [
            ['ftype' => 'image', 'name' => __('Feature image ( 128x128 )'), 'id' => 'image'],
            ['ftype' => 'input', 'name' => 'Title', 'id' => 'title', 'placeholder' => __('Enter title'), 'required' => true],
            ['ftype' => 'input', 'name' => 'Description', 'id' => 'description', 'placeholder' => __('Enter description'), 'required' => true],
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $this->validateAccess();

        return view('landing.features.index', ['setup' => [
            'iscontent' => true,
            'title' => __('Features'),
            'action_link' => route('admin.landing.features.create'),
            'action_name' => __('Add new feature'),
            'items' => Features::where('post_type', 'feature')->get(),
            'item_names' => 'features',
            'breadcrumbs' => [
                [__('Landing Page'), route('admin.landing')],
                [__('Features'), route('admin.landing.features.index')],
            ],
        ]]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $this->validateAccess();

        return view('general.form', ['setup' => [
            'title' => __('New feature'),
            'action_link' => route('admin.landing.features.index'),
            'action_name' => __('Back'),
            'iscontent' => true,
            'action' => route('admin.landing.features.store'),
            'breadcrumbs' => [
                [__('Landing Page'), route('admin.landing')],
                [__('Features'), route('admin.landing.features.index')],
                [__('New'), null],
            ],
        ],
            'fields' => $this->getFields(), ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->validateAccess();
        //Validate first
        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        $feature = Features::create([
            'post_type' => 'feature',
            'title' => $request->title,
            'description' => $request->description,
            'image' => '',
        ]);

        $feature->save();

        if ($request->hasFile('image')) {
            $feature->image = $this->saveImageVersions(
                $this->imagePath,
                $request->image,
                [
                    ['name' => 'large', 'w' => 128, 'h' => 128],
                ]
            );
            $feature->update();
        }

        return redirect()->route('admin.landing.features.index')->withStatus(__('Feature was added'));
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function show(Features $features)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Features  $features
     */
    public function edit(Features $feature): View
    {
        $this->validateAccess();
        $fields = $this->getFields();
        $fields[0]['value'] = $feature->image_link;
        $fields[1]['value'] = $feature->title;
        $fields[2]['value'] = $feature->description;

        return view('general.form', ['setup' => [
            'title' => __('Edit this feature'),
            'action_link' => route('admin.landing.features.index'),
            'action_name' => __('Back'),
            'iscontent' => true,
            'isupdate' => true,
            'action' => route('admin.landing.features.update', ['feature' => $feature->id]),
            'breadcrumbs' => [
                [__('Landing Page'), route('admin.landing')],
                [__('Features'), route('admin.landing.features.index')],
                [$feature->id, null],
            ],
        ],
            'fields' => $fields, ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Models\Features  $features
     */
    public function update(Request $request, Features $feature): RedirectResponse
    {
        $this->validateAccess();

        $feature->title = $request->title;
        $feature->description = $request->description;

        if ($request->hasFile('image')) {
            $feature->image = $this->saveImageVersions(
                $this->imagePath,
                $request->image,
                [
                    ['name' => 'large', 'w' => 128, 'h' => 128],
                ]
            );
        }

        $feature->update();

        return redirect()->route('admin.landing.features.index')->withStatus(__('Feature was updated'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Features  $features
     */
    public function destroy(Features $feature): RedirectResponse
    {
        $this->validateAccess();

        $feature->delete();

        return redirect()->route('admin.landing.features.index')->withStatus(__('Feature was deleted.'));
    }
}
