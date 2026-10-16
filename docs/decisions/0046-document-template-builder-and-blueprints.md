# ADR-0046: Document template builder — values, formats, blocks and blueprints

- Status: Accepted (Phase 4)
- Date: 2026-10-14

## Context

Review of Phase 4 found the document template builder too narrow. It asked for:

- placeholders picked from linked forms and system values;
- child-record tables with chosen columns;
- conditional blocks;
- per-placeholder formatting, including Arabic-Indic digits;
- a header and footer with page numbers, logo and title;
- signatures, QR codes and barcodes;
- templates saved as blueprints and carried by configuration packages.

## Decision

### Values and formats

- Placeholders are picked from the field tree (`GET /forms/{form}/field-tree`): the form's fields, linked forms by relation path, and system values. The system values added are `@created_by`, `@updated_by`, `@assignee`, `@user` (the person generating the document) and `@now`.
- A format is written after a bar: `{{ path | format:arg }}`. The list is fixed and parsed, never evaluated:
  - `date` and `datetime`, each `short`, `medium`, `long` or `iso`;
  - `number:0`–`number:6`;
  - `currency:CODE`, which takes the decimals and symbol of a Reference-data currency;
  - `digits:arabic` or `digits:western`.
- Formats chain from left to right. An unknown format is refused when the template is saved, in designed and Word templates alike.
- A value the reader may not see stays empty whatever its format.

### Blocks

- **Linked records.** The records of another form whose reference field points at this record, with chosen columns, each with its own format, up to a row limit. The block is found through `GET /forms/{form}/linked-from`. Each listed record is read with the readers' record scope and field access.
- **Signature.** Up to four signatories, each with a label and an optional name, which may be a placeholder. A date line can be added.
- **QR code or barcode.** A QR code carries the record's link; a Code 128 barcode carries the record number. Both are drawn on the server as PNG images, the QR code by `mpdf/qrcode` and the barcode with GD. Nothing is fetched from outside, so the egress gateway is not involved.
- **Conditions.** Every block keeps its optional condition from the conditions builder, in the expression language.

### Title, header and footer

- The design has a title, a header (the system logo and a text) and a footer (a text and page numbers: none, "Page n" or "Page n of m").
- In the PDF, mPDF's HTML header and footer repeat them on every page. The top margin stretches to fit the header.
- In Word, they are written as real `header1`/`footer1` parts with `PAGE` and `NUMPAGES` fields.

### Blueprints

- A template, designed or Word, is saved as a blueprint of the new kind `document_template`. The `ck_blueprints_kind` check is widened to allow it.
- The Word files are embedded base64 in the blueprint's content, so the content hash covers them. Import verifies that hash.
- Templates made from a blueprint receive its updates through the same per-property three-way merge as other blueprints. Local changes are kept and listed as conflicts.
- Configuration packages are Phase 5 (§4.22). They will carry document templates as these blueprints, so nothing further is needed in Phase 4.

## Consequences

- The same placeholder syntax and formats work in e-mail subjects, designed documents and Word files.
- Formats are data in the template, so a template exported as a blueprint renders the same in any environment that has the same currencies.
