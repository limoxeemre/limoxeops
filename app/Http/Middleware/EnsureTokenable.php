<?php

namespace App\Http\Middleware;

use App\Models\Agency;
use App\Models\Driver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sanctum token'ının sahibinin beklenen tipte (acente / sürücü) ve aktif olduğunu doğrular.
 * Kullanım: ->middleware(['auth:sanctum', 'tokenable:driver'])
 */
class EnsureTokenable
{
    private const TYPES = [
        'agency' => Agency::class,
        'driver' => Driver::class,
    ];

    public function handle(Request $request, Closure $next, string $type): Response
    {
        $user = $request->user();
        $class = self::TYPES[$type] ?? null;

        abort_unless($class && $user instanceof $class, 403, 'Bu token bu işlem için yetkili değil.');
        abort_unless($user->is_active, 403, 'Hesap pasif durumda.');

        return $next($request);
    }
}
