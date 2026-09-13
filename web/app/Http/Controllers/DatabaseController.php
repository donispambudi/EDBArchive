<?php

namespace App\Http\Controllers;

use App\Models\DatabaseColumn;
use App\Models\DatabaseTable;
use App\Models\ProviderDatabase;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DatabaseController extends Controller
{
    private const EXCLUDED_DATABASES = [
        'information_schema',
        'mysql',
        'performance_schema',
        'sys',
    ];

    public function index(): View
    {
        $databases = ProviderDatabase::query()
            ->with('provider')
            ->withCount('databaseTables')
            ->latest()
            ->paginate(10);

        return view('databases.index', [
            'databases' => $databases,
        ]);
    }

    public function create(): View
    {
        return view('databases.create', $this->formData(new ProviderDatabase()));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedData($request);

        DB::transaction(function () use ($validated): void {
            $database = ProviderDatabase::create($validated['database']);
            $this->syncDatabaseTables($database, $validated['table_names']);
        });

        return redirect()
            ->route('databases.index')
            ->with('success', 'Database metadata created successfully.');
    }

    public function show(ProviderDatabase $database): View
    {
        $database->load(['provider', 'databaseTables.columns']);

        return view('databases.show', [
            'database' => $database,
        ]);
    }

    public function edit(ProviderDatabase $database): View
    {
        $database->loadMissing('databaseTables');

        return view('databases.edit', $this->formData($database));
    }

    public function update(Request $request, ProviderDatabase $database): RedirectResponse
    {
        $validated = $this->validatedData($request, $database);

        DB::transaction(function () use ($database, $validated): void {
            $database->update($validated['database']);
            $this->syncDatabaseTables($database, $validated['table_names']);
        });

        return redirect()
            ->route('databases.index')
            ->with('success', 'Database metadata updated successfully.');
    }

    public function destroy(ProviderDatabase $database): RedirectResponse
    {
        $database->delete();

        return redirect()
            ->route('databases.index')
            ->with('success', 'Database metadata deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(ProviderDatabase $database): array
    {
        $database->loadMissing('databaseTables');
        $localDatabases = $this->localDatabaseNames($database);

        return [
            'database' => $database,
            'localDatabases' => $localDatabases,
            'localTablesByDatabase' => $this->localTablesByDatabase($localDatabases, $database),
            'selectedTables' => old('table_names', $database->databaseTables->pluck('table_name')->all()),
            'providers' => User::query()
                ->where('role', 'Data Provider')
                ->orderBy('name')
                ->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?ProviderDatabase $database = null): array
    {
        $localDatabases = $this->localDatabaseNames($database);
        $selectedDatabase = (string) $request->input('name');
        $localTables = $this->localTableNames($selectedDatabase, $database);

        $validated = $request->validate([
            'provider_user_id' => [
                'required',
                Rule::exists('users', 'id')->where('role', 'Data Provider'),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::in($localDatabases),
                Rule::unique('databases', 'name')->ignore($database?->id),
            ],
            'description' => ['nullable', 'string'],
            'table_names' => ['required', 'array', 'min:1'],
            'table_names.*' => ['required', 'string', 'max:255', Rule::in($localTables)],
        ]);

        return [
            'database' => collect($validated)
                ->only(['provider_user_id', 'name', 'description'])
                ->all(),
            'table_names' => array_values(array_unique($validated['table_names'])),
        ];
    }

    /**
     * @return list<string>
     */
    private function localDatabaseNames(?ProviderDatabase $database = null): array
    {
        try {
            $databaseRows = DB::select('SHOW DATABASES');
        } catch (QueryException) {
            return $database?->name ? [$database->name] : [];
        }

        $appDatabase = config('database.connections.'.config('database.default').'.database');
        $registeredNames = ProviderDatabase::query()
            ->when($database?->exists, fn ($query) => $query->whereKeyNot($database->id))
            ->pluck('name')
            ->all();

        $excluded = array_map('strtolower', array_merge(
            self::EXCLUDED_DATABASES,
            [$appDatabase],
            $registeredNames,
        ));

        $names = collect($databaseRows)
            ->map(fn (object $row): string => (string) array_values((array) $row)[0])
            ->filter(fn (string $name): bool => ! in_array(strtolower($name), $excluded, true))
            ->values();

        if ($database?->name && ! $names->contains($database->name)) {
            $names->push($database->name);
        }

        return $names->sort()->values()->all();
    }

    /**
     * @param  list<string>  $databaseNames
     * @return array<string, list<string>>
     */
    private function localTablesByDatabase(array $databaseNames, ?ProviderDatabase $database = null): array
    {
        $tablesByDatabase = [];

        foreach ($databaseNames as $databaseName) {
            $tablesByDatabase[$databaseName] = $this->localTableNames($databaseName, $database);
        }

        return $tablesByDatabase;
    }

    /**
     * @return list<string>
     */
    private function localTableNames(string $databaseName, ?ProviderDatabase $database = null): array
    {
        if ($databaseName === '') {
            return [];
        }

        try {
            $tableRows = DB::select('SHOW FULL TABLES FROM '.$this->quoteIdentifier($databaseName));
        } catch (QueryException) {
            if ($database?->name === $databaseName) {
                return $database->databaseTables->pluck('table_name')->sort()->values()->all();
            }

            return [];
        }

        return collect($tableRows)
            ->filter(fn (object $row): bool => (string) array_values((array) $row)[1] === 'BASE TABLE')
            ->map(fn (object $row): string => (string) array_values((array) $row)[0])
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $tableNames
     */
    private function syncDatabaseTables(ProviderDatabase $database, array $tableNames): void
    {
        DatabaseTable::query()
            ->where('database_id', $database->id)
            ->whereNotIn('table_name', $tableNames)
            ->delete();

        foreach ($tableNames as $tableName) {
            $table = DatabaseTable::firstOrCreate([
                'database_id' => $database->id,
                'table_name' => $tableName,
            ]);

            $this->syncPrimaryKeyColumns($database, $table);
        }
    }

    private function syncPrimaryKeyColumns(ProviderDatabase $database, DatabaseTable $table): void
    {
        $columnOrders = $this->columnOrders($database->name, $table->table_name);

        foreach ($this->primaryKeyColumnNames($database->name, $table->table_name) as $columnName) {
            DatabaseColumn::updateOrCreate([
                'database_table_id' => $table->id,
                'column_name' => $columnName,
            ], [
                'column_order' => $columnOrders[$columnName] ?? null,
                'default_encryption_type' => 'plaintext',
                'fhe_context_id' => null,
            ]);
        }
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
