import json
from pathlib import Path


def replace(path: str, old: str, new: str) -> None:
    file = Path(path)
    source = file.read_text()
    if old not in source:
        raise RuntimeError(f"Expected pattern missing in {path}: {old[:160]!r}")
    file.write_text(source.replace(old, new))


# 1. Declare only the two proven direct dependencies required by the existing
# lint and CSS configuration. Preserve all other package declarations.
package_path = Path("package.json")
package = json.loads(package_path.read_text())
if package["dependencies"].get("tw-animate-css") not in (None, "1.3.4"):
    raise RuntimeError("Unexpected tw-animate-css declaration")
if package["devDependencies"].get("eslint-config-prettier") not in (None, "10.1.1"):
    raise RuntimeError("Unexpected eslint-config-prettier declaration")
package["dependencies"]["tw-animate-css"] = "1.3.4"
package["devDependencies"]["eslint-config-prettier"] = "10.1.1"
package_path.write_text(json.dumps(package, ensure_ascii=False, indent=2) + "\n")

# 2. Strengthen the session-expiry assertion: require the event boundary and
# each private query cache clear independently, rather than depending on source
# statement order.
replace(
    "scripts/audit-phase6-security.mjs",
    '''requirePattern(
  "src/routes/__root.tsx",
  /rosta:session-expired[\\s\\S]*removeQueries/,
  "expired protected sessions must clear private query caches",
);
''',
    '''requirePattern(
  "src/routes/__root.tsx",
  /rosta:session-expired/,
  "the protected-session expiry event boundary is required",
);
for (const privateQueryKey of ["auth", "profile", "orders", "cart"]) {
  requirePattern(
    "src/routes/__root.tsx",
    new RegExp(`removeQueries\\\\(\\\\{ queryKey: queryKeys\\\\.${privateQueryKey}\\\\.all \\\\}\\\\)`),
    `expired protected sessions must clear the ${privateQueryKey} query cache`,
  );
}
''',
)

# 3. Move ownership of the Composer lock existence gate to the backend CI and
# readiness command. R1 remains frontend-only; R2 must create the actual lock.
phase17 = "scripts/audit-phase17-release-baseline.mjs"
replace(
    phase17,
    '''  composerLock: "backend/composer.lock",
''',
    '''  backendCi: ".github/workflows/backend-ci.yml",
  backendReadiness: "backend/app/Console/Commands/BackendReadiness.php",
''',
)
replace(
    phase17,
    '''gate(
  "deterministic_backend_dependencies",
  await exists(paths.composerLock),
  "backend/composer.lock must be generated and committed before staging deployment",
);
''',
    '''gate(
  "backend_dependency_lock_is_permanently_enforced",
  files.backendCi?.includes("test -s composer.lock") &&
    files.backendReadiness?.includes("'composer_lock'") &&
    files.backendReadiness?.includes("is_file(base_path('composer.lock'))"),
  "Backend CI and runtime readiness must fail closed until R2 commits backend/composer.lock.",
);
''',
)

# 4. Keep bundle chunk detection aligned with the maintained Lenis package.
replace(
    "vite.config.ts",
    'id.includes("/@studio-freight/lenis/")',
    'id.includes("/lenis/")',
)
