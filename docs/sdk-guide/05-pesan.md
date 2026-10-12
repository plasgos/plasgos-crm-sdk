# 5. Pesan

Satu pintu untuk semua channel: WhatsApp resmi & tidak resmi, Instagram, Messenger. Pesan yang dikirim
lewat API muncul di inbox Plasgos CRM seperti pesan agen.

## Titik kirim & template

```ts
const endpoints = await client.endpoints.list();                       // Node
for (const e of endpoints) console.log(e.id, e.channel, e.transport, e.status, e.capabilities);
const templates = await client.endpoints.templates("6281100000001");   // WhatsApp resmi saja
const globals = await client.templateVariables.list();                 // {{full_name}}, dst.
```

```php
$endpoints = $client->endpoints->list();
$templates = $client->endpoints->templates('6281100000001');
$globals = $client->templateVariables->list();
```

```python
endpoints = client.endpoints.list()
templates = client.endpoints.templates("6281100000001")
globals_ = client.template_variables.list()
```

`status` titik kirim: `ready`, `not_ready`, `disconnected`. `capabilities` menyebut jenis isi yang
didukung, apakah butuh template di luar jendela 24 jam, dan apakah bisa broadcast.

## Cek penerima dulu (`send-options`)

Sebelum mengirim ke nomor/kontak, tanyakan titik kirim mana yang bisa dipakai dan apakah wajib template:

```ts
const options = await client.messages.sendOptions({ phone_number: "081234567890" });
for (const o of options.data.options) console.log(o.endpoint.id, o.can_send, o.requires_template, o.reason_label);
```

```php
$options = $client->messages->sendOptions(['phone_number' => '081234567890']);
```

```python
options = client.messages.send_options(phone_number="081234567890")
```

Penerima: tepat satu dari `contact_id`, `phone_number`, atau `channel` + `external_id` (opsional
`endpoint_id`). `data.contact` = `null` bila penerima belum terdaftar.

## Kirim

Badan permintaan:

| Field | Isi |
|---|---|
| `endpoint_id` | titik kirim |
| `recipient` | tepat satu dari `{contact_id}`, `{phone_number}` (WhatsApp saja), `{identity: {channel, external_id}}` |
| `create_contact` | opsional: data kontak bila penerima belum ada (`name`, `email`, `company_name`, `tags`, `lifecycle_stage`, `locale`, `timezone`) |
| `content` | dari builder `Content` |
| `client_reference` | opsional: rujukan Anda (mis. nomor pesanan), ikut kembali di pesan & webhook |

Builder isi:

| Node / PHP / Python | Hasil |
|---|---|
| `Content.text("Halo")` | teks |
| `Content.image(url, caption?)`, `Content.video(…)`, `Content.document(url, fileName?, caption?)` | media (URL https publik) |
| `Content.template(templateId).dynamic("name", "{{full_name}}").custom("order", "INV-1")` | template WhatsApp |
| `….location(lat, lng, name?, address?)` | template berheader lokasi |

`dynamic` = variabel global yang diisi CRM saat kirim; `custom` = nilai tetap.

```ts
const sent = await client.messages.send({
  endpoint_id: "6281100000001",
  recipient: { phone_number: "081234567890" },
  create_contact: { name: "Budi Santoso", tags: ["toko"] },
  content: Content.template("66f1a2b3c4d5e6f708192a3b").dynamic("name", "{{full_name}}").custom("order", "INV-1001"),
  client_reference: "INV-1001",
}, "inv-1001-dikirim");
```

```php
$sent = $client->messages->send([
    'endpoint_id' => '6281100000001',
    'recipient' => ['phone_number' => '081234567890'],
    'content' => Content::template('66f1a2b3c4d5e6f708192a3b')->dynamic('name', '{{full_name}}')->custom('order', 'INV-1001'),
    'client_reference' => 'INV-1001',
], idempotencyKey: 'inv-1001-dikirim');
```

```python
sent = client.messages.send({
    "endpoint_id": "6281100000001",
    "recipient": {"phone_number": "081234567890"},
    "content": Content.template("66f1a2b3c4d5e6f708192a3b").dynamic("name", "{{full_name}}").custom("order", "INV-1001"),
    "client_reference": "INV-1001",
}, idempotency_key="inv-1001-dikirim")
```

Jawabannya **202** dengan `Message`. Pesan yang ditolak channel tetap 202 dengan `data.status = "failed"`
dan `data.error` — periksa keduanya. `meta.conversation_created` / `meta.contact_created` memberi tahu
apakah percakapan/kontak baru dibuat.

Galat khas: `requires_template`, `endpoint_not_found`, `template_not_found`, `template_not_approved`,
`template_variables_missing`, `invalid_phone_number`, `media_url_rejected` (URL privat/lokal),
`invalid_contact_draft`.

## Baca pesan

```ts
const msg = await client.messages.get("wamid.HBgM…");   // id channel (wamid, dsb.) atau id dokumen
console.log(msg.data.status);                            // pending | sent | delivered | read | failed
```

Perubahan status juga dikirim lewat webhook `message.status_updated` — lebih hemat daripada bertanya
berulang.
