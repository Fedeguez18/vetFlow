<?php

namespace App\Http\Middleware;

use App\Services\PosTerminalService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The mostrador only works on PCs an administrator enabled as terminals.
 */
class EnsurePosTerminal
{
    public function __construct(protected PosTerminalService $terminals) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->terminals->current()) {
            return redirect()->route('pos.setup');
        }

        return $next($request);
    }
}
