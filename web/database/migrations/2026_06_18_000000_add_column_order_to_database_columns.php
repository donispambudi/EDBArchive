<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('database_columns', function (Blueprint $table) {
            if (! Schema::hasColumn('database_columns', 'column_order')) {
                $table->unsignedInteger('column_order')
                    ->nullable()
                    ->after('column_name')
                    ->comment('One-based physical position of the column in the source table.');

                $table->index(['database_table_id', 'column_order']);
            }
        });

        $this->backfillColumnOrder();
    }

    public function down(): void
    {
        Schema::table('database_columns', function (Blueprint $table) {
            if (Schema::hasColumn('database_columns', 'column_order')) {
                $table->dropIndex(['database_table_id', 'column_order']);
                $table->dropColumn('column_order');
            }
        });
    }

    private function backfillColumnOrder(): void
    {
        $tables = DB::table('database_tables')
            ->join('databases', 'databases.id', '=', 'database_tables.database_id')
            ->select([
                'database_tables.id',
                'database_tables.table_name',
                'databases.name as database_name',
            ])
            ->get();

        foreach ($tables as $table) {
            foreach ($this->columnOrders((string) $table->database_name, (string) $table->table_name) as $columnName => $columnOrder) {
                DB::table('database_columns')
                    ->where('database_table_id', $table->id)
                    ->where('column_name', $columnName)
                    ->update(['column_order' => $columnOrder]);
            }
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

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }
};
