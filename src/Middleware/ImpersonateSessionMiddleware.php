<?php
namespace Abdullyahuza\FilamentImpersonate\Middleware;

use Closure;
use Filament\Facades\Filament;
use Filament\Http\Middleware\AuthenticateSession as BaseAuthenticateSession;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Spatie\Permission\Traits\HasRoles;

class ImpersonateSessionMiddleware extends BaseAuthenticateSession
{

    protected $auth;
    protected static $redirectToCallback;

    public function __construct(AuthFactory $auth)
    {
        $this->auth = $auth;
    }

    public function handle($request, Closure $next)
    {
        if (! $request->hasSession() || ! $request->user() || ! $request->user()->getAuthPassword()) {
            return $next($request);
        }

        if ($this->guard()->viaRemember()) {
            $passwordHash = explode('|', $request->cookies->get($this->guard()->getRecallerName()))[2] ?? null;

            if (! $passwordHash || ! hash_equals($request->user()->getAuthPassword(), $passwordHash)) {
                if (! $this->shouldBypassLogout($request)) {
                    $this->logout($request);
                }
            }
        }

        if (! $request->session()->has('password_hash_'.$this->auth->getDefaultDriver())) {
            $this->storePasswordHashInSession($request);
        }

        if (! hash_equals($request->session()->get('password_hash_'.$this->auth->getDefaultDriver()), $request->user()->getAuthPassword())) {
            if (! $this->shouldBypassLogout($request)) {
                $this->logout($request);
            }
        }

        return tap($next($request), function () use ($request) {
            if (! is_null($this->guard()->user())) {
                $this->storePasswordHashInSession($request);
            }
        });
    }

    protected function storePasswordHashInSession($request)
    {
        $user = $request->user();

        if (! $user) {
            return;
        }

        $request->session()->put([
            'password_hash_'.$this->auth->getDefaultDriver() => $user->getAuthPassword(),
        ]);

        if (!session()->has(config('filament-impersonate.session_key', 'filament_impersonator_id'))) {
            session()->put(config('filament-impersonate.session_key', 'filament_impersonator_id'), $user->id);

            // Check if user model uses HasRoles before storing roles and permissions
            /*if (in_array(HasRoles::class, class_uses_recursive($user))) {
                session([
                    'filament_impersonate_original_roles' => $user->roles->pluck('name')->toArray(),
                    'filament_impersonate_original_permissions' => $user->permissions->pluck('id')->toArray(),
                ]);
            }*/
        }
    }

    protected function logout($request)
    {
        $this->guard()->logoutCurrentDevice();
        $request->session()->flush();

        throw new AuthenticationException(
            'Unauthenticated.', [$this->auth->getDefaultDriver()], $this->redirectTo($request)
        );
    }

    protected function guard()
    {
        return $this->auth;
    }

    protected function redirectTo($request): ?string
    {
        return Filament::getLoginUrl();
    }

    public static function redirectUsing(callable $redirectToCallback)
    {
        static::$redirectToCallback = $redirectToCallback;
    }

    /**
     * Skip logout if impersonating or during tenant switch.
     */
    protected function shouldBypassLogout(Request $request): bool
    {
        return $request->session()->has(config('filament-impersonate.session_key', 'filament_impersonator_id')) || $request->session()->has('tenant_id');
    }
}
