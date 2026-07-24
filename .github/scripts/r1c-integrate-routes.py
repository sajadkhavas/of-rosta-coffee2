import json
from pathlib import Path


def replace(path: str, old: str, new: str) -> None:
    file = Path(path)
    source = file.read_text()
    if old not in source:
        raise RuntimeError(f"Expected pattern missing in {path}: {old[:120]!r}")
    file.write_text(source.replace(old, new))


package_path = Path("package.json")
package = json.loads(package_path.read_text())
scripts = package.setdefault("scripts", {})
if scripts.get("routes:generate") not in (None, "tsr generate"):
    raise RuntimeError("An unexpected routes:generate script already exists")
scripts["routes:generate"] = "tsr generate"
package["scripts"] = dict(sorted(scripts.items()))
package_path.write_text(json.dumps(package, ensure_ascii=False, indent=2) + "\n")

replace(
    "src/router.tsx",
    'import { routeTree } from "./routeTree.phase17";',
    'import { routeTree } from "./routeTree.gen";',
)

phase17 = "scripts/audit-phase17-release-baseline.mjs"
replace(
    phase17,
    'const activeRouteTree = files.phase17RouteTree ?? files.generatedRouteTree ?? "";',
    'const activeRouteTree = files.generatedRouteTree ?? "";',
)
replace(
    phase17,
    '''  requiredRoutes.every((route) => activeRouteTree.includes(route)) &&
    files.router?.includes('from "./routeTree.phase17"'),
  "the active TanStack route tree must register every current public and administrator route",
''',
    '''  requiredRoutes.every((route) => activeRouteTree.includes(route)) &&
    files.router?.includes('from "./routeTree.gen"') &&
    !(await exists(paths.phase17RouteTree)),
  "the generated TanStack route tree must register every current route and the temporary release tree must be absent",
''',
)

for path in (
    "scripts/audit-admin-finance.mjs",
    "scripts/audit-seller-operations.mjs",
    "scripts/audit-admin-operations.mjs",
    "scripts/audit-phase20-completion.mjs",
):
    replace(path, 'routeTree: "src/routeTree.phase17.ts"', 'routeTree: "src/routeTree.gen.ts"')

replace(
    "scripts/audit-admin-finance.mjs",
    "The administrator finance route must be present in navigation and the active temporary route tree.",
    "The administrator finance route must be present in navigation and the generated route tree.",
)
replace(
    "scripts/audit-seller-operations.mjs",
    "The seller panel must remain reachable from navigation and registered in the active route tree.",
    "The seller panel must remain reachable from navigation and registered in the generated route tree.",
)
replace(
    "scripts/audit-admin-operations.mjs",
    "Operations routes must be server-protected and registered in navigation and the active route tree.",
    "Operations routes must be server-protected and registered in navigation and the generated route tree.",
)

Path("src/routeTree.phase17.ts").unlink()
