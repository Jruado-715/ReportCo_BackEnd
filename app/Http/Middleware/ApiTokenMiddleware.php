<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization', '');
        if (! str_starts_with($header, 'Bearer ')) {
            return response()->json(['success' => false, 'message' => 'Authentication required.'], 401);
        }

        $plain = trim(substr($header, 7));
        if ($plain === '') {
            return response()->json(['success' => false, 'message' => 'Authentication required.'], 401);
        }

        $token = ApiToken::with('user')->where('token_hash', hash('sha256', $plain))->first();
        if (! $token || ($token->expires_at && $token->expires_at->isPast())) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired token.'], 401);
        }

        $request->setUserResolver(fn () => $token->user);
        return $next($request);
    }
}
