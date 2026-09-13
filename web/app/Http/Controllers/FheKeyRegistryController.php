<?php

namespace App\Http\Controllers;

use App\Models\FheContext;
use App\Models\FheJob;
use App\Models\FheKeyRegistry;
use App\Models\User;
use App\Services\FheWorkerNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FheKeyRegistryController extends Controller
{
    public function index(): View
    {
        $keys = FheKeyRegistry::query()
            ->with(['owner', 'fheContext.schemeRecord.library'])
            ->latest()
            ->paginate(10);

        return view('fhe_key_registry.index', [
            'keys' => $keys,
        ]);
    }

    public function create(): View
    {
        return view('fhe_key_registry.create', $this->formData(new FheKeyRegistry([
            'key_status' => 'active',
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedData($request);

        // generate keyset_ref here
        $validated['keyset_ref'] = '';
        $validated['generation_status'] = FheKeyRegistry::GENERATION_PENDING;
        FheKeyRegistry::create($validated);

        return redirect()
            ->route('fhe-key-registry.index')
            ->with('success', 'FHE key registry created successfully.');
    }

    public function show(FheKeyRegistry $keyRegistry): View
    {
        $keyRegistry->load(['owner', 'fheContext.schemeRecord.library']);

        return view('fhe_key_registry.show', [
            'keyRegistry' => $keyRegistry,
        ]);
    }

    public function edit(FheKeyRegistry $keyRegistry): View
    {
        return view('fhe_key_registry.edit', $this->formData($keyRegistry));
    }

    public function update(Request $request, FheKeyRegistry $keyRegistry): RedirectResponse
    {
        $validated = $this->validatedData($request);

        if ($this->keyDefinitionChanged($keyRegistry, $validated)) {
            // regenerate keyset_ref here
            $validated['keyset_ref'] = '';
            $validated['generation_status'] = FheKeyRegistry::GENERATION_PENDING;
        }

        $keyRegistry->update($validated);

        return redirect()
            ->route('fhe-key-registry.index')
            ->with('success', 'FHE key registry updated successfully.');
    }

    public function destroy(FheKeyRegistry $keyRegistry): RedirectResponse
    {
        $keyRegistry->delete();

        return redirect()
            ->route('fhe-key-registry.index')
            ->with('success', 'FHE key registry deleted successfully.');
    }

    public function generate(FheKeyRegistry $keyRegistry, FheWorkerNotifier $workerNotifier): RedirectResponse
    {
        $keyRegistry->load(['owner', 'fheContext.schemeRecord.library']);
        $context = $keyRegistry->fheContext;

        $jobPayload = [
            'job_type' => FheJob::TYPE_CREATE_KEYPAIR,
            'pk' => $keyRegistry->id,
            'status' => FheJob::STATUS_PENDING,
            'input_payload' => [
                'key_id' => $keyRegistry->id,
                'owner_id' => $keyRegistry->owner_user_id,
                'owner_name' => $keyRegistry->owner?->name,
                'context_id' => $keyRegistry->fhe_context_id,
                'context_name' => $context?->name,
                'context_file_ref' => $context?->context_file_ref,
                'scheme_id' => $context?->scheme_id,
                'scheme_name' => $context?->scheme_label,
                'library_id' => $context?->schemeRecord?->library?->id,
                'library_name' => $context?->schemeRecord?->library?->name
            ],
            'created_by' => request()->user()?->id,
        ];

        if ($pendingJob = FheJob::pendingKeyGeneration($keyRegistry)) {
            $pendingJob->update($jobPayload);
        } else {
            FheJob::create($jobPayload);
        }

        $keyRegistry->update([
            'generation_status' => FheKeyRegistry::GENERATION_QUEUED,
        ]);

        $workerNotifier->wake();

        return redirect()
            ->back()
            ->with('success', 'FHE key generation job queued successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(FheKeyRegistry $keyRegistry): array
    {
        return [
            'keyRegistry' => $keyRegistry,
            'users' => User::query()
                ->orderBy('name')
                ->get(),
            'fheContexts' => FheContext::query()
                ->with('schemeRecord.library')
                ->orderBy('name')
                ->get(),
            'statuses' => FheKeyRegistry::STATUSES,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request): array
    {
        return $request->validate([
            'owner_user_id' => ['required', Rule::exists('users', 'id')],
            'fhe_context_id' => ['required', Rule::exists('fhe_contexts', 'id')],
            'key_status' => ['required', Rule::in(FheKeyRegistry::STATUSES)],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function keyDefinitionChanged(FheKeyRegistry $keyRegistry, array $validated): bool
    {
        return (int) $keyRegistry->owner_user_id !== (int) $validated['owner_user_id']
            || (int) $keyRegistry->fhe_context_id !== (int) $validated['fhe_context_id'];
    }
}
