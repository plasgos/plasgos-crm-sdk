from __future__ import annotations

import uuid
from typing import Any
from urllib.parse import quote

from .._envelope import Envelope
from ._base import Resource, compact

__all__ = ["MessagesResource"]


class MessagesResource(Resource):
    def send(
        self,
        *,
        channel: str,
        account_id: str,
        receiver: dict[str, str],
        content: dict[str, Any],
        message_id: str | None = None,
    ) -> Envelope:
        """Send an unofficial WhatsApp message.

        ``message_id`` must be globally unique; if omitted a UUIDv4 is generated.
        The value actually sent is on ``envelope.metadata["message_id"]``.

        ``content`` uses snake_case wire keys, e.g.
        ``{"type": "document", "data": {"url": ..., "mime_type": ..., "file_name": ...}}``.
        """
        mid = message_id or str(uuid.uuid4())
        body = compact(
            {
                "message_id": mid,
                "channel": channel,
                "account": {"account_id": account_id},
                "receiver": {
                    "phone_number": receiver["phone_number"],
                    "name": receiver["name"],
                },
                "content": {
                    "type": content.get("type"),
                    "data": compact(dict(content.get("data") or {})),
                },
            }
        )
        env = self._http.request("POST", "/api/v1/messages", json=body)
        return Envelope(
            status=env.status,
            message=env.message,
            data=env.data,
            metadata={"message_id": mid, **env.metadata},
            raw=env.raw,
            http_status=env.http_status,
        )

    def list(self) -> Envelope:
        return self._http.request("GET", "/api/v1/messages")

    def get(self, message_id: str) -> Envelope:
        return self._http.request("GET", f"/api/v1/messages/{quote(message_id, safe='')}")
