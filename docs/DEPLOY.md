# Deploy SDK Plasgos CRM — npm · Packagist · PyPI

Panduan langkah demi langkah menerbitkan SDK ke tiga registry dari repo ini. Referensi teknis singkat
(berkas yang dinaikkan, isi workflow) ada di [RELEASING.md](./RELEASING.md); panduan npm lengkap di
[PUBLISHING_NPM.md](./PUBLISHING_NPM.md).

> Versi yang sudah terbit di npm, PyPI, dan Packagist **tidak bisa ditimpa atau dipakai ulang**. Setiap
> push tag rilis langsung memicu publish — baca bagian [Rilis versi baru](#2-rilis-versi-baru) sampai
> habis sebelum mendorong tag pertama.

---

## Ringkasan

| | Node.js | PHP | Python |
|---|---|---|---|
| Registry | [npm](https://www.npmjs.com/package/plasgos-crm-sdk) | [Packagist](https://packagist.org/packages/plasgos/crm-sdk) | [PyPI](https://pypi.org/project/plasgos-crm-sdk/) |
| Nama paket | `plasgos-crm-sdk` | `plasgos/crm-sdk` | `plasgos-crm-sdk` (modul `plasgos_crm`) |
| Pasang | `npm install plasgos-crm-sdk` | `composer require plasgos/crm-sdk` | `pip install plasgos-crm-sdk` |
| Tag rilis | `node-vX.Y.Z` | `php-vX.Y.Z` | `python-vX.Y.Z` |
| Workflow | [`release-node.yml`](../.github/workflows/release-node.yml) | [`release-php.yml`](../.github/workflows/release-php.yml) | [`release-python.yml`](../.github/workflows/release-python.yml) |
| Login ke registry | npm Trusted Publishing (OIDC, tanpa token) | deploy key ke repo mirror `plasgos/crm-sdk-php` | PyPI Trusted Publishing (OIDC, tanpa token) |

Alurnya sama untuk ketiganya: **naikkan versi → commit → push → push tag**. Workflow rilis menjalankan
seluruh test dan pemeriksaan versi dulu; publish hanya terjadi bila semuanya lulus.

```
push tag  ──►  test · lint · build · cek tag = versi di kode  ──►  publish ke registry
                         (gagal di sini = tidak ada yang terbit)
```

---

## 1. Persiapan sekali

Dikerjakan sekali per registry. Butuh akses **admin** ke organisasi GitHub `plasgos` (untuk secret,
variable, environment, dan repo mirror) serta akun di masing-masing registry.

### 1.1 npm

1. Login ke npmjs.com dengan akun pemilik paket (`plasgos`, 2FA aktif).
2. Paket **`plasgos-crm-sdk` → Settings → Trusted Publisher → GitHub Actions**, isi persis:

   | Field | Isi |
   |---|---|
   | Organization or user | `plasgos` |
   | Repository | `plasgos-crm-sdk` |
   | Workflow filename | `release-node.yml` |
   | Environment name | *(kosong)* |

3. Trusted publisher yang baru dibuat **harus dipakai publish dalam 2 hari**, atau kedaluwarsa dan
   harus dibuat ulang — dorong tag `node-v…` segera setelah langkah ini.
4. Setelah publish pertama lewat CI berhasil: **Settings → Publishing access → Require two-factor
   authentication and disallow tokens**.

Catatan:
- Tidak ada `NPM_TOKEN`. npm mencabut semua token klasik (termasuk "Automation") pada 9 Desember 2025;
  token granular untuk menulis berlaku maksimal 90 hari.
- Trusted Publishing butuh Node ≥ 22.14 dan npm CLI ≥ 11.5.1 — workflow sudah memakai Node 24 dan
  memperbarui npm sebelum publish. Provenance ("Built and signed on GitHub Actions") terpasang otomatis.

### 1.2 Packagist (PHP)

Packagist hanya membaca `composer.json` di **root** repo, sedangkan di sini paketnya ada di
`packages/php`. Workflow `release-php` memecah `packages/php` (beserta riwayatnya) ke repo mirror
`plasgos/crm-sdk-php`, dan repo mirror itulah yang terdaftar di Packagist.

1. **Repo mirror.** GitHub → New repository → owner `plasgos`, nama `crm-sdk-php`, **Public**,
   **tanpa** README / .gitignore / license (harus benar-benar kosong; push pertama dari workflow ditolak
   bila sudah ada commit).

2. **Deploy key.** Di laptop, di folder sementara (jangan di dalam repo):

   ```bash
   mkdir -p ~/kunci-sementara && cd ~/kunci-sementara
   ssh-keygen -t ed25519 -f crm-sdk-php-deploy -N "" -C "release-php"
   cat crm-sdk-php-deploy.pub   # kunci publik
   cat crm-sdk-php-deploy       # kunci privat (BEGIN … END)
   ```

   | Isi | Dipasang di |
   |---|---|
   | `crm-sdk-php-deploy.pub` | repo `plasgos/crm-sdk-php` → Settings → **Deploy keys** → Add, judul `release-php`, centang **Allow write access** |
   | `crm-sdk-php-deploy` (seluruh teks) | repo `plasgos/plasgos-crm-sdk` → Settings → Secrets and variables → Actions → **Secrets** → `PHP_MIRROR_DEPLOY_KEY` |

   Lalu di halaman yang sama, tab **Variables** → `PHP_MIRROR_REPO` = `plasgos/crm-sdk-php`.
   Hapus kunci dari laptop: `cd ~ && rm -rf ~/kunci-sementara`.

3. **Rilis PHP pertama** (lihat [2.3](#23-php--packagist)) supaya mirror berisi `composer.json` dan tag `vX.Y.Z`.

4. **Daftarkan di Packagist** (setelah langkah 3): packagist.org → **Submit** →
   `https://github.com/plasgos/crm-sdk-php` → Check → Submit. Nama `plasgos/crm-sdk` dibaca dari
   `composer.json`, versi dari tag mirror.

5. **Update otomatis** — tanpa ini rilis berikutnya tidak muncul di Packagist sampai tombol *Update*
   diklik manual. Pilih salah satu:
   - Packagist → Profile → Settings → **Connect GitHub**, izinkan akses ke organisasi `plasgos`
     (admin organisasi mungkin perlu menyetujui aplikasi Packagist).
   - Webhook manual di repo `plasgos/crm-sdk-php` → Settings → Webhooks → Add:
     Payload URL `https://packagist.org/api/github?username=<username-packagist>`, content type
     `application/json`, Secret = API Token Packagist (Profile → Show API Token), event *Just the push
     event*.

   Peringatan *"This package is not auto-updated"* di halaman paket Packagist hilang bila sudah benar.

6. *(Opsional)* Secret `PACKAGIST_USERNAME` + `PACKAGIST_TOKEN` di `plasgos/plasgos-crm-sdk` agar
   workflow meminta Packagist memperbarui seketika. Pasang **hanya setelah** paket terdaftar — sebelum
   itu step tersebut gagal dan job ikut merah.

**Jangan pernah commit langsung ke repo mirror**: setiap rilis menimpa `main` di sana dengan hasil
pemecahan dari monorepo.

### 1.3 PyPI

1. Akun di pypi.org dengan **2FA** aktif (wajib di PyPI).
2. **Pending publisher** (selama proyek belum ada di PyPI): Account → **Publishing** → *Add a new
   pending publisher* → tab GitHub, isi persis:

   | Field | Isi |
   |---|---|
   | PyPI Project Name | `plasgos-crm-sdk` |
   | Owner | `plasgos` |
   | Repository name | `plasgos-crm-sdk` |
   | Workflow name | `release-python.yml` |
   | Environment name | `pypi` |

   Pending publisher belum mengunci nama — lakukan rilis pertama tidak lama setelahnya. Setelah rilis
   pertama ia otomatis menjadi trusted publisher proyek.

3. **Environment GitHub `pypi`**: repo `plasgos/plasgos-crm-sdk` → Settings → Environments → New
   environment `pypi`:
   - **Deployment branches and tags** → Selected branches and tags → Add rule → Ref type **Tag**, pola
     `python-v*`. Wajib: PyPI mencocokkan nama workflow dan environment saja, bukan branch — tanpa aturan
     ini, workflow yang diubah di branch lain bisa ikut mengunggah. Jangan pilih *Protected branches
     only* (rilis dipicu tag, bukan branch).
   - **Required reviewers** *(disarankan bila lebih dari satu orang punya akses write)*: publish menunggu
     persetujuan. Bila hanya satu reviewer, jangan centang *Prevent self-review*. Tanpa reviewer, push tag
     = langsung terbit.

---

## 2. Rilis versi baru

### 2.1 Sebelum rilis

- [ ] `openapi/openapi-v3.json` (salinan dari api-crm) dan `docs/SDK_SPEC.md` mencerminkan perubahan;
      `node scripts/generate-types.mjs` sudah dijalankan.
- [ ] Ketiga suite test hijau di CI `main`.
- [ ] `docs/vectors.json` / `docs/vectors-v3.json` tidak berubah (atau sengaja diganti dan semua suite
      ikut diperbarui).
- [ ] README diperbarui untuk metode baru.
- [ ] Entri CHANGELOG per paket, dengan **tanggal rilis** (bukan `XX` / `Unreleased`).

**Penomoran.** Semver. Ketiga paket menyamakan `MAJOR.MINOR` selama melacak kontrak API yang sama;
`PATCH` boleh berbeda per paket. Perubahan yang merusak = major baru.

### 2.2 Node → npm

Naikkan versi di dua tempat — workflow menolak bila tag, `package.json`, dan `SDK_VERSION` berbeda:

| Berkas | Isi |
|---|---|
| `packages/node/package.json` | `"version": "X.Y.Z"` |
| `packages/node/src/version.ts` | `export const SDK_VERSION = "X.Y.Z";` |
| `packages/node/CHANGELOG.md` | entri `## [X.Y.Z] - YYYY-MM-DD` |

```bash
cd packages/node
npm ci && npm run typecheck && npm test && npm run build
npm publish --dry-run            # periksa isi tarball (versi, jumlah berkas, ukuran)
cd ../..
git commit -am "node: vX.Y.Z"
git push
git tag node-vX.Y.Z
git push origin node-vX.Y.Z
```

Workflow: typecheck, test, build, uji impor ESM & CJS, tipe sinkron dengan `openapi-v3.json`, cek
versi, lalu `npm publish --access public`.

### 2.3 PHP → Packagist

Packagist membaca versi dari **tag**, bukan dari `composer.json` (jangan tambahkan field `version` di
sana).

| Berkas | Isi |
|---|---|
| `packages/php/src/Http/Transport.php` | `public const VERSION = 'X.Y.Z';` |
| `packages/php/CHANGELOG.md` | entri `## [X.Y.Z] - YYYY-MM-DD` |

```bash
git commit -am "php: vX.Y.Z"
git push
git tag php-vX.Y.Z
git push origin php-vX.Y.Z
```

Workflow:
1. **verify** (PHP 8.1 dan 8.4): `composer validate --strict`, test, PHPStan, tipe sinkron, tag =
   `Transport::VERSION`. Gagal di sini → mirror tidak disentuh.
2. **mirror**: `git subtree split --prefix=packages/php`, push ke `main` repo mirror dan tag `vX.Y.Z`
   di sana. Packagist memperbarui lewat hook (1.2 langkah 5).

### 2.4 Python → PyPI

| Berkas | Isi |
|---|---|
| `packages/python/pyproject.toml` | `version = "X.Y.Z"` |
| `packages/python/src/plasgos_crm/_version.py` | `__version__ = "X.Y.Z"` |
| `packages/python/CHANGELOG.md` | entri `## [X.Y.Z] - YYYY-MM-DD` |

```bash
git commit -am "python: vX.Y.Z"
git push
git tag python-vX.Y.Z
git push origin python-vX.Y.Z
```

Workflow:
1. **build**: pytest, ruff, mypy, tipe sinkron, tag = `pyproject.toml` = `__version__`,
   `python -m build`, `twine check --strict`, wheel dipasang di venv bersih dan diimpor.
2. **publish** (environment `pypi`): bila Required reviewers aktif, buka run → **Review deployments** →
   centang `pypi` → **Approve and deploy**. Unggah lewat Trusted Publishing.

Uji lokal sebelum tag (opsional): `cd packages/python && python -m build && twine check --strict dist/*`.

### 2.5 Aturan push tag

- Push tag **satu per satu**: `git push origin <nama-tag>`. **Jangan** `git push --tags` — semua tag
  lokal ikut terdorong dan bisa memicu rilis yang tidak dimaksud.
- Tag harus dibuat **di commit yang versinya sudah dinaikkan** — workflow membandingkan tag dengan
  versi di kode.
- Tag boleh dibuat dari branch rilis (mis. `release/sdk-X.Y.Z`); setelah rilis, merge branch itu
  kembali ke `main`.

---

## 3. Verifikasi setelah rilis

```bash
npm view plasgos-crm-sdk version                       # X.Y.Z
composer show -a plasgos/crm-sdk | grep versions       # memuat vX.Y.Z
pip index versions plasgos-crm-sdk                     # memuat X.Y.Z
```

Uji pasang di folder/venv kosong:

```bash
mkdir /tmp/cek-node && cd /tmp/cek-node && npm init -y >/dev/null && npm install plasgos-crm-sdk@X.Y.Z
mkdir /tmp/cek-php && cd /tmp/cek-php && composer require plasgos/crm-sdk:X.Y.Z
python3 -m venv /tmp/cek-py && /tmp/cek-py/bin/pip install plasgos-crm-sdk==X.Y.Z \
  && /tmp/cek-py/bin/python -c "import plasgos_crm; print(plasgos_crm.__version__)"
```

Halaman npm menampilkan lencana provenance; halaman Packagist tanpa peringatan "not auto-updated".

---

## 4. Bila ada masalah

### Workflow gagal sebelum publish

Tidak ada yang terbit, jadi nomor versi yang sama masih boleh dipakai. Perbaiki, commit, lalu buat ulang
tag di commit yang baru:

```bash
git tag -d php-vX.Y.Z
git push origin :refs/tags/php-vX.Y.Z
# perbaiki → commit → push
git tag php-vX.Y.Z && git push origin php-vX.Y.Z
```

Untuk PHP, aman selama job **mirror** belum berjalan. Bila mirror sudah menerima tag `vX.Y.Z`, naikkan
versi.

### Versi yang sudah terbit ternyata bermasalah

Versi terbit tidak bisa ditimpa. Terbitkan perbaikan sebagai **versi patch baru**, lalu tandai versi
yang buruk:

| Registry | Cara menandai |
|---|---|
| npm | `npm deprecate plasgos-crm-sdk@X.Y.Z "Pakai X.Y.Z+1: <alasan>"` (login akun pemilik) |
| PyPI | pypi.org → proyek → Manage → Releases → versi → Options → **Yank** (pip berhenti memilihnya kecuali diminta persis) |
| Packagist | terbitkan patch baru; jangan menghapus atau memindah tag di mirror |

### Pesan galat yang umum

| Registry | Pesan | Penyebab / perbaikan |
|---|---|---|
| semua | `Tag … does not match …` | versi di kode belum dinaikkan, atau tag dibuat di commit lama — lihat "Workflow gagal sebelum publish" |
| npm | `ENEEDAUTH` / `404` saat publish | trusted publisher belum dibuat atau kedaluwarsa (2 hari), atau isiannya tidak persis `plasgos` / `plasgos-crm-sdk` / `release-node.yml` |
| npm | `You cannot publish over the previously published versions` | versi sudah ada di npm — naikkan versi |
| PHP | `PHP_MIRROR_DEPLOY_KEY / PHP_MIRROR_REPO not configured` | secret atau variable belum dipasang (1.2 langkah 2) |
| PHP | `Permission denied (publickey)` / `ERROR: The key you are authenticating with has been marked as read only` | deploy key tidak terpasang di repo mirror atau *Allow write access* tidak dicentang |
| PHP | push ke mirror ditolak `non-fast-forward` | repo mirror pernah di-commit manual — kosongkan lagi atau timpa `main` mirror dari hasil split |
| PHP | step "Ask Packagist to update now" gagal | secret Packagist dipasang sebelum paket terdaftar, atau token salah |
| PyPI | `invalid-publisher: valid token, but no corresponding publisher` | isian pending/trusted publisher tidak persis (nama workflow, environment `pypi`) |
| PyPI | `Non-user identities cannot create new projects` | pending publisher belum dibuat atau untuk nama proyek lain |
| PyPI | job publish tidak pernah mulai | environment `pypi` tidak mengizinkan tag `python-v*` |
| PyPI | `File already exists` | versi sudah pernah diunggah — PyPI tidak mengizinkan pemakaian ulang, naikkan versi |
