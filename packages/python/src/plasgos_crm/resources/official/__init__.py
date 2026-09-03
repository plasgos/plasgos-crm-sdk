from __future__ import annotations

from ..._http import HttpClient
from .accounts import OfficialAccountsResource
from .broadcasts import OfficialBroadcastsResource
from .messages import OfficialMessagesResource
from .shared_waba import OfficialSharedWabaResource
from .templates import OfficialTemplatesResource

__all__ = ["OfficialNamespace"]


class OfficialNamespace:
    """``client.official.*`` — WhatsApp Business API (official) endpoints."""

    def __init__(self, http: HttpClient) -> None:
        self.accounts = OfficialAccountsResource(http)
        self.templates = OfficialTemplatesResource(http)
        self.messages = OfficialMessagesResource(http)
        self.broadcasts = OfficialBroadcastsResource(http)
        self.shared_waba = OfficialSharedWabaResource(http)
