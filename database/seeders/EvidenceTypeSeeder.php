<?php

namespace Database\Seeders;

use App\Models\EvidenceType;
use Illuminate\Database\Seeder;

class EvidenceTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['code' => 'support_ticket', 'label' => 'Support Ticket'],
            ['code' => 'log', 'label' => 'Log'],
            ['code' => 'code_snippet', 'label' => 'Code Snippet'],
            ['code' => 'db_snapshot', 'label' => 'Database Snapshot'],
            ['code' => 'api_response', 'label' => 'API Response'],
            ['code' => 'screenshot', 'label' => 'Screenshot'],
            ['code' => 'configuration', 'label' => 'Configuration'],
            ['code' => 'deployment_history', 'label' => 'Deployment History'],
        ];

        foreach ($types as $type) {
            EvidenceType::firstOrCreate(['code' => $type['code']], $type);
        }
    }
}
