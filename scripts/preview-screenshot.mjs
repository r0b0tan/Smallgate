/**
 * Takes the thumbnail screenshot of one preview.
 *
 * Only ever started by App\Services\Previews\PreviewScreenshotter from a queue
 * worker -- never by a web request, never with input a customer controls.
 *
 * Input:  one JSON object on stdin (not argv, which every local user can read):
 *           { mode: "static",   root: "/abs/dir", output, viewport, scale, timeoutMs, chromium, sandbox }
 *           { mode: "upstream", url, host, address, output, viewport, scale, timeoutMs, chromium, sandbox }
 * Output: one JSON line on stdout, {"ok":true} or {"ok":false,"error":"<code>"}.
 *         Error codes are deliberately coarse and never contain a URL or a
 *         path, so the caller can log them as they are.
 *
 * What the browser can reach -- the part that matters:
 *
 *  - static:   the directory is served from disk under a fixed, non-resolvable
 *              origin by the request handler below. Nothing touches the network.
 *  - upstream: only the allow-listed host, pinned to the public address PHP
 *              resolved and checked. A rebinding DNS answer never reaches
 *              Chromium.
 *
 *  In both modes every other request is aborted. Redirects and IP literals are
 *  not seen by the request handler, so the browser additionally runs behind a
 *  proxy that does not exist (loopback included) and a resolver that knows no
 *  other name: whatever slips past the handler goes nowhere.
 *
 * With "sandbox" set, Chromium's own process sandbox is on as well, so a page
 * that exploits the renderer is still locked into namespaces and a seccomp
 * filter. In Docker that needs docker/seccomp/chromium.json. The sandbox is
 * checked after launch; without it the screenshot fails as
 * "sandbox_unavailable" instead of quietly running unprotected.
 */
import { chromium } from 'playwright-core';
import { access, readFile, realpath, stat } from 'node:fs/promises';
import path from 'node:path';

const STATIC_ORIGIN = 'https://preview.smallgate.invalid';
const DEAD_PROXY = 'http://127.0.0.1:9';
const MAX_FILE_BYTES = 25 * 1024 * 1024;

const MIME = {
    '.html': 'text/html; charset=utf-8',
    '.htm': 'text/html; charset=utf-8',
    '.css': 'text/css; charset=utf-8',
    '.js': 'text/javascript; charset=utf-8',
    '.mjs': 'text/javascript; charset=utf-8',
    '.json': 'application/json',
    '.svg': 'image/svg+xml',
    '.png': 'image/png',
    '.jpg': 'image/jpeg',
    '.jpeg': 'image/jpeg',
    '.gif': 'image/gif',
    '.webp': 'image/webp',
    '.avif': 'image/avif',
    '.ico': 'image/x-icon',
    '.woff': 'font/woff',
    '.woff2': 'font/woff2',
    '.ttf': 'font/ttf',
    '.otf': 'font/otf',
    '.mp4': 'video/mp4',
    '.webm': 'video/webm',
};

function finish(result) {
    process.stdout.write(JSON.stringify(result) + '\n');
    process.exit(result.ok ? 0 : 1);
}

async function readStdin() {
    const chunks = [];
    for await (const chunk of process.stdin) {
        chunks.push(chunk);
    }
    return JSON.parse(Buffer.concat(chunks).toString('utf8'));
}

function isInside(candidate, root) {
    return candidate === root || candidate.startsWith(root + path.sep);
}

/**
 * Serve one request of a static preview from its directory. Path traversal is
 * stopped twice: lexically before the filesystem is touched, and again after
 * realpath(), which also catches symlinks pointing out of the directory.
 */
async function serveStatic(route, root) {
    const url = new URL(route.request().url());

    if (url.origin !== STATIC_ORIGIN) {
        return route.abort('blockedbyclient');
    }

    let relative;
    try {
        relative = decodeURIComponent(url.pathname);
    } catch {
        return route.fulfill({ status: 400 });
    }

    if (relative.includes('\0') || relative.split('/').some((segment) => segment.startsWith('.') && segment !== '')) {
        return route.fulfill({ status: 404 });
    }

    try {
        let file = path.join(root, path.posix.normalize('/' + relative));
        if (!isInside(file, root)) {
            return route.fulfill({ status: 404 });
        }

        file = await realpath(file);
        let info = await stat(file);

        if (info.isDirectory()) {
            file = await realpath(path.join(file, 'index.html'));
            info = await stat(file);
        }

        if (!isInside(file, root) || !info.isFile() || info.size > MAX_FILE_BYTES) {
            return route.fulfill({ status: 404 });
        }

        return route.fulfill({
            status: 200,
            contentType: MIME[path.extname(file).toLowerCase()] ?? 'application/octet-stream',
            body: await readFile(file),
        });
    } catch {
        return route.fulfill({ status: 404 });
    }
}

function serveUpstream(route, host) {
    const url = new URL(route.request().url());

    if (url.protocol === 'data:' || url.protocol === 'blob:') {
        return route.continue();
    }

    if (url.protocol !== 'https:' || url.hostname !== host || (url.port !== '' && url.port !== '443')) {
        return route.abort('blockedbyclient');
    }

    return route.continue();
}

/**
 * "Ready" means: the load event fired, the network went quiet (bounded), every
 * web font is loaded and every image in the first screen has decoded. A page
 * that renders on the client can opt into an explicit signal: with
 * data-smallgate-wait on <html>, the screenshot waits until the page sets
 * data-smallgate-ready there as well.
 */
/**
 * Whether Chromium reports its sandbox as fully active. chrome://sandbox is an
 * internal page: it touches neither the network nor the request handler.
 */
async function isSandboxed(browser) {
    const page = await browser.newPage();
    try {
        await page.goto('chrome://sandbox');
        return (await page.locator('body').innerText()).includes('You are adequately sandboxed.');
    } catch {
        return false;
    } finally {
        await page.close().catch(() => {});
    }
}

async function waitUntilReady(page, deadline) {
    const remaining = () => Math.max(1000, deadline - Date.now());

    await page.waitForLoadState('networkidle', { timeout: Math.min(5000, remaining()) }).catch(() => {});

    const wantsSignal = await page.evaluate(() => document.documentElement.hasAttribute('data-smallgate-wait'));
    if (wantsSignal) {
        await page.waitForSelector('html[data-smallgate-ready]', { state: 'attached', timeout: remaining() });
    }

    await page.evaluate(async () => {
        await document.fonts.ready;

        const visible = [...document.images].filter((img) => {
            const box = img.getBoundingClientRect();
            return box.bottom > 0 && box.top < window.innerHeight && box.width > 0 && box.height > 0;
        });

        await Promise.all(visible.map((img) => (img.complete
            ? img.decode().catch(() => {})
            : new Promise((resolve) => {
                img.addEventListener('load', resolve, { once: true });
                img.addEventListener('error', resolve, { once: true });
            }))));

        // Two frames, so the decoded images and fonts are actually painted.
        await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
    });
}

async function main() {
    let spec;
    try {
        spec = await readStdin();
    } catch {
        return { ok: false, error: 'invalid_input' };
    }

    const deadline = Date.now() + spec.timeoutMs;
    const upstream = spec.mode === 'upstream';

    let root = null;
    if (!upstream) {
        try {
            root = await realpath(spec.root);
        } catch {
            return { ok: false, error: 'target_missing' };
        }
    }

    const resolverRules = upstream
        ? `MAP ${spec.host} ${spec.address}, MAP * ~NOTFOUND`
        : 'MAP * ~NOTFOUND';

    let browser;
    try {
        browser = await chromium.launch({
            executablePath: spec.chromium || undefined,
            chromiumSandbox: spec.sandbox === true,
            timeout: Math.max(1000, deadline - Date.now()),
            proxy: {
                server: DEAD_PROXY,
                // "<-loopback>" removes Chromium's implicit proxy bypass for
                // localhost, so the dead proxy covers loopback addresses too.
                bypass: upstream ? `<-loopback>;${spec.host}` : '<-loopback>',
            },
            args: [
                `--host-resolver-rules=${resolverRules}`,
                '--force-webrtc-ip-handling-policy=disable_non_proxied_udp',
                // Alpine's Chromium 152 writes the GPU shader cache with musl's
                // pwritev2, which its own GPU sandbox policy forbids -- the GPU
                // process crashes and takes the page with it (aports MR
                // !108402). One screenshot gains nothing from a cache anyway.
                '--disable-gpu-shader-disk-cache',
                '--disable-background-networking',
                '--disable-component-update',
                '--disable-sync',
                '--no-first-run',
                '--mute-audio',
            ],
        });
    } catch {
        // An installed browser that will not start with the sandbox on is
        // almost always the container refusing the namespaces it needs.
        const installed = await access(spec.chromium || chromium.executablePath()).then(() => true, () => false);

        return { ok: false, error: spec.sandbox === true && installed ? 'sandbox_unavailable' : 'browser_unavailable' };
    }

    if (spec.sandbox === true && !(await isSandboxed(browser))) {
        await browser.close().catch(() => {});

        return { ok: false, error: 'sandbox_unavailable' };
    }

    try {
        const context = await browser.newContext({
            viewport: spec.viewport,
            deviceScaleFactor: spec.scale,
            serviceWorkers: 'block',
            acceptDownloads: false,
            javaScriptEnabled: true,
            locale: 'de-DE',
            timezoneId: 'Europe/Berlin',
        });

        await context.route('**/*', (route) => (upstream ? serveUpstream(route, spec.host) : serveStatic(route, root)));

        const page = await context.newPage();
        const response = await page.goto(upstream ? spec.url : STATIC_ORIGIN + '/', {
            waitUntil: 'load',
            timeout: Math.max(1000, deadline - Date.now()),
        });

        if (response === null || !response.ok()) {
            return { ok: false, error: 'bad_status' };
        }

        await waitUntilReady(page, deadline);

        await page.screenshot({
            path: spec.output,
            type: 'jpeg',
            quality: 80,
            animations: 'disabled',
            timeout: Math.max(1000, deadline - Date.now()),
        });

        return { ok: true };
    } catch (error) {
        return { ok: false, error: error?.name === 'TimeoutError' ? 'timeout' : 'render_failed' };
    } finally {
        await browser.close().catch(() => {});
    }
}

main().then(finish, () => finish({ ok: false, error: 'render_failed' }));
