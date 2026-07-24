from pathlib import Path

path = Path("backend/tests/Support/AuthenticatesRecordedSession.php")
source = path.read_text()
old = '''        $this->flushSession();
        $guard = Auth::guard('web');
        $guard->login($user);
        $passwordHash = method_exists($guard, 'hashPasswordForCookie')
            ? $guard->hashPasswordForCookie($user->getAuthPassword())
            : $user->getAuthPassword();

        $this->withSession([
            AuthSessionService::SESSION_KEY => $session->id,
            'password_hash_web' => $passwordHash,
        ]);
'''
new = '''        $this->actingAs($user, 'web');
        Auth::guard('sanctum')->forgetUser();
        $guard = Auth::guard('web');
        $passwordHash = method_exists($guard, 'hashPasswordForCookie')
            ? $guard->hashPasswordForCookie($user->getAuthPassword())
            : $user->getAuthPassword();

        $this->withSession([
            $guard->getName() => $user->getAuthIdentifier(),
            AuthSessionService::SESSION_KEY => $session->id,
            'password_hash_web' => $passwordHash,
        ]);
'''
if old not in source:
    raise RuntimeError("Expected final session-login block is missing")
path.write_text(source.replace(old, new))
