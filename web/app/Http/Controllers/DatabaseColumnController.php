<?php

namespace App\Http\Controllers;

use App\Models\DatabaseColumn;
use App\Models\DatabaseTable;
use App\Models\FheContext;
use App\Models\ProviderDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DatabaseColumnController extends Controller
{
    public function index(): View
    {
        $columns = DatabaseColumn::query()
            ->with(['databaseTable.database', 'fheContext'])
            ->latest()
            ->paginate(10);

        return view('database_columns.index', [
            'columns' => $columns,
        ]);
    }

    public function create(): View
    {
        return view('database_columns.create', $this->formData(new DatabaseColumn([
            'default_encryption_type' => 'plaintext',
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        DatabaseColumn::create($this->validatedData($request));

        return redirect()
            ->route('database-columns.index')
            ->with('success', 'Database column created successfully.');
    }

    public function show(DatabaseColumn $databaseColumn): View
    {
        $databaseColumn->load(['databaseTable.database', 'fheContext']);

        return view('database_columns.show', [
            'databaseColumn' => $databaseColumn,
        ]);
    }

    public function edit(DatabaseColumn $databaseColumn): View
    {
        return view('database_columns.edit', $this->formData($databaseColumn));
    }

    public function update(Request $request, DatabaseColumn $databaseColumn): RedirectResponse
    {
        $databaseColumn->update($this->validatedData($request, $databaseColumn));

        return redirect()
            ->route('database-columns.index')
            ->with('success', 'Database column updated successfully.');
    }

    public function destroy(DatabaseColumn $databaseColumn): RedirectResponse
    {
        $databaseColumn->delete();

        return redirect()
            ->route('database-columns.index')
            ->with('success', 'Database column deleted successfully.');
    }

    public function editDatabaseColumns(ProviderDatabase $database): View
    {
        $database->load(['databaseTables.columns', 'provider']);

        return view('databases.columns', array_merge($this->sharedOptions(), [
            'database' => $database,
            'columnRows' => $this->columnRows($database),
        ]));
    }

    public function updateDatabaseColumns(Request $request, ProviderDatabase $database): RedirectResponse
    {
        $database->load('databaseTables');
        $validated = $this->validatedColumnRows($request, $database);

        DB::transaction(function () use ($database, $validated): void {
            $enabledColumns = collect($validated)
                ->filter(fn (array $row): bool => (bool) ($row['enabled'] ?? false));

            $enabledByTable = $enabledColumns
                ->groupBy('database_table_id')
                ->map(fn ($rows) => $rows->pluck('column_name')->all());

            foreach ($database->databaseTables as $table) {
                DatabaseColumn::query()
                    ->where('database_table_id', $table->id)
                    ->whereNotIn('column_name', $enabledByTable->get($table->id, []))
                    ->delete();
            }

            foreach ($enabledColumns as $row) {
                if (! $this->isFheEncryption($row['default_encryption_type'])) {
                    $row['fhe_context_id'] = null;
                }

                unset($row['enabled']);

                DatabaseColumn::updateOrCreate([
                    'database_table_id' => $row['database_table_id'],
                    'column_name' => $row['column_name'],
                ], $row);
            }
        });

        return redirect()
            ->route('databases.show', $database)
            ->with('success', 'Database columns updated successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(DatabaseColumn $databaseColumn): array
    {
        return array_merge([
            'databaseColumn' => $databaseColumn,
            'databaseTables' => DatabaseTable::query()
                ->with('database')
                ->orderBy('table_name')
                ->get(),
        ], $this->sharedOptions());
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?DatabaseColumn $databaseColumn = null): array
    {
        $validated = $request->validate([
            'database_table_id' => ['required', Rule::exists('database_tables', 'id')],
            'column_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('database_columns', 'column_name')
                    ->where('database_table_id', $request->input('database_table_id'))
                    ->ignore($databaseColumn?->id),
            ],
            'default_encryption_type' => ['required', Rule::in(DatabaseColumn::ENCRYPTION_TYPES)],
            'fhe_context_id' => [
                Rule::requiredIf($this->isFheEncryption($request->input('default_encryption_type'))),
                'nullable',
                Rule::exists('fhe_contexts', 'id'),
            ],
        ]);

        if (! $this->isFheEncryption($validated['default_encryption_type'])) {
            $validated['fhe_context_id'] = null;
        }

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function sharedOptions(): array
    {
        return [
            'fheContexts' => FheContext::query()
                ->with('schemeRecord')
                ->orderBy('name')
                ->get(),
            'encryptionTypes' => DatabaseColumn::ENCRYPTION_TYPES,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function columnRows(ProviderDatabase $database): array
    {
        $rows = [];

        foreach ($database->databaseTables->sortBy('table_name') as $table) {
            $existingByName = $table->columns->keyBy('column_name');
            $localColumns = $this->localColumnDefinitions($database->name, $table->table_name);

            if ($localColumns === []) {
                $localColumns = $existingByName
                    ->map(fn (DatabaseColumn $column): array => [
                        'name' => $column->column_name,
                        'type' => '-',
                        'is_primary' => false,
                        'position' => $column->column_order,
                    ])
                    ->values()
                    ->all();
            }

            foreach ($localColumns as $localColumn) {
                $existing = $existingByName->get($localColumn['name']);

                $rows[] = [
                    'table' => $table,
                    'enabled' => $existing !== null,
                    'column_name' => $localColumn['name'],
                    'column_order' => $localColumn['position'] ?? $existing?->column_order,
                    'data_type' => $localColumn['type'] ?? '-',
                    'is_primary' => (bool) ($localColumn['is_primary'] ?? false),
                    'default_encryption_type' => ! empty($localColumn['is_primary'])
                        ? 'plaintext'
                        : ($existing?->default_encryption_type ?? 'plaintext'),
                    'fhe_context_id' => ! empty($localColumn['is_primary'])
                        ? null
                        : $existing?->fhe_context_id,
                ];
            }
        }

        return $rows;
    }

    /**
     * @return list<array{name: string, type: string, is_primary: bool, position: int}>
     */
    private function localColumnDefinitions(string $databaseName, string $tableName): array
    {
        try {
            $columns = DB::select(
                'SHOW FULL COLUMNS FROM '.$this->quoteIdentifier($databaseName).'.'.$this->quoteIdentifier($tableName)
            );
        } catch (QueryException) {
            return [];
        }

        return collect($columns)
            ->map(function (object $column, $index): array {
                $data = (array) $column;

                return [
                    'name' => (string) ($data['Field'] ?? ''),
                    'type' => (string) ($data['Type'] ?? '-'),
                    'is_primary' => (string) ($data['Key'] ?? '') === 'PRI',
                    'position' => ((int) $index) + 1,
                ];
            })
            ->filter(fn (array $column): bool => $column['name'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function validatedColumnRows(Request $request, ProviderDatabase $database): array
    {
        $validated = $request->validate([
            'columns' => ['nullable', 'array'],
            'columns.*.enabled' => ['nullable', 'boolean'],
            'columns.*.database_table_id' => ['required', 'integer'],
            'columns.*.column_name' => ['required', 'string', 'max:255'],
            'columns.*.column_order' => ['nullable', 'integer', 'min:1'],
            'columns.*.default_encryption_type' => ['required', Rule::in(DatabaseColumn::ENCRYPTION_TYPES)],
            'columns.*.fhe_context_id' => ['nullable', Rule::exists('fhe_contexts', 'id')],
        ]);

        $allowedTableIds = $database->databaseTables->pluck('id')->all();
        $primaryColumnsByTable = $this->primaryColumnsByTable($database);

        $rows = collect($validated['columns'] ?? [])
            ->map(function (array $row) use ($allowedTableIds): array {
                $row['enabled'] = (bool) ($row['enabled'] ?? false);
                $row['database_table_id'] = (int) $row['database_table_id'];
                $row['column_order'] = isset($row['column_order']) ? (int) $row['column_order'] : null;

                if (! in_array($row['database_table_id'], $allowedTableIds, true)) {
                    throw ValidationException::withMessages([
                        'columns' => 'Invalid database table selected.',
                    ]);
                }

                if ($row['enabled'] && $this->isFheEncryption($row['default_encryption_type'])) {
                    if (empty($row['fhe_context_id'])) {
                        throw ValidationException::withMessages([
                            'columns' => 'FHE columns require an FHE context.',
                        ]);
                    }
                }

                return $row;
            })
            ->values();

        $rowsByTableAndColumn = $rows->keyBy(
            fn (array $row): string => $row['database_table_id'].'|'.$row['column_name']
        );

        foreach ($database->databaseTables as $table) {
            foreach ($primaryColumnsByTable[$table->id] ?? [] as $primaryColumn) {
                $row = $rowsByTableAndColumn->get($table->id.'|'.$primaryColumn);

                if (! $row || ! $row['enabled'] || $row['default_encryption_type'] !== 'plaintext') {
                    throw ValidationException::withMessages([
                        'columns' => "Primary key column {$table->table_name}.{$primaryColumn} must be included with plaintext encryption.",
                    ]);
                }
            }
        }

        return $rows->all();
    }

    /**
     * @return array<int, list<string>>
     */
    private function primaryColumnsByTable(ProviderDatabase $database): array
    {
        $primaryColumns = [];

        foreach ($database->databaseTables as $table) {
            $primaryColumns[$table->id] = $this->primaryKeyColumnNames($database->name, $table->table_name);
        }

        return $primaryColumns;
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

    private function isFheEncryption(?string $encryption): bool
    {
        return is_string($encryption) && str_starts_with($encryption, 'fhe-');
    }
}
