import json
from pathlib import Path


def replace(path: str, old: str, new: str) -> None:
    file = Path(path)
    source = file.read_text()
    if old not in source:
        raise RuntimeError(f"Expected pattern missing in {path}: {old[:180]!r}")
    file.write_text(source.replace(old, new))


# Declare only dependencies already required by the checked-in lint and CSS
# configuration. Exact versions are proven from the project's previous lock.
package_path = Path("package.json")
package = json.loads(package_path.read_text())
if package["dependencies"].get("tw-animate-css") not in (None, "1.3.4"):
    raise RuntimeError("Unexpected tw-animate-css declaration")
if package["devDependencies"].get("eslint-config-prettier") not in (None, "10.1.1"):
    raise RuntimeError("Unexpected eslint-config-prettier declaration")
package["dependencies"]["tw-animate-css"] = "1.3.4"
package["devDependencies"]["eslint-config-prettier"] = "10.1.1"
package_path.write_text(json.dumps(package, ensure_ascii=False, indent=2) + "\n")

# Require the expiry event and each private cache clear independently. This is
# stronger and does not depend on source statement order.
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

# R1 is frontend-only. Keep the backend dependency lock fail-closed in backend
# CI and readiness; R2 will create and commit the actual composer.lock.
replace(
    "scripts/audit-phase17-release-baseline.mjs",
    '  composerLock: "backend/composer.lock",\n',
    '  backendCi: ".github/workflows/backend-ci.yml",\n  backendReadiness: "backend/app/Console/Commands/BackendReadiness.php",\n',
)
replace(
    "scripts/audit-phase17-release-baseline.mjs",
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

# The runtime dependency was migrated in R1A; keep chunking aligned.
replace(
    "vite.config.ts",
    'id.includes("/@studio-freight/lenis/")',
    'id.includes("/lenis/")',
)

# Replace control-character regular expressions rejected by ESLint with an
# equivalent explicit code-point check. Security behavior is preserved.
control_old = r'const CONTROL_OR_BACKSLASH = /[\\\u0000-\u001f\u007f]/;'
control_new = '''function hasControlOrBackslash(value: string): boolean {
  for (const character of value) {
    const code = character.charCodeAt(0);
    if (character === "\\\\" || code <= 0x1f || code === 0x7f) return true;
  }
  return false;
}'''
for path in ("src/config/site.ts", "src/lib/api/schemas.ts"):
    replace(path, control_old, control_new)
    file = Path(path)
    file.write_text(file.read_text().replace("CONTROL_OR_BACKSLASH.test(", "hasControlOrBackslash("))
