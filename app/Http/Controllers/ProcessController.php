<?php

namespace App\Http\Controllers;

use App\Models\Process;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProcessController extends Controller
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
        $elements = [
            ['ftype' => 'input', 'name' => 'Title', 'id' => 'title', 'placeholder' => __('Enter title'), 'required' => true],
            ['ftype' => 'input', 'name' => 'Description', 'id' => 'description', 'placeholder' => __('Enter description'), 'required' => true],
            ['ftype' => 'input', 'name' => 'Link Name', 'id' => 'link_name', 'placeholder' => __('Enter link name'), 'required' => false],
            ['ftype' => 'input', 'name' => 'Link ', 'id' => 'link', 'placeholder' => __('Enter link URL'), 'required' => false],
        ];

        array_push($elements, ['ftype' => 'input', 'name' => __('Subtitle'), 'id' => 'subtitle', 'required' => true, 'placeholder' => __('Enter subtitle')]);
        array_push($elements, ['ftype' => 'image', 'name' => __('Feature image'), 'id' => 'image']);

        return $elements;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $this->validateAccess();

        return view('landing.processes.index', ['setup' => [
            'iscontent' => true,
            'title' => __('Processes'),
            'action_link' => route('admin.landing.processes.create'),
            'action_name' => __('Add new process'),
            'items' => Process::where('post_type', 'process')->get(),
            'item_names' => 'processes',
            'breadcrumbs' => [
                [__('Landing Page'), route('admin.landing')],
                [__('Processes'), route('admin.landing.processes.index')],
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
            'title' => __('New process'),
            'action_link' => route('admin.landing.processes.index'),
            'action_name' => __('Back'),
            'iscontent' => true,
            'action' => route('admin.landing.processes.store'),
            'breadcrumbs' => [
                [__('Landing Page'), route('admin.landing')],
                [__('Processes'), route('admin.landing.processes.index')],
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
            'link' => ['required', 'string', 'max:255'],
            'link_name' => ['required', 'string', 'max:255'],
        ]);

        $process = Process::create([
            'post_type' => 'process',
            'title' => $request->title,
            'description' => $request->description,
            'link' => $request->link,
            'link_name' => $request->link_name,
        ]);

        $process->subtitle = $request->has('subtitle') ? $request->subtitle : '';

        if ($request->hasFile('image')) {
            $process->image = $this->saveImageVersions(
                $this->imagePath,
                $request->image,
                [
                    ['name' => 'large'],
                ]
            );

        }

        $process->save();

        return redirect()->route('admin.landing.processes.index')->withStatus(__('Process was added'));
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function show(Process $process)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Process $process): View
    {
        $this->validateAccess();
        $fields = $this->getFields();
        $fields[0]['value'] = $process->title;
        $fields[1]['value'] = $process->description;
        $fields[2]['value'] = $process->link;
        $fields[3]['value'] = $process->link_name;

        if (config('app.ispc')) {
            $fields[4]['value'] = $process->subtitle;
            $fields[5]['value'] = $process->image_link;
        }

        //dd($option);
        return view('general.form', ['setup' => [
            'title' => __('Edit this process'),
            'action_link' => route('admin.landing.processes.index'),
            'action_name' => __('Back'),
            'iscontent' => true,
            'isupdate' => true,
            'action' => route('admin.landing.processes.update', ['process' => $process->id]),
            'breadcrumbs' => [
                [__('Landing Page'), route('admin.landing')],
                [__('Processes'), route('admin.landing.processes.index')],
                [$process->id, null],
            ],
        ],
            'fields' => $fields, ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Process $process): RedirectResponse
    {
        $this->validateAccess();

        $process->title = $request->title;
        $process->description = $request->description;
        $process->link = $request->link;
        $process->link_name = $request->link_name;

        $process->subtitle = $request->has('subtitle') ? $request->subtitle : '';

        if ($request->hasFile('image')) {
            $process->image = $this->saveImageVersions(
                $this->imagePath,
                $request->image,
                [
                    ['name' => 'large'],
                ]
            );

        }

        $process->update();

        return redirect()->route('admin.landing.processes.index')->withStatus(__('Process was updated'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Process $process): RedirectResponse
    {
        $this->validateAccess();

        $process->delete();

        return redirect()->route('admin.landing.processes.index')->withStatus(__('Process was deleted.'));
    }
}
