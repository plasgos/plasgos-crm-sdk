# 13. Webchat — pelanggan yang login di website

Widget chat website Plasgos bisa mengenali pelanggan yang sudah login di website Anda. Pelanggan itu
langsung **terverifikasi**. Riwayat chatnya tidak tercampur dengan orang lain di perangkat yang sama, dan
kontak CRM-nya mendapat email/nama/telepon dari sistem Anda. Caranya, server website membuat **token
identitas** lalu memberikannya ke widget.

Yang dibutuhkan:

- **Widget ID** (`wgt_…`) dan **secret identitas** (`wcs_…`) dari dashboard CRM → Webchat → widget Anda.
  Secret hanya tampil sekali saat widget dibuat atau dirotasi. Simpan di konfigurasi server (variabel
  lingkungan / brankas rahasia).
- SDK ini. Tidak perlu kunci API: token identitas tidak memanggil API apa pun.

> Secret identitas **tidak boleh** sampai ke peramban (jangan ditulis di JavaScript halaman, jangan
> dikirim ke aplikasi mobile). Yang dikirim ke halaman hanya tokennya.

## Membuat token

```ts
import { Webchat } from "plasgos-crm-sdk";

const identityToken = Webchat.signIdentity(process.env.PLASGOS_WEBCHAT_IDENTITY_SECRET!, {
  sub: String(user.id),   // id pelanggan di sistem Anda — stabil untuk orang yang sama
  email: user.email,      // opsional
  name: user.name,        // opsional
  phone: user.phone,      // opsional
});
```

```php
use Plasgos\CrmSdk\Webchat\Webchat;

$identityToken = Webchat::signIdentity(getenv('PLASGOS_WEBCHAT_IDENTITY_SECRET'), [
    'sub' => (string) $user->id,
    'email' => $user->email,
    'name' => $user->name,
]);
```

```python
import os
from plasgos_crm import Webchat

identity_token = Webchat.sign_identity(
    os.environ["PLASGOS_WEBCHAT_IDENTITY_SECRET"],
    {"sub": str(user.id), "email": user.email, "name": user.name},
)
```

| Isian | Wajib | Keterangan |
|---|---|---|
| `sub` | ya | id pelanggan di sistem Anda (teks; angka diubah ke teks), maks. 255 karakter. Jangan pakai email sebagai `sub` bila pelanggan bisa mengganti emailnya |
| `email`, `name`, `phone` | tidak | atribut terverifikasi. Mengisi field kontak CRM yang masih kosong (nilai yang sudah ada tidak ditimpa). `null`/kosong diabaikan |

Opsi (Node `{ widgetId, ttl }`, PHP argumen bernama `widgetId:`, `ttl:`, Python `widget_id=`, `ttl=`):

| Opsi | Bawaan | Keterangan |
|---|---|---|
| `widgetId` / `widget_id` | — | token hanya berlaku untuk widget itu. Berguna bila satu server melayani beberapa widget |
| `ttl` | `3600` | umur token dalam detik, 1–86400 |

Token **sekali pakai per pengunjung**: buat token baru setiap halaman dirender atau setiap login, jangan
disimpan lalu dipakai ulang untuk pelanggan lain.

## Memberikan token ke widget

Halaman yang dirender server (PHP, Laravel, Django, Rails, Express + template):

```html
<script>
  Plasgos("boot", { widgetId: "wgt_…", identityToken: <?= json_encode($identityToken) ?> });
</script>
```

Aplikasi satu halaman (React, Vue, dsb.) — ambil token dari API Anda setelah login:

```js
const { webchatToken } = await fetch("/api/me").then((r) => r.json());
Plasgos("identify", { token: webchatToken });
```

Wrapper framework: isi prop `identityToken` (`<PlasgosWebchat widgetId="wgt_…" identityToken={token} />`).

**Saat logout** panggil `Plasgos("shutdown")` (wrapper: kosongkan `identityToken`), terutama di
perangkat bersama, agar pelanggan berikutnya tidak melihat chat sebelumnya.

## Bila token ditolak

Widget memancarkan event `error` `{ code: "identity_invalid", reason }`. Pengunjung tetap bisa chat
sebagai anonim (kecuali widget diatur "wajib login").

| `reason` | Penyebab umum |
|---|---|
| `signature` | secret salah, atau secret lama yang sudah lewat 24 jam setelah dirotasi |
| `expired`, `not_yet_valid`, `issued_in_future` | jam server website tidak tepat (sinkronkan NTP) atau token disimpan terlalu lama |
| `lifetime_too_long` | `ttl` lebih dari 24 jam (SDK sudah mencegahnya) |
| `replayed` | token yang sama dipakai pengunjung lain — buat token baru per pengunjung |
| `wrong_widget` | `widgetId` di token bukan widget halaman ini |
| `verification_off` | verifikasi identitas dimatikan di pengaturan widget |

**Rotasi secret:** setelah dirotasi di dashboard, secret lama masih diterima 24 jam. Ganti konfigurasi
server Anda dalam rentang itu.

## Pengujian

Vektor uji bersama ada di [`../vectors-v3.json`](../vectors-v3.json) (`webchat_identity[]`). Dengan
`now` dan `jti` yang sama, ketiga SDK menghasilkan token yang identik:

```ts
Webchat.signIdentity("wcs_test_secret", { sub: "42" }, { now: 1760500000, jti: "4b1f3c2e-8a8d-4c35-9f3e-2b6f1e0d9a11" });
```

Spesifikasi teknis: [SDK_SPEC §8](../SDK_SPEC.md#8-webchat-identity-token).
