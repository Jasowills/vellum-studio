<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );

        if (app()->isProduction() && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }

    /**
     * Generation has a real provider cost, so it is deliberately tighter than
     * ordinary browsing. Estimates are cheap and can be more frequent.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('generations', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(6)->by('generations:'.$key);
        });

        RateLimiter::for('estimates', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(90)->by('estimates:'.$key);
        });

        RateLimiter::for('images', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(180)->by('images:'.$key);
        });

        RateLimiter::for('webhooks', function (Request $request) {
            return Limit::perMinute(240)->by('webhooks:'.$request->ip());
        });

        RateLimiter::for('admin-report', function (Request $request) {
            /** @var User|null $user */
            $user = $request->user();

            return Limit::perMinute(20)->by('admin-report:'.($user?->getAuthIdentifier() ?? $request->ip()));
        });
    }
}
