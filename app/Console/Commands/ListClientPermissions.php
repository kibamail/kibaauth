<?php

namespace App\Console\Commands;

use App\Models\Client;
use Illuminate\Console\Command;

class ListClientPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'client:permissions
                            {client : The client ID or name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all permissions for an OAuth client';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $clientIdentifier = $this->argument('client');

        // Find the client by ID or name
        $client = $this->findClient($clientIdentifier);

        if (!$client) {
            $this->error("Client not found: {$clientIdentifier}");
            $this->info('Available clients:');
            $this->displayAvailableClients();
            return Command::FAILURE;
        }

        $permissions = $client->permissions()->orderBy('created_at', 'desc')->get();

        if ($permissions->isEmpty()) {
            $this->info("No permissions found for client: {$client->name}");
            $this->info("Use 'php artisan client:permission' to create permissions.");
            return Command::SUCCESS;
        }

        $this->info("Permissions for client: {$client->name} (ID: {$client->id})");
        $this->newLine();

        $this->table(
            ['ID', 'Name', 'Slug', 'Description', 'Created At'],
            $permissions->map(function ($permission) {
                return [
                    $permission->id,
                    $permission->name,
                    $permission->slug,
                    $permission->description ?? 'N/A',
                    $permission->created_at->format('Y-m-d H:i:s'),
                ];
            })->toArray()
        );

        $this->info("Total permissions: {$permissions->count()}");

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
}
