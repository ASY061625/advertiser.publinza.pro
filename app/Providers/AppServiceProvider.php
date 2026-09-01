<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Posts\Models\Post;
use App\Observers\PostObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Enforces the post lifecycle and writes post_status_history. Registered
        // here rather than at call sites so no code path can skip it.
        Post::observe(PostObserver::class);

        // Registered directly rather than relying on framework auto-discovery:
        // if this listener silently stopped being registered, signup would
        // succeed and no verification email would ever arrive.
        Event::listen(Registered::class, SendEmailVerificationNotification::class);

        // Models live under app/Domain/<Context>/Models, so Laravel's default
        // guess (Database\Factories\<full namespace>Factory) misses every one
        // of them. Factories are flat in database/factories, keyed by the model
        // name, so resolve them by basename. Without this a domain model's
        // factory exists but can never be found, and only a test that reaches
        // for it finds out.
        Factory::guessFactoryNamesUsing(
            static fn (string $model): string => 'Database\\Factories\\'.class_basename($model).'Factory',
        );

        // One definition of "a strong enough password", so signup and reset
        // cannot drift apart. The breach check calls api.pwnedpasswords.com, so
        // it runs only where outbound HTTP is expected — a test suite that
        // makes a network request per password is slow and flaky, and the rule
        // being tested is the length and composition, not Troy Hunt's uptime.
        Password::defaults(static function (): Password {
            $rule = Password::min(10)->mixedCase()->numbers();

            return app()->runningUnitTests() ? $rule : $rule->uncompromised();
        });

        // Fail loudly in development rather than silently N+1 in production.
        Model::preventLazyLoading(! app()->isProduction());
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());
        Model::unguard(false);

        if (app()->isProduction() || config('publinza.force_https')) {
            URL::forceScheme('https');
        }
    }
}
