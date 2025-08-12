<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class KeepClientParameters
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if ('code' === $request->get('response_type', false)) {
            $allParams = $request->only([
                'client_id', 'redirect_uri', 'scope', 'prompt', 'state', 'response_type'
            ]);

            Log::info('KeepClientParametersMiddleware:', ['client' => $allParams]);

            $request->session()->put('client', $allParams);
        }

        return $next($request);
    }
}
