<?php

namespace App\Http\Controllers;

use App\Models\FheContext;
use App\Models\FheJob;
use App\Models\Scheme;
use App\Services\FheWorkerNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FheContextController extends Controller
{
    public function index(): View
    {
        $contexts = FheContext::query()
            ->with('schemeRecord.library')
            ->latest()
            ->paginate(10);

        return view('fhe_contexts.index', [
            'contexts' => $contexts,
        ]);
    }

    public function create(): View
    {
        return view('fhe_contexts.create', [
            'context' => new FheContext(),
            'schemes' => $this->schemesForForm(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedData($request);

        // generate fhe context here
        FheContext::create($validated);

        return redirect()
            ->route('fhe-contexts.index')
            ->with('success', 'FHE context created successfully.');
    }

    public function show(FheContext $fheContext): View
    {
        $fheContext->load('schemeRecord.library');

        return view('fhe_contexts.show', [
            'context' => $fheContext,
        ]);
    }

    public function edit(FheContext $fheContext): View
    {
        $fheContext->load('schemeRecord.library');

        return view('fhe_contexts.edit', [
            'context' => $fheContext,
            'schemes' => $this->schemesForForm(),
        ]);
    }

    public function update(Request $request, FheContext $fheContext): RedirectResponse
    {
        $validated = $this->validatedData($request);

        if ($this->contextDefinitionChanged($fheContext, $validated)) {
            // generate fhe context here
        }

        $fheContext->update($validated);

        return redirect()
            ->route('fhe-contexts.index')
            ->with('success', 'FHE context updated successfully.');
    }

    public function destroy(FheContext $fheContext): RedirectResponse
    {
        $fheContext->delete();

        return redirect()
            ->route('fhe-contexts.index')
            ->with('success', 'FHE context deleted successfully.');
    }

    public function generate(FheContext $fheContext, FheWorkerNotifier $workerNotifier): RedirectResponse
    {
        $fheContext->load('schemeRecord.library');

        $jobPayload = [
            'job_type' => FheJob::TYPE_CREATE_CONTEXT,
            'pk' => $fheContext->id,
            'status' => FheJob::STATUS_PENDING,
            'input_payload' => [
                'context_id' => $fheContext->id,
                'context_name' => $fheContext->name,
                'scheme_id' => $fheContext->scheme_id,
                'scheme_name' => $fheContext->scheme_label,
                'library_id' => $fheContext->schemeRecord?->library?->id,
                'library_name' => $fheContext->schemeRecord?->library?->name,
                'parameters' => $fheContext->parameters_json ?? [],
            ],
            'created_by' => request()->user()?->id,
        ];

        if ($pendingJob = FheJob::pendingContextGeneration($fheContext)) {
            $pendingJob->update($jobPayload);
        } else {
            FheJob::create($jobPayload);
        }

        $fheContext->update([
            'context_status' => FheContext::STATUS_QUEUED,
        ]);

        $workerNotifier->wake();

        return redirect()
            ->back()
            ->with('success', 'FHE context generation job queued successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'scheme_id' => ['required', 'integer', Rule::exists('schemes', 'id')],
            'parameters' => ['nullable', 'array'],
        ]);

        $scheme = Scheme::query()->findOrFail($validated['scheme_id']);

        return [
            'name' => $validated['name'],
            'scheme_id' => $scheme->id,
            'scheme' => $scheme->scheme_name,
            'context_file_ref' => null,
            'context_status' => FheContext::STATUS_PENDING,
            'parameters_json' => $this->contextParameters($scheme, $validated['parameters'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function contextDefinitionChanged(FheContext $fheContext, array $validated): bool
    {
        return (int) $fheContext->scheme_id !== (int) $validated['scheme_id']
            || $fheContext->parameters_json !== $validated['parameters_json'];
    }

    private function schemesForForm()
    {
        return Scheme::query()
            ->with('library')
            ->orderBy('scheme_name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function contextParameters(Scheme $scheme, array $input): array
    {
        $parameters = [];
        $errors = [];

        foreach ($scheme->configuration_parameters as $definition) {
            $name = (string) ($definition['name'] ?? '');

            if ($name === '') {
                continue;
            }

            $value = $input[$name] ?? null;
            $type = $definition['type'] ?? 'string';
            $isRequired = (bool) ($definition['is_required'] ?? false);

            if ($this->isBlankValue($value)) {
                if (! $this->isBlankValue($definition['default_value'] ?? null)) {
                    $value = $definition['default_value'];
                } elseif ($isRequired) {
                    $errors["parameters.$name"] = ucfirst(str_replace('_', ' ', $name)).' is required.';
                    continue;
                } else {
                    $parameters[$name] = null;
                    continue;
                }
            }

            try {
                $parameters[$name] = $this->castParameterValue($value, $type, $name);
            } catch (ValidationException $exception) {
                $errors = array_merge($errors, $exception->errors());
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $parameters;
    }

    private function isBlankValue(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    private function castParameterValue(mixed $value, string $type, string $name): mixed
    {
        return match ($type) {
            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false
                ? (int) $value
                : throw ValidationException::withMessages(["parameters.$name" => ucfirst(str_replace('_', ' ', $name)).' must be an integer.']),
            'decimal' => is_numeric($value)
                ? (float) $value
                : throw ValidationException::withMessages(["parameters.$name" => ucfirst(str_replace('_', ' ', $name)).' must be a decimal number.']),
            'boolean' => (bool) $value,
            'json' => $this->decodeJsonParameter((string) $value, $name),
            default => (string) $value,
        };
    }

    private function decodeJsonParameter(string $value, string $name): mixed
    {
        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw ValidationException::withMessages(["parameters.$name" => ucfirst(str_replace('_', ' ', $name)).' must contain valid JSON.']);
        }

        return $decoded;
    }
}
