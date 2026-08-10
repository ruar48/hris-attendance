<?php

namespace App\Http\Controllers;

use App\Models\OptionList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OptionListController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('employees/source-data', [
            'optionLists' => OptionList::grouped(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateOption($request);

        $nextOrder = (int) OptionList::query()->category($data['category'])->max('sort_order') + 1;

        OptionList::query()->create([
            ...$data,
            'sort_order' => $nextOrder,
        ]);

        return $this->toast('success', 'Added.');
    }

    public function update(Request $request, OptionList $optionList): RedirectResponse
    {
        $data = $this->validateOption($request, $optionList);

        $optionList->update($data);

        return $this->toast('success', 'Updated.');
    }

    public function destroy(OptionList $optionList): RedirectResponse
    {
        $optionList->delete();

        return $this->toast('warning', 'Removed.');
    }

    private function toast(string $type, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateOption(Request $request, ?OptionList $optionList = null): array
    {
        return $request->validate([
            'category' => ['required', Rule::in(OptionList::CATEGORIES)],
            'value' => [
                'required', 'string', 'max:50',
                Rule::unique('option_lists', 'value')
                    ->where('category', $request->input('category'))
                    ->ignore($optionList?->id),
            ],
            'label' => ['required', 'string', 'max:150'],
            'time_in' => ['nullable', 'string', 'max:20'],
            'time_out' => ['nullable', 'string', 'max:20'],
        ]);
    }
}
