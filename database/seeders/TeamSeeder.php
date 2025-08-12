<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\Workspace;
use App\Models\Permission;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all existing workspaces
        $workspaces = Workspace::with('client')->get();

        if ($workspaces->isEmpty()) {
            $this->command->info('No workspaces found. Please run WorkspaceSeeder first.');
            return;
        }

        foreach ($workspaces as $workspace) {
            $client = $workspace->client;

            // Get permissions for this client
            $permissions = Permission::where('client_id', $client->id)->get();

            if ($permissions->isEmpty()) {
                $this->command->info("No permissions found for client {$client->name}. Skipping teams for workspace {$workspace->name}.");
                continue;
            }

            // Create sample teams for each workspace
            $teamsData = [
                [
                    'name' => 'Development Team',
                    'description' => 'Responsible for application development and maintenance',
                    'permission_count' => min(3, $permissions->count()),
                ],
                [
                    'name' => 'QA Team',
                    'description' => 'Quality assurance and testing team',
                    'permission_count' => min(2, $permissions->count()),
                ],
                [
                    'name' => 'Admin Team',
                    'description' => 'Administrative team with elevated permissions',
                    'permission_count' => $permissions->count(), // All permissions
                ],
                [
                    'name' => 'Support Team',
                    'description' => 'Customer support and help desk team',
                    'permission_count' => min(2, $permissions->count()),
                ],
            ];

            foreach ($teamsData as $teamData) {
                $team = Team::create([
                    'name' => $teamData['name'],
                    'description' => $teamData['description'],
                    'workspace_id' => $workspace->id,
                ]);

                // Attach random permissions from the client's permissions
                if ($teamData['permission_count'] > 0) {
                    $selectedPermissions = $permissions->random($teamData['permission_count']);
                    $team->permissions()->attach($selectedPermissions->pluck('id'));
                }

                $this->command->info("Created team '{$team->name}' in workspace '{$workspace->name}' with {$teamData['permission_count']} permissions.");
            }
        }

        $this->command->info('Teams seeded successfully!');
    }
}
