# Product Requirements Traceability

Friend-supplied blueprint captured below so we can benchmark the current platform against the desired capabilities. Status values: **Supported**, **Partial**, **Planned** (not yet started), or **Out of Scope** for the first release.

## Data Aggregation & Processing

| Capability | Status | Notes |
| --- | --- | --- |
| Aggregate raw meter feeds (1/5/30 minute) into day/week/month/year buckets | Partial | Daily aggregator service + cron covers daily snapshots; weekly/monthly/annual rollups still pending. |
| Store historical comparison windows (previous day/week/month/year) for fast reporting | Planned | Requires aggregation jobs and cache tables; not implemented. |
| Handle sub-minute (1 min) and half-hourly data ingestion pipelines | Planned | `domain/ingestion` scaffolding exists but no logic. |
| Support multiple meter channels (import/export) with accurate aggregation | Planned | Schema tracks `reading_type` but not direction. Needs model expansion. |
| Flexible import adapters for multiple formats (HH, TOU) | Planned | `scripts/import_csv.php` exists but limited. |
| Enrich data with external feeds (calorific values, temperatures) | Planned | No integration yet. |

## Organisational Hierarchy & Setup

| Capability | Status | Notes |
| --- | --- | --- |
| Supplier hierarchy with branding overrides | Planned | `suppliers` table exists; UI/theme not implemented. |
| Company-level branding and inheritance | Planned | `companies` table present; theming not implemented. |
| Sites and meters with hierarchy, boundary vs sub-meter relationships | Partial | Tables exist; admin views list sites/meters counts. Need relationship flags and UI controls. |
| Region taxonomy | Supported | `regions` table seeded; reporting not wired yet. |
| Custom key analysis metric (user defined) | Planned | No schema field or UI. |
| Meter type coverage (elec/gas/water/heat/steam/EV/battery etc) | Partial | `meters.meter_type` enum limited to electricity/gas/water/heat; extend list + metadata. |

## Tariff Management

| Capability | Status | Notes |
| --- | --- | --- |
| Upload simple fixed tariffs | Partial | Tariff CRUD absent; listing view shows seeded records. |
| Support split tariffs by time bands | Planned | Schema holds peak/off-peak; need ingestion + calculator. |
| Complex intraday rates per interval | Planned | Requires tariff schedule modelling. |
| Automated updates from supplier/customer | Planned | Needs API ingestion & versioning. |
| Flexible tariff performance reporting | Planned | Depends on aggregation + tariffs engine. |

## Reporting & Analytics

| Capability | Status | Notes |
| --- | --- | --- |
| Carbon footprint estimation | Planned | Not yet implemented. |
| 48-period interactive graphs with drill/compare | Planned | Frontend placeholder only. |
| Missing data alerts | Planned | Requires validation pipeline. |
| Comparative analytics (site/meter vs previous periods) | Planned | Needs aggregation tables. |
| Group-level comparisons (company, region) | Planned | Summaries awaited from aggregation services. |
| Flexible tariff success metrics | Planned | Dependent on tariffs + aggregation. |
| Customer switching analysis vs alternative suppliers | Planned | Requires tariff library + AI/external APIs. |
| Usage vs external temperature trends | Planned | External weather feed integration outstanding. |
| Baseload analysis (identify constant load) | Planned | Needs analytics module on aggregated data. |
| AI-driven insights (external factors, key metrics) | Planned | No AI services integrated yet. |

## Data Processing & Export

| Capability | Status | Notes |
| --- | --- | --- |
| Accept HH data down to 1-minute granularity | Planned | Current importer basic; extend. |
| Channel-specific import (import/export per meter) | Planned | Schema tweak + ingestion logic required. |
| Support multiple import formats and scheduling | Planned | Only CSV script available. |
| Fetch external info (gas calorific, weather) | Planned | No connectors yet. |
| Export data at any granularity (manual/automated SFTP/email) | Planned | `scripts/export_sftp.php` stub exists; needs scheduler + API. |

## User Interface & Experience

| Capability | Status | Notes |
| --- | --- | --- |
| Supplier/customer white-labelling | Planned | Theming hooks absent. |
| Adjustable dashboards with saved preferences | Planned | Dashboard static today. |
| Embedded reporting widgets and designer | Planned | Twig views placeholder only. |
| AI assistance for reporting, recommendations, chat | Planned | Requires AI integration. |

## User Management & Access Control

| Capability | Status | Notes |
| --- | --- | --- |
| Hierarchical access (supplier → company → region → site → meter) | Planned | Auth middleware supports roles; need scope-based ACLs. |
| Role management with super users and granular scopes | Partial | Roles (admin/manager/viewer) exist, but scope assignments missing. |
| Credential lifecycle (password policies, reset) | Planned | Auth flow basic; no reset or MFA. |

## Next Actions

1. Extend roadmap and `STATUS.md` with high-level themes derived from this document.
2. Prioritise enabling data aggregation (foundation for comparisons, baseload, carbon). This unlocks multiple downstream analytics requirements.
3. Design scope-based access model (hierarchical permissions) alongside expanded meter metadata.
4. Draft technical specs for tariff engine and flexible reporting exports.

Document maintained by: GitHub Copilot (Nov 2025). Update whenever requirements evolve or capabilities ship.
