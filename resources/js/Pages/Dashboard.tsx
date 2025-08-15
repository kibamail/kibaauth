import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Client } from '@/types';
import { Head } from '@inertiajs/react';

interface DashboardProps {
  clients: Client[];
}

export default function Dashboard({ clients }: DashboardProps) {
  console.log({ clients });
  return (
    <AuthenticatedLayout
      header={
        <h2 className="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200">
          Dashboard
        </h2>
      }
    >
      <Head title="Dashboard" />

      <div className="py-12">
        <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
          <div className="mb-8">
            <h3 className="mb-6 text-2xl font-bold text-gray-900 dark:text-gray-100">
              Applications
            </h3>
            <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
              {clients.map((client) => (
                <div
                  key={client.id}
                  className="overflow-hidden border border-gray-200 bg-white shadow-sm sm:rounded-lg dark:border-gray-700 dark:bg-gray-800"
                >
                  <div className="p-6">
                    <div className="mb-4 flex items-center justify-between">
                      <h4 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {client.name}
                      </h4>
                      <span
                        className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                          client.revoked
                            ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                            : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                        }`}
                      >
                        {client.revoked ? 'Revoked' : 'Active'}
                      </span>
                    </div>
                    <div className="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                      <div>
                        <span className="font-medium">Client ID:</span>
                        <p className="font-mono text-xs break-all">
                          {client.id}
                        </p>
                      </div>
                      <div>
                        <span className="font-medium">Redirect URI:</span>
                        <p className="break-all">
                          {client.redirect_uris?.join(',')}
                        </p>
                      </div>
                      <div>
                        <span className="font-medium">Created:</span>
                        <p>
                          {new Date(client.created_at).toLocaleDateString()}
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
            {clients.length === 0 && (
              <div className="py-12 text-center">
                <div className="text-gray-500 dark:text-gray-400">
                  No applications found.
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
