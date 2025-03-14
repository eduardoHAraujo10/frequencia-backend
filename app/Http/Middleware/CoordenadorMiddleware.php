<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CoordenadorMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || $request->user()->tipo !== 'coordenador') {
            return response()->json([
                'status' => 'error',
                'message' => 'Acesso não autorizado. Apenas coordenadores podem acessar este recurso.'
            ], 403);
        }

        return $next($request);
    }
} 