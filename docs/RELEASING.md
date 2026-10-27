# Releasing

Step-by-step runbook in Indonesian (one-time registry setup, per-release commands, troubleshooting):
**[DEPLOY.md](DEPLOY.md)**.

Each package versions and publishes independently. Keep the three at the same
`MAJOR.MINOR` when they track the same API contract; patch releases can diverge.

Bump the version wherever it appears:

| Package | Files to bump |
|---|---|
| Node | `packages/node/package.json` (`version`), `package-lock.json` (root `version` and `packages[""]`) and the `SDK_VERSION` const in `src/version.ts` |
| PHP | `packages/php/src/Http/Transport.php` (`VERSION`) and the User-Agent assertion in `tests/ClientTest.php` — Packagist reads the git tag, not `composer.json` |
| Python | `packages/python/pyproject.toml` (`version`), `__version__` in `src/plasgos_crm/_version.py` and the User-Agent assertion in `tests/test_v3_client.py` |

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
`package.json`, then `npm publish --access public` through npm Trusted Publishing (no token; provenance
is attached automatically). It also checks the tag against `SDK_VERSION`
and the generated types against `openapi-v3.json`.

## PHP → Packagist

Packagist needs `composer.json` at the repository root, so the monorepo is not registered directly.
CI ([`.github/workflows/release-php.yml`](../.github/workflows/release-php.yml)) fires on `php-v*`
tags:

1. **verify** (PHP 8.1 and 8.4): `composer validate --strict`, tests, PHPStan (`composer analyse`),
   types in sync with `openapi-v3.json`, and the tag equals `Transport::VERSION`.
2. **mirror**: `git subtree split --prefix=packages/php` (full history) is pushed to the read-only
   mirror's `main` and tagged `v1.2.0` there; Packagist publishes `1.2.0` from that tag.
   `.gitattributes` keeps tests and dev files out of the dist archive.

One-time setup:

| What | Where |
|---|---|
| Empty mirror repository, e.g. `plasgos/crm-sdk-php` | GitHub |
| Deploy key with **write** access on the mirror; private half in secret `PHP_MIRROR_DEPLOY_KEY` | mirror → Settings → Deploy keys; this repo → Secrets |
| Variable `PHP_MIRROR_REPO` = `plasgos/crm-sdk-php` | this repo → Variables |
| Register the mirror as `plasgos/crm-sdk` and enable the GitHub hook | https://packagist.org |
| Optional: `PACKAGIST_USERNAME` + `PACKAGIST_TOKEN` to trigger the update immediately | this repo → Secrets |

```bash
# bump packages/php/src/Http/Transport.php VERSION + CHANGELOG, commit, then:
git tag php-v1.2.0 && git push origin php-v1.2.0
```

Never commit to the mirror by hand: every release rewrites its `main` from the split.

## Python → PyPI

CI ([`.github/workflows/release-python.yml`](../.github/workflows/release-python.yml)) fires on
`python-v*` tags:

1. **build**: tests, ruff, mypy, types in sync, tag equals both `pyproject.toml` and
   `plasgos_crm.__version__`, `python -m build`, `twine check --strict`, and a smoke install of the
   wheel in a fresh venv.
2. **publish** (environment `pypi`): uploads the built files with Trusted Publishing (OIDC), so no
   PyPI token is stored in GitHub.

One-time setup: on PyPI, add a trusted publisher for `plasgos-crm-sdk` (owner `plasgos`, repository
`plasgos-crm-sdk`, workflow `release-python.yml`, environment `pypi`); in GitHub, create the
environment `pypi` (add required reviewers to gate releases). For the very first release, use a
"pending publisher" on PyPI.

```bash
# bump pyproject.toml version + src/plasgos_crm/_version.py + CHANGELOG, commit, then:
git tag python-v1.2.0 && git push origin python-v1.2.0
```

Local dry run: `cd packages/python && python -m build && twine check --strict dist/*`.

## Pre-release checklist

- [ ] `openapi/openapi-v3.json` (copied from api-crm) and `docs/SDK_SPEC.md` reflect the change; `node scripts/generate-types.mjs` run.
- [ ] All three test suites green in CI.
- [ ] `docs/vectors.json` / `docs/vectors-v3.json` unchanged (or intentionally rotated + all suites updated).
- [ ] READMEs updated for any new method.
- [ ] CHANGELOG entry per package.
