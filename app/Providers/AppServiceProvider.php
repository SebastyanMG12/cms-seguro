<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Mail\FakeMailSource;
use App\Services\Mail\MailSource;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use App\Services\Mail\ImapMailSource;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MailSource::class, function () {
            return config('imap.accounts.default.username')
                ? new ImapMailSource()
                : new FakeMailSource();
        });
    }

    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by($email . '|' . $request->ip()),
                Limit::perMinute(20)->by('ip|' . $request->ip()),
            ];
        });

        Gate::define('view-inbox', fn(User $user) => $user->hasRole('admin', 'editor'));
        Gate::define('manage-users', fn(User $user) => $user->isAdmin());
        Gate::define('view-audit', fn(User $user) => $user->isAdmin());
    }
}