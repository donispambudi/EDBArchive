<?php

namespace App\Http\Controllers;

use App\Models\DatabaseColumn;
use App\Models\DatabaseTable;
use App\Models\FheContext;
use App\Models\FheJob;
use App\Models\FheKeyRegistry;
use App\Models\ProviderDatabase;
use App\Models\Share;
use App\Models\ShareItem;
use App\Models\User;
use App\Services\FheWorkerNotifier;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShareController extends Controller
{
    public function index(): View
    {
        $shares = Share::query()
            ->with(['owner', 'recipient', 'database'])
            ->withCount(['items', 'history'])
            ->latest()
            ->paginate(10);

        return view('shares.index', ['shares' => $shares]);
    }

    public function create(): View
    {
        return view('shares.create', $this->formData(new Share([
            'release_version' => now()->format('Y-m-d').'-release',
            'status' => 'draft',
            'bundle_status' => 'pending',
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $share = Share::create($this->validatedShareData($request));

        return redirect()
            ->route('shares.show', $share)
            ->with('success', 'Share created successfully.');
    }

    public function show(Share $share): View
    {
        $share->load([
            'owner',
            'recipient',
            'database.databaseTables.columns',
            'items.databaseTable',
            'items.databaseColumn.databaseTable',
            'items.fheContext',
            'items.keyRegistry.owner',
            'history.parentShare',
            'history.originalOwner',
            'history.sharedBy',
            'history.sharedTo',
            'history.database',
        ]);
        $share->setRelation('items', $share->items
            ->sortBy([
                fn (ShareItem $item): string => $item->databaseTable?->table_name ?? '',
                fn (ShareItem $item): int => $item->databaseColumn?->column_order ?? PHP_INT_MAX,
                fn (ShareItem $item): int => $item->id,
            ])
            ->values());

        return view('shares.show', array_merge($this->childFormData($share), [
            'share' => $share,
            'primaryShareItemIds' => $this->primaryShareItemIds($share),
        ]));
    }

    public function edit(Share $share): View
    {
        return view('shares.edit', $this->formData($share));
    }

    public function update(Request $request, Share $share): RedirectResponse
    {
        $share->update($this->validatedShareData($request));

        return redirect()
            ->route('shares.show', $share)
            ->with('success', 'Share updated successfully.');
    }

    public function destroy(Share $share): RedirectResponse
    {
        $share->delete();

        return redirect()
            ->route('shares.index')
            ->with('success', 'Share deleted successfully.');
    }

    public function downloadBundle(Share $share): StreamedResponse
    {
        $bundleRef = str_replace('\\', '/', trim((string) $share->bundle_ref));
        $pathSegments = explode('/', $bundleRef);

        abort_if(
            $bundleRef === ''
                || ! Str::startsWith($bundleRef, 'public/')
                || in_array('..', $pathSegments, true),
            404
        );

        $diskPath = Str::after($bundleRef, 'public/');

        abort_unless(
            $diskPath !== ''
                && ! Str::startsWith($diskPath, '/')
                && Storage::disk('public')->exists($diskPath),
            404
        );

        return Storage::disk('public')->download($diskPath, basename($diskPath));
    }

    public function generateBundle(Share $share, FheWorkerNotifier $workerNotifier): RedirectResponse
    {
        if ($share->bundle_ref) {
            return redirect()
                ->back()
                ->with('success', 'Bundle has already been generated.');
        }

        $share->load([
            'recipient',
            'database',
            'items.databaseTable',
            'items.databaseColumn',
            'items.fheContext.schemeRecord.library',
            'items.keyRegistry',
        ]);

        $hasInvalidRecipientKey = $share->items->contains(function (ShareItem $item) use ($share): bool {
            if (! $this->isFheShareEncryption($item->encryption)) {
                return false;
            }

            return ! $item->keyRegistry
                || (int) $item->keyRegistry->owner_user_id !== (int) $share->recipient_user_id
                || blank($item->keyRegistry->keyset_ref);
        });

        if ($hasInvalidRecipientKey) {
            return redirect()
                ->back()
                ->with('error', 'An encryption key could not be found for one or more configured columns.');
        }

        $keyRegistryCount = $share->items
            ->pluck('key_registry_id')
            ->filter()
            ->unique()
            ->count();

        if ($keyRegistryCount > 1) {
            return redirect()
                ->back()
                ->with('error', 'Bundle generation currently supports only one FHE key per share.');
        }

        $primaryColumnsByTable = $share->items
            ->pluck('databaseTable')
            ->filter()
            ->unique('id')
            ->mapWithKeys(function (DatabaseTable $table) use ($share): array {
                $databaseName = $share->database?->name;

                return [
                    $table->id => $databaseName
                        ? $this->primaryKeyColumnNames($databaseName, $table->table_name)
                        : [],
                ];
            });

        $jobPayload = [
            'job_type' => FheJob::TYPE_BACKUP,
            'pk' => $share->id,
            'status' => FheJob::STATUS_PENDING,
            'input_payload' => [
                'share_id' => $share->id,
                'recipient_id' => $share->recipient_user_id,
                'recipient_name' => $share->recipient?->name,
                'database_id' => $share->database_id,
                'database_name' => $share->database?->name,
                'release_version' => $share->release_version,
                'items' => $share->items->map(function (ShareItem $item) use ($primaryColumnsByTable): array {
                    $itemPayload = [
                        'share_item_id' => $item->id,
                        'table_id' => $item->database_table_id,
                        'table_name' => $item->databaseTable?->table_name,
                        'column_id' => $item->database_column_id,
                        'column_name' => $item->databaseColumn?->column_name,
                        'pk' => in_array(
                            $item->databaseColumn?->column_name,
                            $primaryColumnsByTable->get($item->database_table_id, []),
                            true
                        ),
                        'encryption' => $item->encryption,
                    ];

                    if (! $this->isFheShareEncryption($item->encryption)) {
                        return $itemPayload;
                    }

                    $context = $item->fheContext;

                    return array_merge($itemPayload, [
                        'key_id' => $item->key_registry_id,
                        'keypair_file_ref' => $item->keyRegistry?->keyset_ref,
                        'context_id' => $item->fhe_context_id,
                        'context_name' => $context?->name,
                        'context_file_ref' => $context?->context_file_ref,
                        'scheme_id' => $context?->scheme_id,
                        'scheme_name' => $context?->scheme_label,
                        'library_id' => $context?->schemeRecord?->library?->id,
                        'library_name' => $context?->schemeRecord?->library?->name
                    ]);
                })->values()->all(),
            ],
            'created_by' => request()->user()?->id,
        ];

        if ($pendingJob = FheJob::pendingShareBackup($share)) {
            $pendingJob->update($jobPayload);
        } else {
            FheJob::create($jobPayload);
        }

        $share->update([
            'bundle_status' => 'processing',
        ]);

        $workerNotifier->wake();

        return redirect()
            ->back()
            ->with('success', 'Bundle generation started.');
    }

    public function storeItem(Request $request, Share $share): RedirectResponse
    {
        DB::transaction(function () use ($request, $share): void {
            $data = $this->validatedItemData($request, $share);

            $this->addPrimaryKeyItemsForTable($share, (int) $data['database_table_id']);
            ShareItem::firstOrCreate([
                'share_id' => $share->id,
                'database_table_id' => $data['database_table_id'],
                'database_column_id' => $data['database_column_id'],
            ], [
                'encryption' => $data['encryption'],
                'fhe_context_id' => $data['fhe_context_id'],
                'key_registry_id' => $data['key_registry_id'],
            ]);
        });

        return redirect()
            ->route('shares.show', $share)
            ->with('success', 'Share item added successfully.');
    }

    public function updateItem(Request $request, Share $share, ShareItem $item): RedirectResponse
    {
        abort_unless($item->share_id === $share->id, 404);

        if ($this->isPrimaryKeyShareItem($item)) {
            $item->update([
                'encryption' => 'plaintext',
                'fhe_context_id' => null,
                'key_registry_id' => null,
            ]);

            return redirect()
                ->route('shares.show', $share)
                ->with('success', 'Primary key share item kept as plaintext.');
        }

        $data = $request->validateWithBag('updateShareItem', [
            'editing_item_id' => ['required', 'integer', Rule::in([$item->id])],
            'encryption' => ['required', Rule::in(ShareItem::ENCRYPTION_TYPES)],
            'fhe_context_id' => [
                Rule::requiredIf($this->isFheShareEncryption($request->input('encryption'))),
                'nullable',
                Rule::exists('fhe_contexts', 'id'),
            ],
            'key_registry_id' => [
                Rule::requiredIf($this->isFheShareEncryption($request->input('encryption'))),
                'nullable',
                Rule::exists('fhe_key_registry', 'id')->where(
                    fn ($query) => $query
                        ->where('owner_user_id', $share->recipient_user_id)
                        ->where('fhe_context_id', $request->input('fhe_context_id'))
                ),
            ],
        ]);

        if (! $this->isFheShareEncryption($data['encryption'])) {
            $data['fhe_context_id'] = null;
            $data['key_registry_id'] = null;
        }

        unset($data['editing_item_id']);
        $item->update($data);

        return redirect()
            ->route('shares.show', $share)
            ->with('success', 'Share item updated successfully.');
    }

    public function storeAllConfiguredItems(Request $request, Share $share): RedirectResponse
    {
        $columns = $this->configuredDatabaseColumns($share);
        $fheColumns = $columns->filter(fn (DatabaseColumn $column) => $this->isFheShareEncryption($column->default_encryption_type));
        $missingContextCount = $fheColumns->filter(fn (DatabaseColumn $column) => empty($column->fhe_context_id))->count();

        if ($missingContextCount > 0) {
            return redirect()
                ->route('shares.show', $share)
                ->withErrors(['bulk_keys' => 'All configured FHE columns must have an FHE context before bulk add.']);
        }

        $requiredContextIds = $fheColumns
            ->pluck('fhe_context_id')
            ->filter()
            ->unique()
            ->values();
        $rules = [];

        foreach ($requiredContextIds as $contextId) {
            $rules['bulk_keys.'.$contextId] = [
                'required',
                Rule::exists('fhe_key_registry', 'id')
                    ->where('owner_user_id', $share->recipient_user_id)
                    ->where('fhe_context_id', $contextId),
            ];
        }

        $validated = $rules === [] ? [] : $request->validate($rules);
        $bulkKeyIdsByContext = collect($validated['bulk_keys'] ?? [])
            ->mapWithKeys(fn ($keyId, $contextId) => [(int) $contextId => (int) $keyId]);
        $created = 0;
        $configuredCount = $columns->count();

        foreach ($columns as $column) {
            $isFhe = $this->isFheShareEncryption($column->default_encryption_type);
            $keyRegistryId = $isFhe
                ? $bulkKeyIdsByContext->get((int) $column->fhe_context_id)
                : null;

            $item = ShareItem::firstOrCreate([
                'share_id' => $share->id,
                'database_table_id' => $column->database_table_id,
                'database_column_id' => $column->id,
            ], [
                'encryption' => $isFhe ? $this->shareItemEncryptionForColumn($column) : 'plaintext',
                'fhe_context_id' => $isFhe ? $column->fhe_context_id : null,
                'key_registry_id' => $keyRegistryId,
            ]);

            if ($item->wasRecentlyCreated) {
                $created += 1;
            }
        }

        return redirect()
            ->route('shares.show', $share)
            ->with('success', $created.' of '.$configuredCount.' configured database columns added to this share.');
    }

    public function destroyAllItems(Share $share): RedirectResponse
    {
        $deleted = ShareItem::query()
            ->where('share_id', $share->id)
            ->delete();

        return redirect()
            ->route('shares.show', $share)
            ->with('success', $deleted.' share items deleted successfully.');
    }

    public function destroyItem(Share $share, ShareItem $item): RedirectResponse
    {
        abort_unless($item->share_id === $share->id, 404);

        if ($this->isPrimaryKeyShareItem($item) && $item->database_table_id) {
            $deleted = ShareItem::query()
                ->where('share_id', $share->id)
                ->where('database_table_id', $item->database_table_id)
                ->delete();

            return redirect()
                ->route('shares.show', $share)
                ->with('success', $deleted.' share items from the selected primary key table deleted successfully.');
        }

        $item->delete();

        return redirect()
            ->route('shares.show', $share)
            ->with('success', 'Share item deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Share $share): array
    {
        return [
            'share' => $share,
            'owners' => $this->ownerUsers($share),
            'recipients' => $this->recipientUsers($share),
            'databases' => ProviderDatabase::query()->orderBy('name')->get(),
            'statuses' => Share::STATUSES,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function childFormData(Share $share): array
    {
        $recipientKeyRegistries = FheKeyRegistry::query()
            ->with('fheContext.schemeRecord')
            ->where('owner_user_id', $share->recipient_user_id)
            ->orderBy('id')
            ->get();

        $databaseColumns = $this->configuredDatabaseColumns($share);
        $bulkFheContextIds = $databaseColumns
            ->filter(fn (DatabaseColumn $column) => $this->isFheShareEncryption($column->default_encryption_type) && $column->fhe_context_id)
            ->pluck('fhe_context_id')
            ->unique()
            ->values();
        $hasMissingBulkFheContexts = $databaseColumns
            ->contains(fn (DatabaseColumn $column) => $this->isFheShareEncryption($column->default_encryption_type) && empty($column->fhe_context_id));
        $recipientKeysByContext = $recipientKeyRegistries->groupBy('fhe_context_id');

        return [
            'databaseTables' => DatabaseTable::query()
                ->where('database_id', $share->database_id)
                ->with('columns')
                ->orderBy('table_name')
                ->get(),
            'databaseColumns' => $databaseColumns,
            'fheContexts' => FheContext::query()
                ->with('schemeRecord')
                ->orderBy('name')
                ->get(),
            'recipientKeyRegistries' => $recipientKeyRegistries,
            'recipientKeyIdsByContext' => $recipientKeyRegistries->keyBy('fhe_context_id')->map->id,
            'recipientKeysByContext' => $recipientKeysByContext,
            'bulkFheContexts' => FheContext::query()
                ->with('schemeRecord')
                ->whereIn('id', $bulkFheContextIds)
                ->orderBy('name')
                ->get(),
            'hasMissingBulkFheContexts' => $hasMissingBulkFheContexts,
            'hasMissingBulkRecipientKeys' => $bulkFheContextIds
                ->contains(fn ($contextId) => $recipientKeysByContext->get($contextId, collect())->isEmpty()),
            'hasConfiguredFheColumns' => $databaseColumns->contains(fn (DatabaseColumn $column) => $this->isFheShareEncryption($column->default_encryption_type)),
            'encryptionTypes' => ShareItem::ENCRYPTION_TYPES,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedShareData(Request $request): array
    {
        return $request->validate([
            'owner_user_id' => [
                'required',
                Rule::exists('users', 'id')->where('role', 'Data Provider'),
            ],
            'recipient_user_id' => [
                'required',
                Rule::exists('users', 'id')->where('role', 'Third Party'),
            ],
            'database_id' => ['required', Rule::exists('databases', 'id')],
            'release_version' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(Share::STATUSES)],
            'expires_at' => ['nullable', 'date'],
            'revoked_at' => ['nullable', 'date'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedItemData(Request $request, Share $share): array
    {
        $validated = $request->validate([
            'database_table_id' => [
                'required',
                Rule::exists('database_tables', 'id')->where('database_id', $share->database_id),
            ],
            'database_column_id' => ['required', Rule::exists('database_columns', 'id')],
            'encryption' => ['required', Rule::in(ShareItem::ENCRYPTION_TYPES)],
            'fhe_context_id' => [
                Rule::requiredIf($this->isFheShareEncryption($request->input('encryption'))),
                'nullable',
                Rule::exists('fhe_contexts', 'id'),
            ],
            'key_registry_id' => [
                Rule::requiredIf($this->isFheShareEncryption($request->input('encryption'))),
                'nullable',
                Rule::exists('fhe_key_registry', 'id')
                    ->where('owner_user_id', $share->recipient_user_id),
            ],
        ]);

        $columnBelongsToTable = DatabaseColumn::query()
            ->whereKey($validated['database_column_id'])
            ->where('database_table_id', $validated['database_table_id'])
            ->exists();

        abort_unless($columnBelongsToTable, 422, 'The selected column does not belong to the selected table.');

        $databaseColumn = DatabaseColumn::query()
            ->with('databaseTable.database')
            ->findOrFail($validated['database_column_id']);

        if ($this->isPrimaryKeyColumn($databaseColumn)) {
            $validated['encryption'] = 'plaintext';
            $validated['fhe_context_id'] = null;
            $validated['key_registry_id'] = null;
        } elseif (! $this->isFheShareEncryption($validated['encryption'])) {
            $validated['fhe_context_id'] = null;
            $validated['key_registry_id'] = null;
        }

        $validated['share_id'] = $share->id;

        return $validated;
    }

    private function recipientUsers(Share $share)
    {
        return User::query()
            ->where(function ($query) use ($share): void {
                $query->where('role', 'Third Party');

                if ($share->recipient_user_id) {
                    $query->orWhere('id', $share->recipient_user_id);
                }
            })
            ->orderBy('name')
            ->get();
    }

    private function ownerUsers(Share $share)
    {
        return User::query()
            ->where(function ($query) use ($share): void {
                $query->where('role', 'Data Provider');

                if ($share->owner_user_id) {
                    $query->orWhere('id', $share->owner_user_id);
                }
            })
            ->orderBy('name')
            ->get();
    }

    private function shareItemEncryptionForColumn(DatabaseColumn $column): string
    {
        if (! $this->isFheShareEncryption($column->default_encryption_type)) {
            return 'plaintext';
        }

        if (in_array($column->default_encryption_type, ShareItem::ENCRYPTION_TYPES, true)) {
            return $column->default_encryption_type;
        }

        $scheme = Str::slug($column->fheContext?->scheme_label ?? '', '-');

        foreach (['fhe-secure'] as $encryption) {
            if (str_contains($scheme, Str::after($encryption, 'fhe-'))) {
                return $encryption;
            }
        }

        return 'fhe-secure';
    }

    private function isFheShareEncryption(?string $encryption): bool
    {
        return is_string($encryption) && str_starts_with($encryption, 'fhe-');
    }

    private function configuredDatabaseColumns(Share $share)
    {
        return DatabaseColumn::query()
            ->select('database_columns.*')
            ->join('database_tables', 'database_tables.id', '=', 'database_columns.database_table_id')
            ->where('database_tables.database_id', $share->database_id)
            ->with(['databaseTable', 'fheContext.schemeRecord'])
            ->orderBy('database_tables.table_name')
            ->orderByRaw('database_columns.column_order IS NULL')
            ->orderBy('database_columns.column_order')
            ->orderBy('database_columns.id')
            ->get();
    }

    private function addPrimaryKeyItemsForTable(Share $share, int $databaseTableId): void
    {
        foreach ($this->primaryDatabaseColumnsForTable($share, $databaseTableId) as $column) {
            ShareItem::firstOrCreate([
                'share_id' => $share->id,
                'database_table_id' => $column->database_table_id,
                'database_column_id' => $column->id,
            ], [
                'encryption' => 'plaintext',
                'fhe_context_id' => null,
                'key_registry_id' => null,
            ]);
        }
    }

    private function primaryDatabaseColumnsForTable(Share $share, int $databaseTableId)
    {
        $database = $share->database()->with(['databaseTables' => fn ($query) => $query->where('id', $databaseTableId)])->first();

        if (! $database || $database->databaseTables->isEmpty()) {
            return collect();
        }

        $columns = collect();

        foreach ($database->databaseTables as $table) {
            $columnOrders = $this->columnOrders($database->name, $table->table_name);

            foreach ($this->primaryKeyColumnNames($database->name, $table->table_name) as $columnName) {
                $columns->push(DatabaseColumn::updateOrCreate([
                    'database_table_id' => $table->id,
                    'column_name' => $columnName,
                ], [
                    'column_order' => $columnOrders[$columnName] ?? null,
                    'default_encryption_type' => 'plaintext',
                    'fhe_context_id' => null,
                ]));
            }
        }

        return $columns;
    }

    private function primaryShareItemIds(Share $share)
    {
        $primaryColumnIds = $share->items
            ->map(fn (ShareItem $item) => $item->database_table_id)
            ->filter()
            ->unique()
            ->flatMap(fn (int $databaseTableId) => $this->primaryDatabaseColumnsForTable($share, $databaseTableId))
            ->pluck('id')
            ->all();

        if ($primaryColumnIds === []) {
            return collect();
        }

        return $share->items
            ->filter(fn (ShareItem $item): bool => in_array($item->database_column_id, $primaryColumnIds, true))
            ->pluck('id');
    }

    private function isPrimaryKeyShareItem(ShareItem $item): bool
    {
        $item->loadMissing('databaseColumn.databaseTable.database');

        return $item->databaseColumn
            ? $this->isPrimaryKeyColumn($item->databaseColumn)
            : false;
    }

    private function isPrimaryKeyColumn(DatabaseColumn $column): bool
    {
        $column->loadMissing('databaseTable.database');

        $table = $column->databaseTable;
        $database = $table?->database;

        if (! $table || ! $database) {
            return false;
        }

        return in_array($column->column_name, $this->primaryKeyColumnNames($database->name, $table->table_name), true);
    }

    /**
     * @return array<string, int>
     */
    private function columnOrders(string $databaseName, string $tableName): array
    {
        try {
            $columns = DB::select(
                'SHOW FULL COLUMNS FROM '.$this->quoteIdentifier($databaseName).'.'.$this->quoteIdentifier($tableName)
            );
        } catch (QueryException) {
            return [];
        }

        $orders = [];

        foreach ($columns as $index => $column) {
            $data = (array) $column;
            $name = (string) ($data['Field'] ?? '');

            if ($name !== '') {
                $orders[$name] = $index + 1;
            }
        }

        return $orders;
    }

    /**
     * @return list<string>
     */
    private function primaryKeyColumnNames(string $databaseName, string $tableName): array
    {
        try {
            $keys = DB::select(
                'SHOW KEYS FROM '.$this->quoteIdentifier($databaseName).'.'.$this->quoteIdentifier($tableName)." WHERE Key_name = 'PRIMARY'"
            );
        } catch (QueryException) {
            return [];
        }

        return collect($keys)
            ->map(fn (object $key): string => (string) (((array) $key)['Column_name'] ?? ''))
            ->filter()
            ->values()
            ->all();
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }
}
