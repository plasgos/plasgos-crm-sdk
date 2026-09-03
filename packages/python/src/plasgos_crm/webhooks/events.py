from __future__ import annotations

MESSAGE_RECEIVED = "message.received"
MESSAGE_STATUSES = "message.statuses"

PUBLIC_EVENTS = frozenset({MESSAGE_RECEIVED, MESSAGE_STATUSES})

__all__ = ["MESSAGE_RECEIVED", "MESSAGE_STATUSES", "PUBLIC_EVENTS"]
