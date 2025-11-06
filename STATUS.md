# Platform Implementation Status (Nov 2025)

This document captures the current progress of the Eclectyc Energy platform so future iterations can pick up exactly where work left off.

## ✅ Completed Foundation

- **Infrastructure**: Slim 4 app skeleton, PSR-4 autoloading, environment loading, Twig views, DI container, logging, session bootstrap.
- **Database**: Full 12-table schema with migrations, seeds, and PDO connection helpers.
- **Tooling**: CLI utilities for migrations, seeding, CSV import, aggregation, SFTP export; structure and health check tools.
- **UI**: Base layout, dashboard scaffold, login view, admin/report listings, consistent styling.
- **Reports**: Consumption and cost dashboards now backed by controllers and live aggregates.
- **APIs**: `/api/health` endpoint plus controller-backed meter/import feeds returning aggregated roll-ups.
- **Access Control**: Session-backed auth service, role-aware middleware, and navigation that respects admin/manager/viewer capabilities.
- **Aggregation**: Domain services for daily and period roll-ups (weekly/monthly/annual), CLI wrappers, and audit logging feeding the aggregation tables.
- **Imports & Exports**: Central CSV ingestion service shared by CLI and admin UI (dry-run support, batch flash messaging), import history dashboard, and operational SFTP export pipeline with phpseclib authentication and admin activity view.
- **Health Monitoring**: `/api/health` now reports environment, SFTP, filesystem, and recent activity checks with graded status codes.
- **Requirements**: High-level capability matrix tracked in `docs/product_requirements.md`.

## ⚠️ Work Still Required

- **Authentication & Authorization**
  - Harden login flow (redirect handling, throttling, password reset).
  - Extend role-based policies to APIs and non-admin UIs (manager workflows, viewer read-only safeguards).

- **Controller Layer**
  - Audit remaining anonymous closures (e.g. 404 handler) and ensure consistent middleware/response patterns.
  - Normalise directory casing (`app/http` vs `app/Http`) and remove duplicate route definitions after deployment checks.

- **Data Aggregation & Analytics**
  - Automate cron/scheduler orchestration for daily and period aggregations with telemetry and failure alerts.
  - Layer in comparison snapshots (prev day/week/month/year), baseload analytics, and missing-data detection.
  - Integrate external datasets (temperature, calorific values) to power AI insights and carbon reporting.

- **Data Imports**
  - Add retry workflows, reprocessing, and richer attribution/notes for batches surfaced in the history dashboard.
  - Introduce background processing/queueing to avoid long-running requests for large files.

- **CRUD & Admin UI**
  - Add create/edit flows for sites, meters, tariffs; introduce validation, flash messaging, and REST endpoints.
  - Support sub-meter vs boundary meter metadata, meter direction, and custom key metrics per site.

- **Reporting & Visualisation**
  - Populate report templates with aggregated data, add charts (e.g., Chart.js) and AJAX filters.
  - Deliver drill-down charts (48-period graphs, comparisons) and carbon/flexible-tariff dashboards.

- **Tariff Engine & Switching**
  - Model complex tariff structures (time bands, flexible offers) and cost comparison workflows.
  - Prepare for supplier integrations and customer switching analysis.

- **Exports & Automation**
  - Enhance export monitoring with delivery receipts, retention policies, and email packaging.

- **Testing & QA**
  - Expand automated tests, fixtures, and sample data; ensure the health endpoint covers new dependencies.

## Recommended Next Milestones

1. Finalise authentication (session hardening, middleware reuse, role-scope authorisation).
2. Replace remaining route closures with controllers (`DashboardController`, `MetersController`, `ImportController`).
3. Operationalise ingestion → aggregation (schedule multi-range aggregations, add comparison caches, automate ingestion retries/alerts).
4. Build CRUD/editor flows for sites/meters/tariffs including metadata (direction, sub-meters, key metrics).
5. Deliver the tariff engine, switching analysis, and production-grade exports (SFTP/email) before expanding to carbon/flexible dashboards.

Keep this file updated when major milestones are completed so the roadmap stays accurate.
