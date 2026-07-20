import { existsSync } from "node:fs";
import { readFile, readdir, stat, writeFile } from "node:fs/promises";
import { extname, relative, resolve } from "node:path";
import { gzipSync } from "node:zlib";

const MAX_CLIENT_CHUNK_BYTES = 1_200 * 1024;
const CANDIDATE_ROOTS = [".output/public", "dist", "build/client"];
const ASSET_EXTENSIONS = new Set([".js", ".mjs", ".css"]);

async function walk(directory) {
  const entries = await readdir(directory, { withFileTypes: true });
  const files = [];

  for (const entry of entries) {
    const path = resolve(directory, entry.name);
    if (entry.isDirectory()) files.push(...(await walk(path)));
    if (entry.isFile() && ASSET_EXTENSIONS.has(extname(entry.name))) files.push(path);
  }

  return files;
}

const outputRoot = CANDIDATE_ROOTS.find((candidate) => existsSync(candidate));
if (!outputRoot) {
  throw new Error(`Client build directory not found. Checked: ${CANDIDATE_ROOTS.join(", ")}`);
}

const files = await walk(outputRoot);
const assets = await Promise.all(
  files.map(async (file) => {
    const info = await stat(file);
    const content = await readFile(file);
    return {
      file: relative(outputRoot, file),
      bytes: info.size,
      gzipBytes: gzipSync(content).byteLength,
    };
  }),
);

assets.sort((a, b) => b.bytes - a.bytes);
const javascriptAssets = assets.filter((asset) => [".js", ".mjs"].includes(extname(asset.file)));
const oversized = javascriptAssets.filter((asset) => asset.bytes > MAX_CLIENT_CHUNK_BYTES);
const totals = assets.reduce(
  (result, asset) => ({
    bytes: result.bytes + asset.bytes,
    gzipBytes: result.gzipBytes + asset.gzipBytes,
  }),
  { bytes: 0, gzipBytes: 0 },
);

const report = {
  generatedAt: new Date().toISOString(),
  outputRoot,
  maxClientChunkBytes: MAX_CLIENT_CHUNK_BYTES,
  totals,
  assets,
  oversized,
};

await writeFile("bundle-report.json", `${JSON.stringify(report, null, 2)}\n`);
console.table(
  assets.slice(0, 15).map((asset) => ({
    file: asset.file,
    rawKiB: (asset.bytes / 1024).toFixed(1),
    gzipKiB: (asset.gzipBytes / 1024).toFixed(1),
  })),
);
console.log(
  `Client assets: ${(totals.bytes / 1024).toFixed(1)} KiB raw / ${(totals.gzipBytes / 1024).toFixed(1)} KiB gzip`,
);

if (oversized.length > 0) {
  const names = oversized
    .map((asset) => `${asset.file} (${(asset.bytes / 1024).toFixed(1)} KiB)`)
    .join(", ");
  throw new Error(`Client chunk budget exceeded: ${names}`);
}
