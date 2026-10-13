"""Tipe API v3 mengikuti OpenAPI — diperiksa ``mypy`` di CI (``mypy src tests/test_v3_types.py``).

Fungsi ``_typed_usage`` tidak pernah dipanggil; ia hanya ada untuk pemeriksaan statis. ``# type: ignore``
negatif membuktikan tipe tidak longgar: bila tipe melonggar, mypy (``warn_unused_ignores``) gagal.
"""

from __future__ import annotations

from plasgos_crm.v3 import Client, Content, Page
from plasgos_crm.v3 import types as t


def _typed_usage(client: Client) -> None:
    sent = client.messages.send(
        {"endpoint_id": "e1", "contact": {"phone_number": "0812", "name": "Budi"}, "content": Content.text("Halo")}
    )
    status: t.MessageStatus = sent.data["status"]
    client.messages.send({"endpoint_id": "e1", "contact": {"id": "c1"}, "content": Content.template("tpl").custom("a", "1")})
    contact: t.Contact = client.contacts.get("c1").data
    page: Page[t.Contact] = client.contacts.list({"search": "budi"}, per_page=10)
    scopes: list[t.Scope] = client.me.get().data["scopes"]
    client.broadcasts.create(
        {"name": "Promo", "endpoint_id": "e1", "content": Content.template("tpl"), "audience": {"group_ids": ["g1"]}}
    )
    client.messages.send({"endpoint_id": "e1", "content": Content.text("x")})  # type: ignore[typeddict-item]
    client.contacts.list({"per_page": "sepuluh"})  # type: ignore[arg-type]
    _ = (status, contact, page, scopes)


def test_types_module_imports() -> None:
    assert t.ContactRef.__annotations__ and "Operations" not in dir(t)
