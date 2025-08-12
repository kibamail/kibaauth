import './bootstrap';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import React from 'react';
import { createRoot } from 'react-dom/client';
import { Route, Switch } from 'wouter';

const queryClient = new QueryClient();

import { AuthLayout } from './layouts/auth';
import { EmailConfirm } from './pages/email/confirm';
import { Login } from './pages/login/login';
import { ForgotPassword } from './pages/passwords/forgot/forgot';
import { ResetPassword } from './pages/passwords/reset/reset';
import { Register } from './pages/register/register';

export function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <AuthLayout>
        <Switch>
          <Route path="/auth/register" component={Register} />
          <Route path="/auth/login" component={Login} />
          <Route path="/auth/register/email/confirm" component={EmailConfirm} />
          <Route path="/auth/passwords/forgot" component={ForgotPassword} />
          <Route
            path="/auth/passwords/reset/:token"
            component={ResetPassword}
          />
        </Switch>
      </AuthLayout>
    </QueryClientProvider>
  );
}

createRoot(document.getElementById('app') as HTMLDivElement).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>,
);
