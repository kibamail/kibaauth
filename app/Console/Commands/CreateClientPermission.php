<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Permission;
use Illuminate\Console\Command;

class CreateClientPermission extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'client:permission
                            {client : The client ID or name}
                            {name : The permission name}
                            {--slug= : Custom slug for the permission}
                            {--description= : Description of the permission}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a permission for an OAuth client';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $clientIdentifier = $this->argument('client');
        $name = $this->argument('name');
        $slug = $this->option('slug');
        $description = $this->option('description');

        // Find the client by ID or name
        $client = $this->findClient($clientIdentifier);

        if (!$client) {
            $this->error("Client not found: {$clientIdentifier}");
            $this->info('Available clients:');
            $this->displayAvailableClients();
            return Command::FAILURE;
        }

        // Generate slug if not provided
        if (empty($slug)) {
            $slug = Permission::generateUniqueSlug($name, $client->id);
        } else {
            // Check if custom slug is unique for this client
            if (Permission::where('slug', $slug)->where('client_id', $client->id)->exists()) {
                $originalSlug = $slug;
                $slug = Permission::generateUniqueSlug($slug, $client->id);
                $this->warn("Slug '{$originalSlug}' was already taken, using '{$slug}' instead.");
            }
        }

        // Create the permission
        $permission = Permission::create([
            'name' => $name,
            'description' => $description,
            'slug' => $slug,
            'client_id' => $client->id,
        ]);

        // Auto-attach the new permission to all "Administrators" teams for this client
        $this->attachPermissionToAdministratorTeams($permission, $client);

        $this->info("Permission created successfully!");
        $this->table(
            ['Field', 'Value'],
            [
                ['ID', $permission->id],
                ['Name', $permission->name],
                ['Slug', $permission->slug],
                ['Description', $permission->description ?? 'N/A'],
                ['Client ID', $permission->client_id],
                ['Client Name', $client->name],
                ['Created At', $permission->created_at->format('Y-m-d H:i:s')],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Find a client by ID or name.
     */
    protected function findClient(string $identifier): ?Client
    {
        // Try to find by ID first
        $client = Client::find($identifier);

        // If not found by ID, try by name
        if (!$client) {
            $client = Client::where('name', $identifier)->first();
        }

        return $client;
    }

    /**
     * Display available clients.
     */
    protected function displayAvailableClients(): void
    {
        $clients = Client::select('id', 'name')->get();

        if ($clients->isEmpty()) {
            $this->warn('No OAuth clients found. Create a client first using passport:client command.');
            return;
        }

        $this->table(
            ['ID', 'Name'],
            $clients->map(fn($client) => [$client->id, $client->name])->toArray()
        );
    }

    /**
     * Attach the permission to all "Administrators" teams for this client.
     */
    protected function attachPermissionToAdministratorTeams(Permission $permission, Client $client): void
    {
        // Find all workspaces for this client
        $workspaces = $client->workspaces;

        foreach ($workspaces as $workspace) {
            // Find the "Administrators" team in each workspace
            $adminTeam = $workspace->teams()->where('name', 'Administrators')->first();

            if ($adminTeam) {
                // Attach the permission to the Administrators team
                $adminTeam->permissions()->attach($permission->id);
                $this->info("Permission attached to Administrators team in workspace: {$workspace->name}");
            }
        }
    }
}
