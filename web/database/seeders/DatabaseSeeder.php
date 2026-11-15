<?php

namespace Database\Seeders;

use App\Models\DatabaseColumn;
use App\Models\DatabaseTable;
use App\Models\FheContext;
use App\Models\FheJob;
use App\Models\FheKeyRegistry;
use App\Models\Library;
use App\Models\ProviderDatabase;
use App\Models\Scheme;
use App\Models\Share;
use App\Models\ShareItem;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Library::firstOrCreate([
            'id' => 1
        ], [
            'name' => 'OpenFHE',
            'is_active' => true
        ]);

        Scheme::firstOrCreate(
            [
                'id' => 1
            ],
            [
                'library_id' => 1,
                'scheme_name' => 'BFV',
                'configuration_json' => [
                    'parameters' => [
                        [
                            'name' => 'N',
                            'type' => 'integer',
                            'default_value' => '16384',
                            'is_required' => true,
                            'description' => 'Ring Dimension',
                        ],
                        [
                            'name' => 'depth',
                            'type' => 'integer',
                            'default_value' => '1',
                            'is_required' => true,
                            'description' => 'Multiplicative Depth',
                        ],
                        [
                            'name' => 't',
                            'type' => 'integer',
                            'default_value' => '536903681',
                            'is_required' => true,
                            'description' => 'Plaintext Modulus',
                        ],
                    ],
                ],
                'is_active' => true,
            ]
        );

        $users = [
            [
                'id' => 1,
                'email' => 'admin@edbarchive.com',
                'name' => 'Administrator',
                'password' => 'edbarchive',
                'role' => 'Data Provider',
            ],
            [
                'id' => 2,
                'email' => 'thirdparty@edbarchive.com',
                'name' => 'Third Party',
                'password' => 'edbarchive',
                'role' => 'Third Party',
            ],
        ];

        foreach ($users as $user) {
            User::firstOrCreate(
                [
                    'id' => $user['id']
                ],
                [
                    'email' => $user['email'],
                    'name' => $user['name'],
                    'password' => Hash::make($user['password']),
                    'role' => $user['role'],
                ]
            );
        }

        DB::transaction(function (): void {
            $context = FheContext::firstOrCreate(
                [
                    'id' => 1,
                ],
                [
                    'name' => 'OpenFHE BFV Context',
                    'scheme_id' => 1,
                    'scheme' => 'BFV',
                    'parameters_json' => [
                        'N' => 16384,
                        'depth' => 1,
                        't' => 536903681,
                    ],
                    'context_file_ref' => null,
                    'context_status' => FheContext::STATUS_QUEUED,
                ]
            );

            $contextJob = FheJob::firstOrCreate(
                [
                    'job_type' => FheJob::TYPE_CREATE_CONTEXT,
                    'pk' => $context->id,
                ],
                [
                    'status' => FheJob::STATUS_PENDING,
                    'input_payload' => [
                        'context_id' => $context->id,
                        'context_name' => $context->name,
                        'scheme_id' => $context->scheme_id,
                        'scheme_name' => $context->scheme,
                        'library_id' => 1,
                        'library_name' => 'OpenFHE',
                        'parameters' => $context->parameters_json,
                    ],
                    'created_by' => 1,
                ]
            );

            $contextFileRef = "private/{$contextJob->id}/context.bin";

            if ($contextJob->wasRecentlyCreated) {
                $context->update([
                    'context_file_ref' => $contextFileRef,
                    'context_status' => FheContext::STATUS_QUEUED,
                ]);
            }

            $keyRegistry = FheKeyRegistry::firstOrCreate(
                [
                    'id' => 1,
                ],
                [
                    'owner_user_id' => 2,
                    'fhe_context_id' => $context->id,
                    'keyset_ref' => '',
                    'generation_status' => FheKeyRegistry::GENERATION_QUEUED,
                    'key_status' => 'active',
                ]
            );

            $keyJob = FheJob::firstOrCreate(
                [
                    'job_type' => FheJob::TYPE_CREATE_KEYPAIR,
                    'pk' => $keyRegistry->id,
                ],
                [
                    'status' => FheJob::STATUS_PENDING,
                    'input_payload' => [
                        'key_id' => $keyRegistry->id,
                        'owner_id' => $keyRegistry->owner_user_id,
                        'owner_name' => 'Third Party',
                        'context_id' => $context->id,
                        'context_name' => $context->name,
                        'context_file_ref' => $contextFileRef,
                        'scheme_id' => $context->scheme_id,
                        'scheme_name' => $context->scheme,
                        'library_id' => 1,
                        'library_name' => 'OpenFHE',
                    ],
                    'created_by' => 1,
                ]
            );

            $keysetRef = "private/{$keyJob->id}";

            if ($keyJob->wasRecentlyCreated) {
                $keyRegistry->update([
                    'keyset_ref' => $keysetRef,
                    'generation_status' => FheKeyRegistry::GENERATION_QUEUED,
                ]);
            }

            $database = ProviderDatabase::firstOrCreate(
                [
                    'id' => 1,
                ],
                [
                    'provider_user_id' => 1,
                    'name' => 'tpcc',
                    'description' => null,
                ]
            );

            $tpccColumns = [
                'customer' => [
                    'c_id',
                    'c_d_id',
                    'c_w_id',
                    'c_first',
                    'c_middle',
                    'c_last',
                    'c_street_1',
                    'c_street_2',
                    'c_city',
                    'c_state',
                    'c_zip',
                    'c_phone',
                    'c_since',
                    'c_credit',
                    'c_credit_lim',
                    'c_discount',
                    'c_balance',
                    'c_ytd_payment',
                    'c_payment_cnt',
                    'c_delivery_cnt',
                    'c_data',
                ],
                'district' => [
                    'd_id',
                    'd_w_id',
                    'd_ytd',
                    'd_tax',
                    'd_next_o_id',
                    'd_name',
                    'd_street_1',
                    'd_street_2',
                    'd_city',
                    'd_state',
                    'd_zip',
                ],
                'history' => [
                    'h_c_id',
                    'h_c_d_id',
                    'h_c_w_id',
                    'h_d_id',
                    'h_w_id',
                    'h_date',
                    'h_amount',
                    'h_data',
                ],
                'item' => [
                    'i_id',
                    'i_im_id',
                    'i_name',
                    'i_price',
                    'i_data',
                ],
                'new_order' => [
                    'no_w_id',
                    'no_d_id',
                    'no_o_id',
                ],
                'order_line' => [
                    'ol_w_id',
                    'ol_d_id',
                    'ol_o_id',
                    'ol_number',
                    'ol_i_id',
                    'ol_delivery_d',
                    'ol_amount',
                    'ol_supply_w_id',
                    'ol_quantity',
                    'ol_dist_info',
                ],
                'orders' => [
                    'o_id',
                    'o_w_id',
                    'o_d_id',
                    'o_c_id',
                    'o_carrier_id',
                    'o_ol_cnt',
                    'o_all_local',
                    'o_entry_d',
                ],
                'stock' => [
                    's_i_id',
                    's_w_id',
                    's_quantity',
                    's_dist_01',
                    's_dist_02',
                    's_dist_03',
                    's_dist_04',
                    's_dist_05',
                    's_dist_06',
                    's_dist_07',
                    's_dist_08',
                    's_dist_09',
                    's_dist_10',
                    's_ytd',
                    's_order_cnt',
                    's_remote_cnt',
                    's_data',
                ],
                'warehouse' => [
                    'w_id',
                    'w_ytd',
                    'w_tax',
                    'w_name',
                    'w_street_1',
                    'w_street_2',
                    'w_city',
                    'w_state',
                    'w_zip',
                ],
            ];

            $fheColumns = [
                'customer.c_payment_cnt',
                'customer.c_delivery_cnt',
                'district.d_next_o_id',
                'item.i_im_id',
                'orders.o_carrier_id',
                'orders.o_ol_cnt',
                'orders.o_all_local',
            ];

            foreach ($tpccColumns as $tableName => $columnNames) {
                $table = DatabaseTable::firstOrCreate([
                    'database_id' => $database->id,
                    'table_name' => $tableName,
                ]);

                foreach ($columnNames as $columnIndex => $columnName) {
                    $usesFhe = in_array("{$tableName}.{$columnName}", $fheColumns, true);

                    DatabaseColumn::firstOrCreate(
                        [
                            'database_table_id' => $table->id,
                            'column_name' => $columnName,
                        ],
                        [
                            'column_order' => $columnIndex + 1,
                            'default_encryption_type' => $usesFhe ? 'fhe-secure' : 'plaintext',
                            'fhe_context_id' => $usesFhe ? $context->id : null,
                        ]
                    );
                }
            }

            $shareScenarios = [
                'district-workload' => [
                    'district.d_next_o_id',
                ],
                'district-customer-item-workload' => [
                    'district.d_next_o_id',
                    'customer.c_payment_cnt',
                    'item.i_im_id',
                ],
            ];

            $primaryColumns = [
                'customer' => ['c_w_id', 'c_d_id', 'c_id'],
                'district' => ['d_w_id', 'd_id'],
                'history' => [],
                'item' => ['i_id'],
                'new_order' => ['no_w_id', 'no_d_id', 'no_o_id'],
                'order_line' => ['ol_w_id', 'ol_d_id', 'ol_o_id', 'ol_number'],
                'orders' => ['o_w_id', 'o_d_id', 'o_id'],
                'stock' => ['s_w_id', 's_i_id'],
                'warehouse' => ['w_id'],
            ];

            $databaseColumns = DatabaseColumn::query()
                ->whereHas('databaseTable', fn ($query) => $query->where('database_id', $database->id))
                ->with('databaseTable')
                ->orderBy('database_table_id')
                ->orderBy('column_order')
                ->get();

            foreach ($shareScenarios as $releaseVersion => $encryptedColumns) {
                $share = Share::firstOrCreate(
                    [
                        'owner_user_id' => 1,
                        'recipient_user_id' => 2,
                        'database_id' => $database->id,
                        'release_version' => $releaseVersion,
                    ],
                    [
                        'status' => 'draft',
                        'bundle_ref' => null,
                        'bundle_hash' => null,
                        'bundle_status' => 'pending',
                        'expires_at' => null,
                        'revoked_at' => null,
                    ]
                );

                $backupItems = [];

                foreach ($databaseColumns as $column) {
                    $qualifiedColumn = "{$column->databaseTable->table_name}.{$column->column_name}";
                    $usesFhe = in_array($qualifiedColumn, $encryptedColumns, true);

                    $shareItem = ShareItem::firstOrCreate(
                        [
                            'share_id' => $share->id,
                            'database_table_id' => $column->database_table_id,
                            'database_column_id' => $column->id,
                        ],
                        [
                            'encryption' => $usesFhe ? 'fhe-secure' : 'plaintext',
                            'fhe_context_id' => $usesFhe ? $context->id : null,
                            'key_registry_id' => $usesFhe ? $keyRegistry->id : null,
                        ]
                    );

                    $itemPayload = [
                        'share_item_id' => $shareItem->id,
                        'table_id' => $column->database_table_id,
                        'table_name' => $column->databaseTable->table_name,
                        'column_id' => $column->id,
                        'column_name' => $column->column_name,
                        'pk' => in_array(
                            $column->column_name,
                            $primaryColumns[$column->databaseTable->table_name],
                            true
                        ),
                        'encryption' => $usesFhe ? 'fhe-secure' : 'plaintext',
                    ];

                    if ($usesFhe) {
                        $itemPayload = array_merge($itemPayload, [
                            'key_id' => $keyRegistry->id,
                            'keypair_file_ref' => $keysetRef,
                            'context_id' => $context->id,
                            'context_name' => $context->name,
                            'context_file_ref' => $contextFileRef,
                            'scheme_id' => $context->scheme_id,
                            'scheme_name' => $context->scheme,
                            'library_id' => 1,
                            'library_name' => 'OpenFHE',
                        ]);
                    }

                    $backupItems[] = $itemPayload;
                }

                $backupJob = FheJob::firstOrCreate(
                    [
                        'job_type' => FheJob::TYPE_BACKUP,
                        'pk' => $share->id,
                    ],
                    [
                        'status' => FheJob::STATUS_PENDING,
                        'input_payload' => [
                            'share_id' => $share->id,
                            'recipient_id' => 2,
                            'recipient_name' => 'Third Party',
                            'database_id' => $database->id,
                            'database_name' => $database->name,
                            'release_version' => $share->release_version,
                            'items' => $backupItems,
                        ],
                        'created_by' => 1,
                    ]
                );

                if ($backupJob->wasRecentlyCreated) {
                    $share->update([
                        'bundle_status' => 'processing',
                    ]);
                }
            }
        });
    }
}
