# Publishing `plasgos-crm-sdk` to npm

Goal: consumers run `npm install plasgos-crm-sdk` (unscoped, saved to
`dependencies` automatically — `--save` is the default since npm 5).

The package lives in [`../packages/node`](../packages/node). All commands below
run **from that directory**.

---

## 0. One-time setup

### a. npm account + org

1. Create an account at <https://www.npmjs.com/signup> (or use an existing one).
2. Enable **2FA** (Account → Two-Factor Authentication → "Authorization and writes").
3. The package name `plasgos-crm-sdk` is **unscoped**, so you don't strictly need
   an org. If you want the `plasgos` org to own it (recommended for a team):
   - <https://www.npmjs.com/org/create> → create org `plasgos` (free for public packages).
   - After first publish, transfer/add the package to the org and add teammates.

### b. Check the name is free

```bash
npm view plasgos-crm-sdk        # should print 404 "not found" -> name is available
```

If it's taken, either pick another unscoped name or go scoped
(`@plasgos/crm-sdk`, install becomes `npm install @plasgos/crm-sdk`). To switch,
change `name` in `package.json` and the `import` paths in the README.

### c. Log in locally

```bash
npm login                       # opens browser; ties this shell to your npm user
npm whoami                      # confirm
```

---

## 1. Pre-flight (every release)

```bash
cd packages/node
npm ci                          # clean install from package-lock.json
npm run typecheck
npm test                        # 22 tests
npm run build                   # produces dist/ (ESM + CJS + d.ts)
npm publish --dry-run           # inspect the tarball WITHOUT publishing
```

`--dry-run` should list only:

```
LICENSE
README.md
dist/**            (index.{js,cjs,d.ts,d.cts} + webhooks/* + maps + chunk)
package.json
```

No `src/`, no `test/`, no configs — that's enforced by the `files` field in
`package.json`. `prepublishOnly` also re-runs `build` automatically on a real
publish, so `dist/` is always fresh.

`package.json` already sets:

```jsonc
"publishConfig": { "access": "public", "registry": "https://registry.npmjs.org" }
```

so a first-time publish won't fail with the "private by default" error.

---

## 2. Publish

### Manual

```bash
cd packages/node
npm version patch               # 1.0.0 -> 1.0.1, also creates a git commit + tag "v1.0.1"
                                # (use `minor` / `major` as appropriate)
npm publish                     # prompts for your 2FA OTP
```

Then verify:

```bash
npm view plasgos-crm-sdk        # shows the published version
npm install plasgos-crm-sdk     # in a scratch dir, confirm it resolves + types load
```

> `npm version` tags as `vX.Y.Z`. This monorepo's release workflow expects
> `node-vX.Y.Z`. Either push a matching tag manually
> (`git tag node-v1.0.1 && git push origin node-v1.0.1`) or run
> `npm version --no-git-tag-version` and tag yourself.

### Automated (recommended) — GitHub Actions

Workflow: [`.github/workflows/release-node.yml`](../.github/workflows/release-node.yml).
It triggers on tags matching `node-v*`, runs typecheck + tests + build, checks
the tag matches `package.json`, then `npm publish --provenance --access public`.

Set it up once:

1. Create an **Automation** access token:
   npm → Account → Access Tokens → Generate New Token → *Automation*
   (bypasses the interactive 2FA prompt in CI).
2. GitHub repo → Settings → Secrets and variables → Actions → New repository
   secret → name `NPM_TOKEN`, value = the token.
3. Release:
   ```bash
   cd packages/node
   npm version 1.0.1 --no-git-tag-version
   git commit -am "node: v1.0.1"
   git tag node-v1.0.1
   git push && git push origin node-v1.0.1
   ```
   The workflow publishes and attaches a provenance statement (the
   "Built and signed on GitHub Actions" badge on the npm page).

#### Even better: Trusted Publishing (no token)

npm now supports OIDC trusted publishing. On the npm package settings page add a
trusted publisher: repo `plasgos/plasgos-crm-sdk`, workflow
`.github/workflows/release-node.yml`. Then delete the `NODE_AUTH_TOKEN` env line
from the workflow — `id-token: write` is already granted.

---

## 3. What consumers get

```bash
npm install plasgos-crm-sdk
```

```ts
import { PlasgosCrmClient } from "plasgos-crm-sdk";
import { verifyWebhook } from "plasgos-crm-sdk/webhooks";
```

- ESM (`import`) and CJS (`require`) both resolved via the `exports` map.
- Types ship in the package (`dist/*.d.ts`) — no `@types/...` needed.
- Zero runtime dependencies.
- Works on Node 18+ (`engines` field warns on older).

---

## 4. Common failures

| Symptom | Fix |
|---|---|
| `402 Payment Required` on publish | first publish of an unscoped package while logged into an org context — pass `--access public` (already in `publishConfig`) |
| `403 Forbidden` / name taken | `npm view plasgos-crm-sdk` — choose another name or go scoped |
| `E402` / `EOTP` | 2FA OTP required — run `npm publish --otp=123456` or use an Automation token in CI |
| tarball missing `dist/` | run `npm run build` first; on real publish `prepublishOnly` handles it |
| `dist/` contains stale files | `rm -rf dist && npm run build` (tsup `clean: true` normally handles it) |
| CI publish: `ENEEDAUTH` | `NPM_TOKEN` secret missing/expired, or `registry-url` not set on `setup-node` |
| version already published | npm forbids republishing a version — bump with `npm version` |

## 5. Deprecate / unpublish

```bash
npm deprecate plasgos-crm-sdk@1.0.0 "use >=1.0.1"
# unpublish only within 72h and if nothing depends on it:
npm unpublish plasgos-crm-sdk@1.0.0
```
