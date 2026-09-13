# Machine-readable migration mappings

These YAML files are a **spec** for a future importer. They do not run SQL and must not be treated as complete if a field is missing — see `docs/data-migration/02-field-mapping.md`.

**Do not invent keys.** `unresolved: true` means stop.

| File | Entity |
| --- | --- |
| [companies.yaml](companies.yaml) | Party + operating companies |
| [relationships.yaml](relationships.yaml) | `company_relationships` |
| [users.yaml](users.yaml) | Users (membership unresolved) |
| [brands.yaml](brands.yaml) | Brands |
| [establishments.yaml](establishments.yaml) | Sites |
| [catalogs.yaml](catalogs.yaml) | ID-preserving catalogs |
| [incidents.yaml](incidents.yaml) | QC incidents |
| [technician-incidents.yaml](technician-incidents.yaml) | Technician incidents |
| [contracts.yaml](contracts.yaml) | Contracts |
| [evaluations.yaml](evaluations.yaml) | Evaluations |
| [articles.yaml](articles.yaml) | Articles + client prices + languages |
| [vehicles.yaml](vehicles.yaml) | Vehicles |
| [collaborators.yaml](collaborators.yaml) | Polymorphic → typed pivots |
| [work-orders.yaml](work-orders.yaml) | Merged estimates + work orders |

ID maps: never `legacy_id == new_id` for operational rows. See `docs/data-migration/05-id-mapping-strategy.md`.
