# Releasing

Each package versions and publishes independently. Keep the three at the same
`MAJOR.MINOR` when they track the same API contract; patch releases can diverge.

Bump the version wherever it appears:

| Package | Files to bump |
|---|---|
| Node | `packages/node/package.json` (`version`) and the `SDK_VERSION` const in `src/version.ts` |
| PHP | `packages/php/src/Http/Transport.php` (`VERSION`) — Packagist reads the git tag, not `composer.json` |
| Python | `packages/python/pyproject.toml` (`version`) and `__version__` in `src/plasgos_crm/_version.py` |

## Tags

Use per-package prefixed tags so one repo can feed three registries:

```
node-v1.2.0
php-v1.2.0
python-v1.2.0
```

## Node → npm

Full walkthrough (account setup, name check, dry-run, CI, trusted publishing,
troubleshooting): **[PUBLISHING_NPM.md](PUBLISHING_NPM.md)**.

Package name is **unscoped** (`plasgos-crm-sdk`) so consumers run
`npm install plasgos-crm-sdk`.

```bash
cd packages/node
npm ci && npm test && npm run build
npm publish --dry-run              # inspect the tarball first
npm version 1.2.0 --no-git-tag-version && git commit -am "node: v1.2.0"
git tag node-v1.2.0 && git push && git push origin node-v1.2.0
```

CI: [`.github/workflows/release-node.yml`](../.github/workflows/release-node.yml)
fires on `node-v*` tags — typecheck + test + build, verifies the tag matches
`package.json`, then `npm publish --provenance --access public` (needs the
`NPM_TOKEN` repo secret, or npm Trusted Publishing).

## PHP → Packagist

Packagist builds from the tag; there is no upload step.

1. Register the repo once at https://packagist.org (`plasgos/crm-sdk`), enable
   the GitHub service hook.
2. `git tag php-v1.2.0 && git push origin php-v1.2.0`.
3. Packagist picks it up; the version string comes from the tag
   (strip the `php-` prefix in the Packagist package settings, or publish from a
   `packages/php` subtree split — see below).

Because this is a monorepo, Packagist must point at `packages/php`. Either:
- use a **subtree split** to a dedicated read-only repo
  (`plasgos/crm-sdk-php`) that Packagist watches, updated by CI on each
  `php-v*` tag, or
- configure the Packagist package with the monorepo URL and a
  `"path"`-aware setup (subtree split is the reliable option).

## Python → PyPI

```bash
cd packages/python
python -m build
python -m twine upload dist/*        # needs PYPI_API_TOKEN
```

CI equivalent: trigger on `python-v*` tags, `python -m build`, then
`pypa/gh-action-pypi-publish` with trusted publishing (OIDC) — no token needed.

## Pre-release checklist

- [ ] `openapi/openapi.yaml` and `docs/SDK_SPEC.md` reflect the change.
- [ ] All three test suites green in CI.
- [ ] `docs/vectors.json` unchanged (or intentionally rotated + all suites updated).
- [ ] READMEs updated for any new method.
- [ ] CHANGELOG entry per package.
