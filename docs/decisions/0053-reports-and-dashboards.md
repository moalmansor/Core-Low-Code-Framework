# ADR-0053: Reports and dashboards

- Status: Accepted (Phase 5)
- Date: 2026-10-22

## Context

Specification §4.18 asks for:

- a report builder over any form or collection, including joins through relations;
- columns, grouping, the aggregates count, sum, average, minimum and maximum, filters and sorting;
- output as tables, pivot tables and charts (bar, line, pie, area, KPI cards) through ECharts;
- dashboards with a drag-and-drop widget layout, per-role visibility and export to Excel and PDF.

Every read must respect the viewer's record scope (§16.6) and field access (§4.11), exactly as list views and downloads do.

## Decision

**Paths and access**
- A report reads through the same relation paths as download profiles: `DownloadPaths`, at any depth up to `downloads.max_depth`. These cover forward links, multi-links, reverse links and repeating sections.
- Each hop is limited to the viewer's records, and each field to their field access.
- A grouping or value on a field the viewer cannot see, or sees only masked, makes the report unavailable to them. It is never quietly wrong.
- A detail column they cannot see is left out and named.

**Computing**
- The base records come from the viewer's scope plus the report's fixed filters. The filters use the same filter engine as downloads, through to-one and multi-reference paths.
- Grouping and aggregation run in pages of 1,000 record ids, in PHP, over the stored values. Running them in SQL `GROUP BY` would have to rebuild record scope, field masking and to-many joins for both engines. It would also risk counting a record twice where a join fans out.
- A to-many grouping puts a record in every group it reaches. A to-many value adds every related value once per record. Totals are taken over records, not over groups.
- Dates are grouped by day, ISO week, month, quarter or year, in the organization's time zone.
- A report reads at most `reports.max_records` records (System Settings › Reports, default 100,000). When it stops there, it says so.
- A result can be kept for `cache_ttl_seconds`, per viewer and per access epoch, so a change of permissions is never served from the cache.

**Shapes**
- The visualization decides what a report must have:
  - table: values, or a list of columns with nothing grouped;
  - pivot: two groupings;
  - bar, line, area: one or two groupings, and with two groupings one value;
  - pie: one grouping and one value;
  - key figures: one to four values and no grouping.
- These rules are checked when the report is saved.

**Charts**
- Charts use ECharts 6, tree-shaken to bar, line and pie, drawn on canvas. Tooltips are drawn on the canvas too (`renderMode: richText`), so no inline style attribute is needed under the application's CSP.
- Colours are a fixed categorical palette of eight hues in `src/theme/chartPalette.ts`. It is validated for colour-vision deficiency on the light and dark surfaces, with a separate step for each mode.
- A ninth series is not drawn. The chart says so, and the table shows them all.
- Every chart has a table view, which also covers the three light hues below 3:1 contrast.

**Dashboards**
- A widget is a report or a note. Widgets sit on a twelve-column grid, each with a width and a height.
- The builder reorders widgets by dragging, or with buttons for keyboard users. On narrow screens every widget takes the full width.
- Per-role visibility is a list of roles on the widget. A report widget is also shown only to people who may view that report and its form.
- The ERD's `visibility_condition_id` stays unused for now. Roles answer "per-role visibility" directly, and a condition can be added later without migrating data.

**Permissions**
- Building reports and dashboards needs "Manage reports and dashboards".
- Each report and dashboard registers its own permission, `report.{uuid}.view` and `dashboard.{uuid}.view`, granted in Roles & Permissions. Super Admin holds them, like other object permissions.
- People reach what is shared with them from Reports in the sidebar.

**Export**
- A report exports to Excel, CSV or PDF; a dashboard to Excel (one sheet per report) or PDF.
- A pivot is written as a cross table.
- For PDF the screen sends each chart as a PNG. The server accepts only real PNG data up to 2 MB and embeds it above the table.
- Exports are audited.

**Packages**
- Reports and dashboards are configuration-package objects (ADR-0052). A dashboard brings its reports, a report its form, and roles travel by key.

## Consequences

- Reports are always right for the person reading them, at the cost of reading values in PHP. The record cap and per-viewer caching keep large forms usable.
- The same path, filter and masking code serves list views, downloads and reports, so a fix to one is a fix to all.
