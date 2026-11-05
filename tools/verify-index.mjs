import { setTimeout as sleep } from 'node:timers/promises';
import { fileURLToPath } from 'node:url';
import { validatePackages } from './build.mjs';

async function waitForIndex(indexUrl, names, version, retrySeconds) {
  const deadline = Date.now() + retrySeconds * 1000;
  while (true) {
    try {
      const response = await fetch(indexUrl, {
        cache: 'no-store',
        signal: AbortSignal.timeout(
          Math.min(15_000, Math.max(1, deadline - Date.now()))
        ),
      });
      if (response.status !== 200) {
        await response.body?.cancel();
        throw new Error(`HTTP ${response.status}`);
      }
      const index = await response.json();
      const entries = names.map((name) => [
        name,
        index?.packages?.[name]?.[version],
      ]);
      const missing = entries
        .filter(
          ([, entry]) =>
            entry?.version !== version ||
            typeof entry.dist?.url !== 'string' ||
            !entry.dist.url
        )
        .map(([name]) => `${name}@${version}`);
      if (missing.length)
        throw new Error(
          `Missing package versions or dist URLs: ${missing.join(', ')}`
        );
      return entries;
    } catch (error) {
      const remaining = deadline - Date.now();
      if (remaining > 0) {
        await sleep(Math.min(5000, remaining));
        if (Date.now() < deadline) continue;
      }
      throw new Error(`Could not verify ${indexUrl}: ${error.message}`, {
        cause: error,
      });
    }
  }
}

try {
  const [indexUrl, version, retry = '300', extra] = process.argv.slice(2);
  const retrySeconds = Number(retry);
  if (
    !indexUrl ||
    !version ||
    extra !== undefined ||
    !Number.isInteger(retrySeconds) ||
    retrySeconds < 1 ||
    retrySeconds > 300
  )
    throw new Error(
      'Usage: node tools/verify-index.mjs <packages.json URL> <version> [retry-seconds (1–300)]'
    );
  const root = fileURLToPath(new URL('..', import.meta.url));
  const names = [...(await validatePackages(root)).keys()];
  console.log(
    `Checking ${indexUrl} for ${names.length} packages at ${version}.`
  );
  const entries = await waitForIndex(indexUrl, names, version, retrySeconds);
  await Promise.all(
    entries.map(async ([name, { dist }]) => {
      try {
        const response = await fetch(dist.url, {
          signal: AbortSignal.timeout(15_000),
        });
        await response.body?.cancel();
        if (response.status !== 200) throw new Error(`HTTP ${response.status}`);
      } catch (error) {
        throw new Error(
          `${name}@${version} dist URL ${dist.url}: ${error.message}`,
          {
            cause: error,
          }
        );
      }
    })
  );
  console.log(
    `Verified ${entries.length} downloadable packages at ${version}.`
  );
} catch (error) {
  console.error(error.message);
  process.exitCode = 1;
}
