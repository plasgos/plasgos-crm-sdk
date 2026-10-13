"""Builders for message ``content`` — the same shape as the CRM inbox (docs/SDK_SPEC.md §3.4).

::

    Content.text("Halo")
    Content.image("https://…/promo.jpg", "Promo")
    Content.template("66f1…").dynamic("name", "{{full_name}}").custom("order", "INV-1")
"""

from __future__ import annotations

from typing import TYPE_CHECKING, Any, Optional, cast

if TYPE_CHECKING:
    from . import types as _t

__all__ = ["Content", "TemplateContent", "MEDIA_TYPES"]

MEDIA_TYPES = ("image", "video", "audio", "document")


class TemplateContent:
    """WhatsApp template content. The CRM renders the template; the client only maps variables."""

    def __init__(self, template_id: str) -> None:
        self._template_id = template_id
        self._variables: list[dict[str, str]] = []
        self._location: Optional[dict[str, Any]] = None

    def dynamic(self, param: str, placeholder: str) -> "TemplateContent":
        """Global placeholder resolved by the CRM at send time, e.g. ``{{full_name}}``."""
        return self._variable(param, "dynamic", placeholder)

    def custom(self, param: str, value: str) -> "TemplateContent":
        """Literal value."""
        return self._variable(param, "custom", value)

    def location(
        self, latitude: float, longitude: float, name: Optional[str] = None, address: Optional[str] = None
    ) -> "TemplateContent":
        """Only for templates with a LOCATION header; media headers use the approved template's media."""
        self._location = {"latitude": latitude, "longitude": longitude, "name": name, "address": address}
        return self

    def to_dict(self) -> _t.ContentTemplate:
        template: dict[str, Any] = {
            "template_id": self._template_id,
            "variables": [dict(v) for v in self._variables],
        }
        if self._location is not None:
            template["location"] = dict(self._location)
        return cast("_t.ContentTemplate", {"type": "template", "template": template})

    def _variable(self, param: str, kind: str, value: str) -> "TemplateContent":
        entry = {"param": param, "type": kind, "value": value}
        for i, existing in enumerate(self._variables):
            if existing["param"] == param:
                self._variables[i] = entry
                return self
        self._variables.append(entry)
        return self


class Content:
    @staticmethod
    def text(body: str, preview_url: bool = False) -> _t.ContentText:
        return {"type": "text", "text": {"body": body, "preview_url": preview_url}}

    @staticmethod
    def media(
        type: str,  # noqa: A002 - wire field name
        url: str,
        caption: Optional[str] = None,
        file_name: Optional[str] = None,
        mime_type: Optional[str] = None,
    ) -> _t.ContentMedia:
        if type not in MEDIA_TYPES:
            raise ValueError("Content.media type must be one of image, video, audio, document")
        return cast(
            "_t.ContentMedia",
            {"type": type, "media": {"url": url, "caption": caption, "file_name": file_name, "mime_type": mime_type}},
        )

    @staticmethod
    def image(url: str, caption: Optional[str] = None) -> _t.ContentMedia:
        return Content.media("image", url, caption)

    @staticmethod
    def video(url: str, caption: Optional[str] = None) -> _t.ContentMedia:
        return Content.media("video", url, caption)

    @staticmethod
    def document(url: str, file_name: Optional[str] = None, caption: Optional[str] = None) -> _t.ContentMedia:
        return Content.media("document", url, caption, file_name)

    @staticmethod
    def template(template_id: str) -> TemplateContent:
        return TemplateContent(template_id)
