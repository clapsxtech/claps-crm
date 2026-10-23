<?php

namespace App\Http\Controllers;

use App\Models\Settings;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = Settings::firstOrFail();

        return view('admin.settings', compact('settings'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone' => 'required|string|max:125',
            'support_mail' => 'required|email|max:255',
            'admin_mail' => 'required|email|max:255',
            'career_mail' => 'required|email|max:255',
            'facebook' => 'nullable|string|max:255',
            'instagram' => 'nullable|string|max:255',
            'youtube' => 'nullable|string|max:255',
            'linkedin' => 'nullable|string|max:255',
            'twitter' => 'nullable|string|max:255',
            'map' => 'nullable|string|max:5000',
        ]);

        $settings = Settings::firstOrFail();
        foreach ($validated as $field => $value) {
            $settings->{$field} = $value;
        }
        $settings->save();

        return redirect()->back()->with('status', 'Settings updated successfully.');
    }
}
