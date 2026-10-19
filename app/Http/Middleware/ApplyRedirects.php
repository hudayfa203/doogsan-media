<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ApplyRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && ! $request->is('admin*', 'api/*', 'build/*', 'storage/*')) {
            try {
                $map = Cache::remember('redirects.map', 300, fn () => Redirect::pluck('to_url', 'from_path')->all());
                $path = Redirect::normalize($request->getPathInfo());
                if (isset($map[$path])) {
                    $r = Redirect::where('from_path', $path)->first();
                    $r?->increment('hits');

                    return redirect($map[$path], $r?->status_code ?? 301);
                }
            } catch (\Throwable) {
                // Table may not exist yet during install.
            }
        }

        return $next($request);
    }
}
