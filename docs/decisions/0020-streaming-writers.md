# ADR-0020: Streaming spreadsheet writers for large exports

- Status: Accepted (Phase 0, pending owner review)
- Date: 2026-10-02

## Context

Downloads up to `max_rows` with multiple sheets must not exhaust memory.

## Decision

Use maatwebsite/excel for imports and small exports. Large exports and custom downloads use OpenSpout streaming writers with keyset chunking.

## Consequences

Memory stays bounded and roughly constant regardless of row count. Styled headers, frozen rows, multiple sheets, and RTL sheets are supported by the writer.
