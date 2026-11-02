<?php

namespace App\Providers;

use App\Models\Zone;
use App\Services\Geo\ZoneResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // İstek başına bölgeler bir kez yüklenir
        $this->app->scoped(ZoneResolver::class);
    }

    public function boot(): void
    {
        // N+1 sorgularını geliştirme ortamında logla (hata fırlatmadan)
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::handleLazyLoadingViolationUsing(
            fn (Model $model, string $relation) => logger()->warning('Lazy loading: '.$model::class.'::'.$relation),
        );

        // Bölge değişince önbelleğe alınmış bölge listesini temizle
        Zone::saved(fn () => app(ZoneResolver::class)->flush());
        Zone::deleted(fn () => app(ZoneResolver::class)->flush());

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user('sanctum')?->getMorphClass().$request->user('sanctum')?->getKey() ?: $request->ip()));

        // Rezervasyon oluşturma/sorgulama: kod tahmini ve spam'e karşı daha sıkı
        RateLimiter::for('bookings', fn (Request $request) => $request->user('sanctum')
            ? Limit::perMinute(120)->by('token:'.$request->user('sanctum')->getKey())
            : Limit::perMinute(10)->by($request->ip()));
    }
}
