from __future__ import annotations

from typing import Any
from urllib.parse import quote

from ..._envelope import Envelope
from .._base import Resource, compact

__all__ = ["OfficialSharedWabaResource"]


class OfficialSharedWabaResource(Resource):
    def send_otp(
        self,
        serial_token: str,
        *,
        to: str,
        otp_code: str | None = None,
        metadata: dict[str, Any] | None = None,
    ) -> Envelope:
        """Send an OTP through a shared WABA serial token."""
        return self._http.request(
            "POST",
            f"/api/v1/official/shared-waba/otp/{quote(serial_token, safe='')}",
            json=compact({"to": to, "otp_code": otp_code, "metadata": metadata}),
        )
