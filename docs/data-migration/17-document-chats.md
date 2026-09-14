# 17 — Document chats (typed per domain)

**Status:** schema + UI in v2. Production row migration from polymorphic chats is **not** implemented yet.

## Legacy shape

Optima Prod used a polymorphic chat stack:

| Legacy | Role |
| --- | --- |
| `chats` | One chat per document (`modelo_type` + `modelo_id`) |
| `lineas_chats` | Messages (text / system / file) |
| `chats_pendientes` | Unread pivot (chat ↔ user) |
| `chats_silenciados` | Mute pivot (chat ↔ user) |
| `archivos` (morph on message) | Message attachments |

Models with `TieneChat` in Prod included work orders, estimates (same OT table family), incidents, evaluations, technician requests, technicians, plus deferred domains (tickets, PRL, inventory, purchase invoices, CX, payment effects).

## v2 shape

Polymorphic chats are **split** into typed tables per document domain that already exists in v2:

| Domain | Chat document type | Parent FK | Tables |
| --- | --- | --- | --- |
| Work order / estimate | `work_order` | `work_order_id` | `work_order_chats`, `work_order_chat_messages`, `work_order_chat_unreads`, `work_order_chat_mutes`, `work_order_chat_message_attachments` |
| Incident | `incident` | `incident_id` | `incident_chat_*` |
| Evaluation | `evaluation` | `evaluation_id` | `evaluation_chat_*` |
| Technician request | `technician_request` | `technician_request_id` | `technician_request_chat_*` |
| Technician | `technician` | `company_relationship_id` | `technician_chat_*` |

Estimates and confirmed work orders share `work_orders` and therefore the same `work_order` chat type.

## Behaviour mapping

| Legacy concept | v2 |
| --- | --- |
| Auto-create chat on document create | `CreatesDocumentChat` trait (WO / incident / evaluation / technician request). Technicians: `ensureChat` on edit only. |
| Text / system / file messages | `type` enum: `text`, `system`, `file` |
| Private lines | `is_private`; visible only when `users.is_internal_employee` |
| UI “Notificar / No notificar” | Mute toggle on `*_chat_mutes` (same as legacy `chats_silenciados`; not a separate notify flag/table) |
| Chat vs Histórico tabs | Same message list: Chat = non-`system`; History = `type=system`. System lines are written by `StatusChangeHistoryService` on status create/change (legacy SYS user 83 + `historial_cambios_estados`). |
| Status audit | `status_change_histories` (`document_type` + `document_id` + old/new status ids). System chat lines for non-WO docs are stored private (legacy), but **always returned** in the chat payload so the History tab is readable. |
| Pendientes | `*_chat_unreads` |
| Silenciados | `*_chat_mutes` (muted users skip unread fan-out) |
| Message archivos | Typed `*_chat_message_attachments` (no morph `archivos`) |
| Rich composer | TipTap `RichTextEditor` (HTML body) |

## HTTP surface

Routes under `/document-chats/{type}/{document}` (`document-chats.*`):

- `GET …/messages` — payload
- `POST …/messages` — text
- `POST …/messages/attachments` — file
- `POST …/read`
- `POST` / `DELETE …/mute`
- `GET …/attachments/{attachment}/download`

Edit pages receive Inertia `chat` + `can.post_chat` and render `DocumentChatPanel`.

## Deferred (no v2 tables yet)

Do **not** invent chat tables for: Ticket, PrlTicket, Inventario, FacturaCompra, DraftPurchaseInvoice, CX, EfectoPago. Keep them in the inventory “skipped / deferred” section until those domains exist in v2.

## Import notes (future)

1. Resolve legacy `modelo_type` → v2 document type + new parent id via mapping tables.
2. Never reuse legacy chat / message IDs as v2 primary keys for operational rows.
3. Remap attachment paths into the typed storage prefixes (`work-order-chats/`, etc.).
4. Skip Telegram / channel-specific message metadata unless a v2 consumer exists.
5. No extra tables needed for Notify/Private/History on document chats — map `privado` → `is_private`, mute rows → `*_chat_mutes`, SYS/`locale` lines → `type=system`.
6. Technician incidents keep `technician_incident_messages` for user conversation only. Status changes write `status_change_histories` and a **system** line on the technician DocumentChat (`technician_chat_*`, right sidebar History tab).

