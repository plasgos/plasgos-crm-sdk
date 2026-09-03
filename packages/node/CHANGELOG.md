# Changelog

All notable changes to this package are documented here.
This project adheres to [Semantic Versioning](https://semver.org/).

## [1.0.1] - 2026-09-03

### Changed
- Package renamed to the unscoped name `plasgos-crm-sdk`
  (`npm install plasgos-crm-sdk`).
- Added `publishConfig` (`access: public`) and a tag-triggered release workflow.
- Hardened dual-format packaging: per-condition `exports` for ESM (`import` →
  `dist/index.js`) and CommonJS (`require` → `dist/index.cjs`), with matching
  `.d.ts` / `.d.cts` type declarations for `node16`/`nodenext`/`bundler`
  resolution; added `sideEffects: false` and a `smoke` script that installs the
  packed tarball and verifies both `require()` and `import` (also run in CI).

## [1.0.0] - 2026-09-03

### Added
- Initial release: signed API client, credential-management client, webhook
  verification, typed errors, normalised response envelope.
