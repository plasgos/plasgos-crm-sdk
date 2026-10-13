# 9. Followup

Urutan followup (langkah, jeda, isi pesan) **dirancang di dashboard Plasgos CRM**. Lewat API, kontak
didaftarkan ke followup yang sudah ada dan dikeluarkan darinya. Pesan followup tidak membawa data
dari sistem Anda (tidak ada variabel per pendaftaran).

## Daftar followup

```ts
const followups = await client.followups.list({ status: "active" });   // Node
const detail = await client.followups.get("fu_1");                     // langkah & jedanya
```

```php
$followups = $client->followups->list(['status' => 'active']);
```

```python
followups = client.followups.list(status="active")
```

## Daftarkan

| Field | Isi |
|---|---|
| `contact` | `{id}`, `{phone_number, …profil}`, `{email, …profil}`, atau `{channel, external_id}` — dicari, atau DIBUAT bila belum ada (nomor/email); kontak yang sudah ada tidak diubah |
| `start_at` | opsional, ISO 8601 — langkah pertama dijadwalkan dari waktu ini |
| `client_reference` | opsional, rujukan Anda |

```ts
const r = await client.followups.enroll("fu_1", { contact: { phone_number: "081234567890", name: "Dewi" }, client_reference: "order-55" }, "order-55-reminder-1");
console.log(r.httpStatus, r.meta.created, r.data.status, r.data.next_run_at);
```

```php
$r = $client->followups->enroll('fu_1', ['contact' => ['id' => 'ct_1']], 'order-55-reminder-1');
```

```python
r = client.followups.enroll("fu_1", {"contact": {"id": "ct_1"}}, idempotency_key="order-55-reminder-1")
```

**201** = terdaftar baru; **200** dengan `meta.created = false` = kontak masih punya jadwal aktif di
followup ini (tak pernah dijadwalkan dua kali).

Galat khas: `followup_not_found`, `contact_not_found` (404); `followup_not_active` (409);
`followup_empty` (422); `not_entitled` (403, paket tanpa followup).

## Keluarkan

```ts
await client.followups.unenroll("fu_1", "ct_1");   // idempoten; tak pernah terdaftar → NotFoundError enrollment_not_found
```

```php
$client->followups->unenroll('fu_1', 'ct_1');
```

```python
client.followups.unenroll("fu_1", "ct_1")
```

Pesan yang belum terkirim dibatalkan. `meta.stopped_messages` = jumlah pesan yang dibatalkan.

## Daftar pendaftaran

```ts
const enrolled = await client.followups.enrollments("fu_1", { status: "active", per_page: 100 });
```

Status pendaftaran juga dikirim lewat webhook `followup.enrollment.completed` dan
`followup.enrollment.stopped`.

> **Tips — daftar ulang.** Idempotency-Key yang sama dalam 24 jam akan dijawab ulang server tanpa
> mendaftarkan lagi. Bila kontak sengaja didaftarkan ulang setelah dikeluarkan, pakai kunci baru
> (mis. akhiri dengan nomor urut).
