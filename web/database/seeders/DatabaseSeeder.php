<?php

namespace Database\Seeders;

use App\Models\Library;
use App\Models\Scheme;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
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
    }
}
