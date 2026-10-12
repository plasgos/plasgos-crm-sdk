# 11. Resep

## Notifikasi status pesanan toko online

Kunci idempotensi bermakna mencegah notifikasi ganda saat status dipicu ulang atau tugas antrean diulang.

```php
function notifyOrderStatus(Client $client, array $order, string $status): void
{
    $client->messages->send([
        'endpoint_id' => '6281100000001',
        'recipient' => ['phone_number' => $order['billing_phone']],
        'create_contact' => ['name' => $order['billing_name'], 'email' => $order['billing_email'], 'tags' => ['toko']],
        'content' => Content::template('tpl_order_status')
            ->dynamic('name', '{{full_name}}')
            ->custom('order', $order['number'])
            ->custom('status', $status),
        'client_reference' => 'order-' . $order['id'],
    ], idempotencyKey: "wc-{$order['id']}-{$status}");
}
```

- Jalankan dari antrean, bukan dari permintaan pengunjung.
- `requires_template` → titik kirim WhatsApp resmi di luar jendela 24 jam; pakai template.
- Simpan `data.id` pesan bila ingin mencocokkan webhook `message.status_updated`.

## Formulir → kontak → lead → sambutan

```ts
const contact = await client.contacts.upsert({ phone_number: form.phone, name: form.name, tags: ["formulir"] }, `form-${entryId}-contact`);
await client.leads.create({ contact_id: contact.data.id, pipeline_id: "pl_1", source_detail: "landing:promo" }, `form-${entryId}-lead`);
if (form.consent) {
  await client.messages.send({ endpoint_id: "6281100000001", recipient: { contact_id: contact.data.id }, content: Content.template("tpl_welcome").dynamic("name", "{{full_name}}") }, `form-${entryId}-welcome`);
}
```

Setiap langkah memakai kunci turunan id kiriman: bila langkah ke-3 gagal sementara dan seluruh tugas
diulang, langkah 1–2 dijawab ulang server — tidak ada lead atau pesan ganda.

## Sinkron kontak bertahap

```python
from datetime import datetime, timezone

since = load_checkpoint()   # ISO 8601 terakhir
for contact in client.contacts.list(updated_from=since, per_page=100).auto_paging():
    save_contact_locally(contact)
    since = contact["updated_at"]
save_checkpoint(since)
```

Dengan `updated_from`, urutan menjadi `updated_at` naik sehingga penanda (checkpoint) aman dilanjutkan.
Untuk perubahan seketika, langganan webhook `contact.created` / `contact.updated`.

## Pengingat pembayaran

Daftarkan pembeli ke followup saat pesanan belum dibayar, keluarkan setelah dibayar/dibatalkan:

```php
$r = $client->followups->enroll('fu_bayar', ['contact' => ['phone_number' => $phone, 'name' => $name]], "order-{$id}-reminder-{$round}");
$contactId = $r->data['contact_id'];
// … pesanan dibayar:
$client->followups->unenroll('fu_bayar', $contactId);
```

## Menguji tanpa server

```ts
// Node: fetch tiruan
const client = new Client("ak", "sk", { baseUrl: "https://crm.test", fetch: async () => new Response(JSON.stringify({ success: true, message: "OK", data: {}, meta: {} })) });
```

```php
// PHP: PSR-18 client tiruan
$client = new Client('ak', 'sk', new ClientConfig(baseUrl: 'https://crm.test', httpClient: $fakePsr18Client));
```

```python
# Python: httpx.MockTransport
import httpx
client = Client("ak", "sk", base_url="https://crm.test", transport=httpx.MockTransport(lambda r: httpx.Response(200, json={"success": True, "message": "OK", "data": {}, "meta": {}})))
```

Hook `sleep`/`sleeper` dan `clock` membuat uji coba ulang & tanda tangan deterministik. Vektor tanda
tangan bersama: [`../vectors-v3.json`](../vectors-v3.json).

## Praktik baik

- Satu Idempotency-Key per **maksud**, bukan per percobaan.
- Bercabanglah dengan `errorCode`, bukan teks pesan.
- Catat `requestId` di log Anda.
- Jangan kirim dari permintaan pengunjung; pakai antrean dan biarkan coba ulang SDK + antrean yang bekerja.
- Hormati `rateLimit.remaining`; untuk impor besar, pakai `contacts.upsert` bertahap dengan jeda.
