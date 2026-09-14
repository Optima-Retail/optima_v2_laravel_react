# Establishment attachments & form-template links

## Attachments (`archivos` → `establishment_attachments`)

Legacy polymorphic `archivos` for establishments used a `privado` boolean. v2 stores that as `is_private` on `establishment_attachments`.

| Legacy | v2 |
| --- | --- |
| `nombre` | `name` |
| `ruta` | `path` |
| `privado` | `is_private` |
| uploader | `uploaded_by` → `users` |

Permissions (policy-discovered):

- Public list/upload/download/delete: `establishments.view-attachments` / `upload-attachments` / `download-attachments` / `delete-attachments`
- Private section: also requires `establishments.view-private-attachments`
- Listing without private permission filters `is_private = false`
- Upload/download/delete of private rows requires the private permission as well

## Form templates (`establecimiento_plantilla` → `establishment_form_template`)

Legacy columns: `establecimiento_id`, `plantilla_id`, `tipo_ot_id`.

v2 pivot shape:

- `id`
- `establishment_id`
- `form_template_id`
- `work_order_type_id`
- unique `(establishment_id, form_template_id, work_order_type_id)`

Fresh installs use the create migration with this shape. Environments that already had the old composite PK `(establishment_id, form_template_id)` are rebuilt by `2026_09_14_140100_rebuild_establishment_form_template_table` (drop + recreate; safe when empty).

Synced from the establishment edit form as `form_template_links: [{ id?, form_template_id, work_order_type_id }]`.
