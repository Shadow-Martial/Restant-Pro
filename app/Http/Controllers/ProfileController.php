<?php

namespace App\Http\Controllers;

use App\Http\Requests\PasswordRequest;
use App\Http\Requests\ProfileRequest;
use App\Traits\Fields;
use App\Traits\Modules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ProfileController extends Controller
{
    use Fields;
    use Modules;

    /**
     * Show the form for editing the profile.
     */
    public function edit(): View
    {
        $rawFields = $this->vendorFields(auth()->user()->getAllConfigs(), auth()->user()->roles->toArray()[0]['name'].'_fields');
        $appFields = $this->convertJSONToFields($rawFields);

        return view('profile.edit', [
            'appFields' => $appFields,
        ]);
    }

    /**
     * Update the profile.
     */
    public function update(ProfileRequest $request): RedirectResponse
    {
        auth()->user()->update($request->all());

        //Update custom fields
        $rawFields = $this->vendorFields(auth()->user()->getAllConfigs(), auth()->user()->roles->toArray()[0]['name'].'_fields');
        //dd($request->all());
        $this->setMultipleConfig(auth()->user(), $request, $rawFields);

        return back()->withStatus(__('Profile successfully updated.'));
    }

    /**
     * Change the password.
     */
    public function password(PasswordRequest $request): RedirectResponse
    {
        auth()->user()->update(['password' => Hash::make($request->get('password'))]);

        return back()->withPasswordStatus(__('Password successfully updated.'));
    }
}
