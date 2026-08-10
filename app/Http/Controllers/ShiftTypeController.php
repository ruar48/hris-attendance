<?php

namespace App\Http\Controllers;

use App\Models\ShiftType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShiftTypeController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'integer', 'min:1', 'max:99', 'unique:shift_types,code'],
            'name' => ['required', 'string', 'max:120'],
            'time_in' => ['nullable', 'date_format:H:i'],
            'time_out' => ['nullable', 'date_format:H:i'],
            'is_rest_day' => ['required', 'boolean'],
        ]);

        ShiftType::query()->create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Shift type saved.']);

        return back();
    }

    public function update(Request $request, ShiftType $shiftType): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'integer', 'min:1', 'max:99', 'unique:shift_types,code,'.$shiftType->id],
            'name' => ['required', 'string', 'max:120'],
            'time_in' => ['nullable', 'date_format:H:i'],
            'time_out' => ['nullable', 'date_format:H:i'],
            'is_rest_day' => ['required', 'boolean'],
        ]);

        $shiftType->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Shift type updated.']);

        return back();
    }

    public function destroy(ShiftType $shiftType): RedirectResponse
    {
        $shiftType->delete();

        return back()->with('success', 'Shift type removed.');
    }
}
