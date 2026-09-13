<?php

namespace App\Http\Controllers;

use App\Models\Library;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LibraryController extends Controller
{
    public function index(): View
    {
        $libraries = Library::query()
            ->latest()
            ->paginate(10);

        return view('libraries.index', [
            'libraries' => $libraries,
        ]);
    }

    public function create(): View
    {
        return view('libraries.create', [
            'library' => new Library([
                'is_active' => true,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Library::create($this->validatedData($request));

        return redirect()
            ->route('libraries.index')
            ->with('success', 'Library created successfully.');
    }

    public function show(Library $library): View
    {
        return view('libraries.show', [
            'library' => $library,
        ]);
    }

    public function edit(Library $library): View
    {
        return view('libraries.edit', [
            'library' => $library,
        ]);
    }

    public function update(Request $request, Library $library): RedirectResponse
    {
        $library->update($this->validatedData($request, $library));

        return redirect()
            ->route('libraries.index')
            ->with('success', 'Library updated successfully.');
    }

    public function destroy(Library $library): RedirectResponse
    {
        $library->delete();

        return redirect()
            ->route('libraries.index')
            ->with('success', 'Library deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?Library $library = null): array
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('libraries', 'name')->ignore($library?->id),
            ],
            'is_active' => ['required', 'boolean'],
        ]);

        return [
            'name' => $validated['name'],
            'is_active' => (bool) $validated['is_active'],
        ];
    }
}
