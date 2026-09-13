<?php

namespace App\Http\Controllers;

use App\Models\Library;
use App\Models\Scheme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SchemeController extends Controller
{
    public function index(): View
    {
        $schemes = Scheme::query()
            ->with('library')
            ->latest()
            ->paginate(10);

        return view('schemes.index', [
            'schemes' => $schemes,
        ]);
    }

    public function create(): View
    {
        return view('schemes.create', [
            'libraries' => $this->librariesForDropdown(),
            'scheme' => new Scheme([
                'is_active' => true,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Scheme::create($this->validatedData($request));

        return redirect()
            ->route('schemes.index')
            ->with('success', 'Scheme created successfully.');
    }

    public function show(Scheme $scheme): View
    {
        $scheme->load('library');

        return view('schemes.show', [
            'scheme' => $scheme,
        ]);
    }

    public function edit(Scheme $scheme): View
    {
        return view('schemes.edit', [
            'libraries' => $this->librariesForDropdown(),
            'scheme' => $scheme,
        ]);
    }

    public function update(Request $request, Scheme $scheme): RedirectResponse
    {
        $scheme->update($this->validatedData($request, $scheme));

        return redirect()
            ->route('schemes.index')
            ->with('success', 'Scheme updated successfully.');
    }

    public function destroy(Scheme $scheme): RedirectResponse
    {
        $scheme->delete();

        return redirect()
            ->route('schemes.index')
            ->with('success', 'Scheme deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?Scheme $scheme = null): array
    {
        $validated = $request->validate([
            'library_id' => ['required', 'integer', Rule::exists('libraries', 'id')],
            'scheme_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('schemes', 'scheme_name')->ignore($scheme?->id),
            ],
            'parameters' => ['nullable', 'array'],
            'parameters.*.name' => ['nullable', 'string', 'max:255'],
            'parameters.*.type' => ['nullable', Rule::in(['string', 'integer', 'decimal', 'boolean', 'json'])],
            'parameters.*.default_value' => ['nullable', 'string', 'max:255'],
            'parameters.*.description' => ['nullable', 'string', 'max:500'],
            'parameters.*.is_required' => ['nullable', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);

        return [
            'library_id' => (int) $validated['library_id'],
            'scheme_name' => $validated['scheme_name'],
            'configuration_json' => [
                'parameters' => $this->configurationParameters($validated['parameters'] ?? []),
            ],
            'is_active' => (bool) $validated['is_active'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $parameters
     * @return array<int, array<string, mixed>>
     */
    private function configurationParameters(array $parameters): array
    {
        $normalized = [];

        foreach ($parameters as $parameter) {
            $name = trim((string) ($parameter['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $normalized[] = [
                'name' => $name,
                'type' => $parameter['type'] ?? 'string',
                'default_value' => $parameter['default_value'] ?? null,
                'is_required' => (bool) ($parameter['is_required'] ?? false),
                'description' => $parameter['description'] ?? null,
            ];
        }

        return $normalized;
    }

    private function librariesForDropdown()
    {
        return Library::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
