<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | FHE contexts
        |--------------------------------------------------------------------------
        */
        Schema::create('fhe_contexts', function (Blueprint $table) {
            $table->id()->comment('Primary key of the FHE context.');

            $table->string('name')->comment('Display name of the FHE context.');
            $table->string('scheme')->comment('Legacy scheme name retained for compatibility.');
            $table->json('parameters_json')->nullable()->comment('Resolved parameter values used to generate the context.');
            $table->string('context_file_ref')->nullable()->comment('Relative storage reference of the generated context artifact.');

            $table->timestamps();

            $table->index('scheme');
        });

        /*
        |--------------------------------------------------------------------------
        | FHE key registry
        |--------------------------------------------------------------------------
        */
        Schema::create('fhe_key_registry', function (Blueprint $table) {
            $table->id()->comment('Primary key of the FHE key registry record.');

            $table->foreignId('owner_user_id')
                ->comment('Recipient user that owns the generated keyset.')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('fhe_context_id')
                ->comment('FHE context used to generate the keyset.')
                ->constrained('fhe_contexts')
                ->restrictOnDelete();

            $table->string('keyset_ref')->comment('Relative storage reference of the generated keypair artifact.');

            $table->string('key_status')->default('active')->comment('Lifecycle status: active, rotated, revoked, or expired.');

            $table->timestamps();

            $table->index(['owner_user_id', 'fhe_context_id']);
            $table->index('key_status');
        });

        /*
        |--------------------------------------------------------------------------
        | Databases
        |--------------------------------------------------------------------------
        | Provider-owned logical database.
        */
        Schema::create('databases', function (Blueprint $table) {
            $table->id()->comment('Primary key of the registered provider database.');

            $table->foreignId('provider_user_id')
                ->comment('Data Provider user that owns the registered database.')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('name')->comment('Database name used to access the registered source database.');
            $table->text('description')->nullable()->comment('Optional description of the registered database.');

            $table->timestamps();

            $table->index('provider_user_id');
        });

        /*
        |--------------------------------------------------------------------------
        | Database tables
        |--------------------------------------------------------------------------
        */
        Schema::create('database_tables', function (Blueprint $table) {
            $table->id()->comment('Primary key of the registered database table.');

            $table->foreignId('database_id')
                ->comment('Registered database containing this table.')
                ->constrained('databases')
                ->cascadeOnDelete();

            $table->string('table_name')->comment('Physical table name in the source database.');

            $table->timestamps();

            $table->unique(['database_id', 'table_name']);
        });

        /*
        |--------------------------------------------------------------------------
        | Database columns
        |--------------------------------------------------------------------------
        | Default protection profile for each column.
        */
        Schema::create('database_columns', function (Blueprint $table) {
            $table->id()->comment('Primary key of the configured database column.');

            $table->foreignId('database_table_id')
                ->comment('Registered database table containing this column.')
                ->constrained('database_tables')
                ->cascadeOnDelete();

            $table->string('column_name')->comment('Physical column name in the source table.');

            /*
             * plaintext, fhe-secure
             */
            $table->string('default_encryption_type')->default('plaintext')->comment('Default encryption type applied when sharing this column.');

            $table->foreignId('fhe_context_id')
                ->nullable()
                ->comment('Default FHE context used when the column encryption type is FHE.')
                ->constrained('fhe_contexts')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['database_table_id', 'column_name']);
            $table->index('default_encryption_type');
        });

        /*
        |--------------------------------------------------------------------------
        | Shares
        |--------------------------------------------------------------------------
        | One share = one concrete database export/release to one recipient.
        */
        Schema::create('shares', function (Blueprint $table) {
            $table->id()->comment('Primary key of the database share.');

            $table->foreignId('owner_user_id')
                ->comment('Data Provider user that owns and creates the share.')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('recipient_user_id')
                ->comment('Third Party user that receives the share.')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('database_id')
                ->comment('Registered database included in the share.')
                ->constrained('databases')
                ->cascadeOnDelete();

            $table->string('release_version')->nullable()->comment('Human-readable release identifier of the share.');

            /*
             * draft, released, expired, revoked
            */
            $table->string('status')->default('draft')->comment('Share lifecycle status: draft, released, expired, or revoked.');
            $table->string('bundle_ref')->nullable()->comment('Relative storage reference of the generated share bundle.');
            $table->string('bundle_hash')->nullable()->comment('Cryptographic hash used to identify and verify the generated bundle.');
            $table->string('bundle_status')->default('pending')->comment('Bundle generation status: pending, processing, or done.');

            $table->timestamp('expires_at')->nullable()->comment('Timestamp when access to the share expires.');
            $table->timestamp('revoked_at')->nullable()->comment('Timestamp when the share was revoked.');

            $table->timestamps();

            $table->index(['owner_user_id', 'recipient_user_id']);
            $table->index('database_id');
            $table->index('status');
            $table->index('bundle_status');
        });

        /*
        |--------------------------------------------------------------------------
        | Share items
        |--------------------------------------------------------------------------
        | Tables/columns included in a share with share-specific protection rules.
        |
        | Rule:
        | For custom shares, only rows listed here are exported/shared.
        */
        Schema::create('share_items', function (Blueprint $table) {
            $table->id()->comment('Primary key of the share item.');

            $table->foreignId('share_id')
                ->comment('Share containing this selected database column.')
                ->constrained('shares')
                ->cascadeOnDelete();

            $table->foreignId('database_table_id')
                ->comment('Database table containing the selected column.')
                ->constrained('database_tables')
                ->cascadeOnDelete();

            /*
             * Nullable because table-level share item may not target a specific column.
             */
            $table->foreignId('database_column_id')
                ->nullable()
                ->comment('Concrete database column included in the share.')
                ->constrained('database_columns')
                ->cascadeOnDelete();

            /*
             * plaintext, fhe-secure
             */
            $table->string('encryption')->default('plaintext')->comment('Encryption applied to this shared column.');

            $table->foreignId('fhe_context_id')
                ->nullable()
                ->comment('FHE context selected for this shared column.')
                ->constrained('fhe_contexts')
                ->nullOnDelete();

            $table->foreignId('key_registry_id')
                ->nullable()
                ->comment('Recipient keyset selected for this shared column.')
                ->constrained('fhe_key_registry')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['share_id', 'database_table_id', 'database_column_id'], 'share_item_unique');
            $table->index('encryption');
        });

        /*
        |--------------------------------------------------------------------------
        | Share history
        |--------------------------------------------------------------------------
        */
        Schema::create('share_history', function (Blueprint $table) {
            $table->id()->comment('Primary key of the share history record.');

            $table->foreignId('share_id')
                ->comment('Share associated with this history event.')
                ->constrained('shares')
                ->cascadeOnDelete();

            $table->foreignId('parent_share_id')
                ->nullable()
                ->comment('Parent share when this event originated from resharing.')
                ->constrained('shares')
                ->nullOnDelete();

            $table->foreignId('original_owner_user_id')
                ->comment('Original Data Provider owner of the shared database.')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('shared_by_user_id')
                ->comment('User that performed the sharing action.')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('shared_to_user_id')
                ->comment('User that received the share in this event.')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('database_id')
                ->comment('Database associated with the share history event.')
                ->constrained('databases')
                ->cascadeOnDelete();

            /*
             * created, reshared, revoked, expired, released
             */
            $table->string('action')->comment('Recorded share lifecycle action.');

            $table->timestamps();

            $table->index(['share_id', 'action']);
            $table->index(['shared_by_user_id', 'shared_to_user_id']);
            $table->index('database_id');
        });

        /*
        |--------------------------------------------------------------------------
        | Access logs
        |--------------------------------------------------------------------------
        */
        Schema::create('access_logs', function (Blueprint $table) {
            $table->id()->comment('Primary key of the access log record.');

            $table->foreignId('user_id')
                ->nullable()
                ->comment('User that performed the logged action, when available.')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('database_id')
                ->nullable()
                ->comment('Database affected by the logged action, when applicable.')
                ->constrained('databases')
                ->nullOnDelete();

            $table->foreignId('share_id')
                ->nullable()
                ->comment('Share affected by the logged action, when applicable.')
                ->constrained('shares')
                ->nullOnDelete();

            $table->foreignId('database_table_id')
                ->nullable()
                ->comment('Database table affected by the logged action, when applicable.')
                ->constrained('database_tables')
                ->nullOnDelete();

            /*
             * create_database, create_share, release_share, download_manifest,
             * receive_release, revoke_share, view_metadata, export_release
             */
            $table->string('action')->comment('Machine-readable name of the logged action.');

            $table->ipAddress('ip_address')->nullable()->comment('Client IP address associated with the action.');
            $table->text('user_agent')->nullable()->comment('Client user agent associated with the action.');

            $table->timestamps();

            $table->index('action');
            $table->index(['user_id', 'created_at']);
            $table->index(['database_id', 'share_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_logs');
        Schema::dropIfExists('share_history');
        Schema::dropIfExists('share_items');
        Schema::dropIfExists('shares');
        Schema::dropIfExists('database_columns');
        Schema::dropIfExists('database_tables');
        Schema::dropIfExists('databases');
        Schema::dropIfExists('fhe_key_registry');
        Schema::dropIfExists('fhe_contexts');
    }
};
