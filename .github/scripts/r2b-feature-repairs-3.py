from pathlib import Path


def replace(path: str, old: str, new: str) -> None:
    file = Path(path)
    source = file.read_text()
    if old not in source:
        raise RuntimeError(f"Expected pattern missing in {path}: {old[:180]!r}")
    file.write_text(source.replace(old, new))


# Content detail responses are stable action/read contracts and must not inherit
# Laravel's implicit 201 from a recently-created Eloquent model.
replace(
    "backend/app/Http/Resources/ContentEntrySummaryResource.php",
    "use Illuminate\\Http\\Resources\\Json\\JsonResource;\n",
    "",
)
replace(
    "backend/app/Http/Resources/ContentEntrySummaryResource.php",
    "class ContentEntrySummaryResource extends JsonResource",
    "class ContentEntrySummaryResource extends OkJsonResource",
)

# A complete valid fulfillment flow includes bounded invalid-transition and
# validation attempts before five state transitions. Keep throttling strict but
# allow the full operational sequence within one minute.
replace(
    "backend/app/Providers/AppServiceProvider.php",
    "Limit::perMinute(6)->by('fulfillment:order:'.hash('sha256', $orderId)),",
    "Limit::perMinute(10)->by('fulfillment:order:'.hash('sha256', $orderId)),",
)

# Sanctum AuthenticateSession validates password_hash_web on every stateful
# request. Switching test identities must replace that exact key for the new
# user instead of flushing the session cookie and losing authentication.
path = "backend/tests/Support/AuthenticatesRecordedSession.php"
replace(
    path,
    "use App\\Services\\Identity\\AuthSessionService;\n",
    "use App\\Services\\Identity\\AuthSessionService;\nuse Illuminate\\Support\\Facades\\Auth;\n",
)
replace(
    path,
    '''        $this->flushSession();
        $this->actingAs($user, 'web')->withSession([
            AuthSessionService::SESSION_KEY => $session->id,
        ]);
''',
    '''        $this->actingAs($user, 'web');
        $guard = Auth::guard('web');
        $passwordHash = method_exists($guard, 'hashPasswordForCookie')
            ? $guard->hashPasswordForCookie($user->getAuthPassword())
            : $user->getAuthPassword();

        $this->withSession([
            AuthSessionService::SESSION_KEY => $session->id,
            'password_hash_web' => $passwordHash,
        ]);
''',
)
