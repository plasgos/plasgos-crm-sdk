# 6. Accounts & References

[← Messages](./05-messages.md) · [WhatsApp Official →](./07-official-whatsapp.md)

---

## 6.1 Accounts — akun WhatsApp unofficial terhubung

| Method | Endpoint | Permission |
|--------|----------|-----------|
| `accounts.list()` | `GET /api/v1/accounts` | `account:list` |
| `accounts.get(accountId)` | `GET /api/v1/accounts/{account_id}` | `account:detail` |

`data` per akun:

```json
{ "id": "665f1c2e9a1b2c3d4e5f6a7b", "name": "CS Toko A", "phone_number": "628123456789" }
```

Gunakan `id` sebagai `account.accountId` saat mengirim pesan
([halaman 5](./05-messages.md)).

### Contoh

```ts
// Node
const accounts = await client.accounts.list();
const first = accounts.data[0];
const detail = await client.accounts.get(first.id);
```

```php
// PHP
$accounts = $client->accounts->list();
$detail = $client->accounts->get($accounts->data[0]['id']);
```

```python
# Python
accounts = client.accounts.list()
detail = client.accounts.get(accounts.data[0]["id"])
```

---

## 6.2 References — variabel template

| Method | Endpoint | Permission |
|--------|----------|-----------|
| `references.variables()` | `GET /api/v1/references/variables` | — (hanya butuh signature valid) |

Mengembalikan daftar variabel yang bisa dipakai pada template WABA official.
`data`:

```json
[
  { "label": "name", "value": "{{name}}", "example": "Budi" },
  { "label": "order_id", "value": "{{order_id}}", "example": "INV-20260406-001" }
]
```

> Server memakai envelope `{ success, data }` untuk endpoint ini; SDK sudah
> menormalisasinya sehingga `envelope.message` bernilai `null` dan
> `envelope.data` langsung berisi array.

### Contoh

```ts
const vars = await client.references.variables();
vars.data.forEach(v => console.log(v.value, "→", v.example));
```

```php
$vars = $client->references->variables();
foreach ($vars->data as $v) {
    echo $v['value'], ' -> ', $v['example'], PHP_EOL;
}
```

```python
vars_ = client.references.variables()
for v in vars_.data:
    print(v["value"], "->", v["example"])
```

---

## 6.3 References — kontak CRM

| Method | Endpoint | Permission |
|--------|----------|-----------|
| `references.contacts({ page?, limit?, search? })` | `GET /api/v1/references/contacts` | — (butuh signature valid) |

| Query | Default | Keterangan |
|-------|---------|-----------|
| `page` | `1` | Nomor halaman |
| `limit` | `20` | Item per halaman |
| `search` | — | Cari berdasar nama / nomor / email |

`metadata` berisi pagination: `{ page, limit, total, total_pages, timestamp }`.

### Contoh

```ts
// Node
const res = await client.references.contacts({ page: 1, limit: 50, search: "budi" });
console.log(res.data);                 // array kontak
console.log(res.metadata.total_pages);
```

```php
// PHP
$res = $client->references->contacts(['page' => 1, 'limit' => 50, 'search' => 'budi']);
```

```python
# Python
res = client.references.contacts(page=1, limit=50, search="budi")
print(res.metadata["total_pages"])
```

### Iterasi semua halaman

```python
page = 1
while True:
    res = client.references.contacts(page=page, limit=100)
    for contact in res.data:
        handle(contact)
    if page >= res.metadata.get("total_pages", 1):
        break
    page += 1
```

Pola pagination lengkap: [Recipes §10.2](./10-recipes.md#102-pagination).

---

[← Messages](./05-messages.md) · [WhatsApp Official →](./07-official-whatsapp.md)
