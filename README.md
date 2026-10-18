# Glitter Form Builder 0.2.0

This Phase 6 release manages form definitions and immutable published versions and exposes a safe, read-only public schema and G7 Layout preview.

The public preview does not accept submissions. Submission POST, persistence, server-side submission validation, handlers, and submission events are planned for Phase 7.

Public runtime scope:

- `GET /api/modules/glitter-form_builder/forms/{slug}/schema`
- published `FormVersion` lookup
- allowlisted G7 Layout projection
- read-only public form preview
