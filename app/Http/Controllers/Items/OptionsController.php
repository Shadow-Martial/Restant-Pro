<?php

namespace App\Http\Controllers\Items;

use App\Http\Controllers\Controller;
use App\Items;
use App\Models\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OptionsController extends Controller
{
    private function getFields()
    {
        return [
            ['ftype' => 'input', 'name' => 'Name', 'id' => 'name', 'placeholder' => 'Enter option name, ex size', 'required' => true],
            ['ftype' => 'input', 'name' => 'Comma separated list of option values', 'id' => 'options', 'placeholder' => 'Enter comma separated list of avaliable option values, ex: small,medium,large', 'required' => true],
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Items $item): View
    {
        return view('items.options.index', ['setup' => [
            'title' => __('Options for').' '.$item->name,
            'action_link' => route('items.options.create', ['item' => $item->id]),
            'action_name' => 'Add new option',
            'items' => $item->options()->paginate(10),
            'item_names' => 'options',
            'breadcrumbs' => [
                [__('Menu'), '/items'],
                [$item->name, '/items/'.$item->id.'/edit'],
                [__('Options'), null],
            ],
        ]]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Items $item): View
    {
        return view('general.form', ['setup' => [
            'title' => __('New option for').' '.$item->name,
            'action_link' => route('items.options.index', ['item' => $item->id]),
            'action_name' => __('Back'),
            'iscontent' => true,
            'action' => route('items.options.store', ['item' => $item->id]),
            'breadcrumbs' => [
                [__('Menu'), '/items'],
                [$item->name, '/items/'.$item->id.'/edit'],
                [__('Options'), route('items.options.index', ['item' => $item->id])],
                [__('New'), null],
            ],
        ],
            'fields' => $this->getFields(), ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Items $item, Request $request): RedirectResponse
    {
        $option = Options::create([
            'name' => $request->name,
            'options' => str_replace(', ', ',', $this->simple_replace_spec_char($request->options)),
            'item_id' => $item->id,
        ]);
        $option->save();

        return redirect()->route('items.options.index', ['item' => $item->id])->withStatus(__('Option has been added'));
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function show(int $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     */
    public function edit(Options $option): View
    {
        $fields = $this->getFields();
        $fields[0]['value'] = $option->name;
        $fields[1]['value'] = $option->options;

        return view('general.form', ['setup' => [
            'title' => __('Edit option').' '.$option->name,
            'action_link' => route('items.options.index', ['item' => $option->item]),
            'action_name' => __('Back'),
            'iscontent' => true,
            'isupdate' => true,
            'action' => route('items.options.update', ['option' => $option->id]),
            'breadcrumbs' => [
                [__('Menu'), '/items'],
                [$option->item->name, '/items/'.$option->item->id.'/edit'],
                [__('Options'), route('items.options.index', ['item' => $option->item->id])],
                [$option->name, null],
            ],
        ],
            'fields' => $fields, ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     */
    public function update(Request $request, Options $option): RedirectResponse
    {
        $option->name = $request->name;
        $option->options = str_replace(', ', ',', $this->simple_replace_spec_char($request->options));
        $option->update();

        return redirect()->route('items.options.index', ['item' => $option->item->id])->withStatus(__('Option has been updated'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     */
    public function destroy(Options $option): RedirectResponse
    {
        $option->delete();

        return redirect()->route('items.options.index', ['item' => $option->item->id])->withStatus(__('Option has been removed'));
    }
}
