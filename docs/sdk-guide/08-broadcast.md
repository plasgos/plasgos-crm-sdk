# 8. Broadcast

Broadcast v3 hanya untuk **template WhatsApp resmi** (titik kirim lain → `422 channel_broadcast_unsupported`).
Cek `endpoint.capabilities.broadcast` untuk memilih titik kirim.

## Buat

| Field | Isi |
|---|---|
| `name` | wajib, maks. 120 karakter |
| `description` | opsional |
| `endpoint_id` | titik kirim WhatsApp resmi |
| `content` | template: `Content.template(id).dynamic(...).custom(...)` |
| `audience` | **tepat satu**: `contact_ids` (maks. 1000), `group_ids` (maks. 20; anggota diurai saat diterima), atau `recipients` (`[{phone_number, name?}]`, maks. 1000) |
| `scheduled_at` | ISO 8601 dengan zona waktu; kosong = sekarang |

```ts
const r = await client.broadcasts.create({
  name: "Promo Oktober",
  endpoint_id: "6281100000001",
  content: Content.template("66f1a2b3c4d5e6f708192a3b").dynamic("name", "{{full_name}}"),
  audience: { group_ids: ["grp_vip"] },
  scheduled_at: "2026-10-20T09:00:00+07:00",
}, "promo-oktober-2026");
console.log(r.data.status, r.meta.accepted_recipients, r.meta.rejected_count, r.meta.rejected_recipients);
```

```php
$r = $client->broadcasts->create([
    'name' => 'Promo Oktober',
    'endpoint_id' => '6281100000001',
    'content' => Content::template('66f1a2b3c4d5e6f708192a3b')->dynamic('name', '{{full_name}}'),
    'audience' => ['recipients' => [['phone_number' => '081234567890', 'name' => 'Budi']]],
], 'promo-oktober-2026');
```

```python
r = client.broadcasts.create({
    "name": "Promo Oktober",
    "endpoint_id": "6281100000001",
    "content": Content.template("66f1a2b3c4d5e6f708192a3b").dynamic("name", "{{full_name}}"),
    "audience": {"contact_ids": ["ct_1", "ct_2"]},
}, idempotency_key="promo-oktober-2026")
```

Jawaban **202**. Penerima yang ditolak (nomor tak sah, ganda, kontak tak ditemukan, tanpa nomor) **tidak**
menggagalkan permintaan; dilaporkan di `meta.rejected_recipients` (maks. 100 contoh) dan
`meta.rejected_count`.

Batas: kuota harian integrasi 1000 penerima per tenant (`429 daily_broadcast_limit_exceeded`, sisa kuota
di `details`), maks. 1000 penerima per permintaan.

Galat khas: `endpoint_not_found`, `template_not_found`, `template_not_approved`,
`template_variables_missing`, `attachment_not_supported`, `no_valid_recipients`, `too_many_recipients`,
`schedule_in_past`.

## Pantau & batalkan

```ts
const list = await client.broadcasts.list({ per_page: 20 });
const b = await client.broadcasts.get("bc_1");          // status + summary {total, pending, sent, delivered, read, failed, cancelled}
const recipients = await client.broadcasts.recipients("bc_1", { status: "failed" });
await client.broadcasts.cancel("bc_1");                 // selesai/sudah batal → ConflictError broadcast_not_cancellable
```

```php
$b = $client->broadcasts->get('bc_1');
$client->broadcasts->cancel('bc_1');
```

```python
b = client.broadcasts.get("bc_1")
client.broadcasts.cancel("bc_1")
```

`status` broadcast: `scheduled`, `processing`, `paused`, `completed`, `cancelled`, `failed`.
Selesainya broadcast dikirim lewat webhook `broadcast.completed`.
