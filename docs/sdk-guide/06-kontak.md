# 6. Kontak

Bentuk `Contact`: `id`, `name`, `phone_number` (E.164), `email` (huruf kecil), `company_name`, `tags`,
`group_ids`, `lifecycle_stage`, `locale`, `timezone`, `custom_fields`, `source`, `created_at`, `updated_at`.

## Daftar & cari

```ts
const page = await client.contacts.list({ search: "budi", tag: "vip", per_page: 50 });          // Node
const found = await client.contacts.lookup({ phone_number: "081234567890" });                    // 404 bila tak ada
```

```php
$page = $client->contacts->list(['search' => 'budi', 'tag' => 'vip', 'per_page' => 50]);
$found = $client->contacts->lookup(['email' => 'budi@contoh.id']);
```

```python
page = client.contacts.list(search="budi", tag="vip", per_page=50)
found = client.contacts.lookup(channel="instagram", external_id="1789…", endpoint_id="1784…")
```

Saringan daftar: `search` (nama, nomor, email, perusahaan), `group_id`, `tag`, `updated_from` (ISO 8601 —
urutan berubah menjadi `updated_at` naik, untuk sinkron bertahap), `page`, `per_page`.

`lookup` menerima tepat satu dari `phone_number`, `email`, atau `channel` + `external_id`
(+ `endpoint_id`). Kontak yang sudah digabung diikuti ke tujuannya.

## Buat, upsert, ubah

| Metode | Perilaku |
|---|---|
| `create(contact)` | Buat saja. Nomor/email sudah dipakai → `ConflictError identity_taken` (`details.contact_id`). |
| `upsert(contact)` | Cari dengan `match_on` (`phone_number` bawaan, atau `email`); ada → diperbarui (200), tidak → dibuat (201). `meta.created` = `true`/`false`. **Tag & grup ditambahkan**, tidak diganti. |
| `get(id)` | Kontak sumber gabungan mengembalikan kontak tujuan dengan `meta.redirected_from`. |
| `update(id, changes)` | Ubah sebagian. `tags` & `group_ids` memakai `{add: [], remove: []}`; `custom_fields` per kunci (`null` menghapus). |

```ts
const r = await client.contacts.upsert(
  { match_on: "email", email: "budi@contoh.id", name: "Budi", tags: ["woocommerce"], custom_fields: { wp_user_id: "42" } },
  "wp-user-42-v3",
);
console.log(r.httpStatus, r.meta.created);
await client.contacts.update(r.data.id, { tags: { add: ["vip"], remove: ["prospek"] } });
```

```php
$r = $client->contacts->upsert(['phone_number' => '081234567890', 'name' => 'Budi', 'tags' => ['woocommerce']], 'wc-customer-17');
$client->contacts->update($r->data['id'], ['group_ids' => ['add' => ['grp_vip']]]);
```

```python
r = client.contacts.upsert({"phone_number": "081234567890", "name": "Budi", "tags": ["woocommerce"]}, idempotency_key="wc-customer-17")
client.contacts.update(r.data["id"], {"custom_fields": {"catatan": None}})
```

Galat khas: `identity_taken`, `group_not_found`, `invalid_custom_field`, `invalid_phone_number`,
`contact_limit_reached`.

## Grup kontak

```ts
for await (const g of (await client.contactGroups.list({ per_page: 100 })).autoPaging()) console.log(g.id, g.name, g.total_contacts);
```

```php
$groups = $client->contactGroups->list();
```

```python
groups = client.contact_groups.list()
```

Grup dipakai sebagai sasaran broadcast (`audience.group_ids`).
