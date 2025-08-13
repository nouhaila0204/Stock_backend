<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class ResponsableStockMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check() || !auth()->user()->isResponsableStock()) {
            return response()->json(['message' => 'Accès réservé au Responsable Stock.'], 403);
        }

        return $next($request);
    }
}

