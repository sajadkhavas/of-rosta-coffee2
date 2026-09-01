import { createHash } from "node:crypto";
import { execFileSync } from "node:child_process";
import { readdir, readFile, stat, writeFile } from "node:fs/promises";
import path from "node:path";

const separatorIndex = process.argv.indexOf("--");
const rawCliArgs =
  separatorIndex >= 0 ? process.argv.slice(separatorIndex + 1) : process.argv.slice(2);
const cliArgs = rawCliArgs.length > 2 ? rawCliArgs.slice(-2) : rawCliArgs;
const artifactDir = path.resolve(cliArgs[0] ?? ".output");
const outputPath = path.resolve(cliArgs[1] ?? "release-manifest.json");
const forbiddenNames = [
  /^\.env(?:\.|$)/i,
  /\.map$/i,
  /(?:^|\/)(?:id_rsa|id_ed25519)(?:\.|$)/i,
  /\.(?:pem|p12|pfx|key)$/i,
  /(?:^|\/)(?:backup|dump|database)(?:[-_.]|$)/i,
];
const secretPatterns = [
  /-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/,
  /\bAKIA[0-9A-Z]{16}\b/,
  /\bsk-(?:proj-)?[A-Za-z0-9_-]{20,}\b/,
  /(?:CLOUDFLARE_API_TOKEN|S3_SECRET_ACCESS_KEY|PAYMENT_MERCHANT_ID|KAVENEGAR_API_KEY)\s*[=:]\s*["']?[A-Za-z0-9_./+=-]{12,}/i,
  /APP_KEY\s*[=:]\s*["']?base64:[A-Za-z0-9+/=]{30,}/i,
];
const textExtensions = new Set([
  ".js",
  ".mjs",
  ".cjs",
  ".json",
  ".html",
  ".css",
  ".txt",
  ".xml",
  ".svg",
  ".wasm.txt",
]);

async function walk(directory) {
  const entries = await readdir(directory, { withFileTypes: true });
  const files = [];

  for (const entry of entries) {
    const absolute = path.join(directory, entry.name);
    if (entry.isDirectory()) {
      files.push(...(await walk(absolute)));
    } else if (entry.isFile()) {
      files.push(absolute);
    }
  }

  return files;
}

function commitSha() {
  const supplied = process.env.GITHUB_SHA?.trim();
  if (supplied) return supplied;

  try {
    return execFileSync("git", ["rev-parse", "HEAD"], {
      encoding: "utf8",
      stdio: ["ignore", "pipe", "ignore"],
    }).trim();
  } catch {
    return "unknown";
  }
}

const artifactStat = await stat(artifactDir).catch(() => null);
if (!artifactStat?.isDirectory()) {
  console.error(`Release artifact directory is missing: ${artifactDir}`);
  process.exit(1);
}

const absoluteFiles = (await walk(artifactDir)).sort();
if (absoluteFiles.length === 0) {
  console.error("Release artifact directory is empty.");
  process.exit(1);
}

const violations = [];
const files = [];
let totalBytes = 0;

for (const absolute of absoluteFiles) {
  const relative = path.relative(artifactDir, absolute).split(path.sep).join("/");
  if (forbiddenNames.some((pattern) => pattern.test(relative))) {
    violations.push(`${relative}: forbidden release filename`);
  }

  const buffer = await readFile(absolute);
  const sha256 = createHash("sha256").update(buffer).digest("hex");
  totalBytes += buffer.byteLength;
  files.push({ path: relative, bytes: buffer.byteLength, sha256 });

  const extension = path.extname(relative).toLowerCase();
  const shouldScanAsText =
    textExtensions.has(extension) ||
    relative.endsWith(".wasm.txt") ||
    buffer.byteLength <= 2_000_000;
  if (!shouldScanAsText || buffer.includes(0)) continue;

  const text = buffer.toString("utf8");
  for (const pattern of secretPatterns) {
    if (pattern.test(text)) {
      violations.push(`${relative}: secret-shaped content (${pattern.source})`);
    }
  }
}

if (violations.length > 0) {
  console.error("Release artifact verification failed:");
  for (const violation of violations) console.error(`- ${violation}`);
  process.exit(1);
}

const manifest = {
  schema: "rosta.release-manifest.v1",
  commit: commitSha(),
  generatedAt: new Date().toISOString(),
  artifactDirectory: path.relative(process.cwd(), artifactDir) || ".",
  fileCount: files.length,
  totalBytes,
  files,
};
await writeFile(outputPath, `${JSON.stringify(manifest, null, 2)}\n`, "utf8");
console.log(
  `Release manifest created: ${path.relative(process.cwd(), outputPath)} (${files.length} files, ${totalBytes} bytes).`,
);
