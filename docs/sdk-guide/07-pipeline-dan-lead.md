# 7. Pipeline & lead

## Pipeline & stage

```ts
const pipelines = await client.pipelines.list();   // Node
for (const p of pipelines.data) for (const s of p.stages) console.log(p.id, p.name, s.id, s.name, s.kind);
```

```php
$pipelines = $client->pipelines->list();
```

```python
pipelines = client.pipelines.list()
```

`stage.kind` = `open`, `won`, atau `lost`. Untuk lead baru, pilih stage berjenis `open`.

## Buat lead

| Field | Isi |
|---|---|
| `pipeline_id` | wajib |
| `contact` | `{id}`, `{phone_number, …profil}`, `{email, …profil}`, atau `{channel, external_id}` — dicari, atau DIBUAT bila belum ada (nomor/email); kontak yang sudah ada tidak diubah |
| `stage_id` | opsional — bawaan stage pertama |
| `score`, `tags`, `note` | opsional |
| `source_detail` | opsional: asal lead, mis. `wordpress:cf7:12` |

```ts
const r = await client.leads.create(
  { contact: { phone_number: "081234567890", name: "Sari" }, pipeline_id: "pl_1", stage_id: "st_baru", source_detail: "landing:promo" },
  "form-123-lead",
);
console.log(r.httpStatus, r.meta.created, r.data.stage);   // 201 baru | 200 + created=false
```

```php
$r = $client->leads->create(['contact' => ['id' => 'ct_1'], 'pipeline_id' => 'pl_1'], 'form-123-lead');
```

```python
r = client.leads.create({"contact": {"id": "ct_1"}, "pipeline_id": "pl_1"}, idempotency_key="form-123-lead")
```

**Satu lead aktif per kontak.** Bila kontak masih punya lead aktif (di channel atau pipeline mana pun),
server tidak membuat lead kedua: jawabannya 200 dengan `meta.created = false` dan lead yang sudah ada.

Galat khas: `pipeline_not_found`, `stage_not_found`, `contact_not_found`.

```ts
const lead = await client.leads.get("ld_1");   // stage: { id, name }, status, score, tags, source …
```

Perpindahan stage & hasil deal dikirim lewat webhook `lead.stage_changed`, `deal.won`, `deal.lost`.
