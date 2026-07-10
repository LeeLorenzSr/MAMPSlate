# Generic extensibility layer

Migration `022_extensibility_and_operations.sql` adds reusable primitives for
specializing MAMPSlate without turning the core into a vertical product. The
core entity types are `article`, `page`, and `listing`; local modules may add
their own entity type through a module manifest.

## Custom fields

Administrators with `content.model.manage` define typed fields at
`/admin/content-model`. Supported types are text, textarea, number, date, URL,
boolean, and select. Field definitions choose required/public flags and a sort
order. Values live in `content_field_values`, keyed by entity type/id and field
key; the public renderer only shows public fields.

The API exposes extensions under an `extensions` object on content responses.
PATCH/PUT can replace individual groups, for example:

```json
{
  "extensions": {
    "custom_fields": {"release_date": "2026-08-14"},
    "term_ids": [3, 7]
  }
}
```

## Relationships, terms, links, embeds, and collections

- `entity_relationships` connects any supported source/target entities with a
  generic relationship type such as `features`, `references`, or `part_of`.
- `taxonomies`, `taxonomy_terms`, and `entity_terms` provide reusable nested
  terms beyond the legacy article categories/tags. Manage them at
  `/admin/taxonomies`.
- `external_links` stores label, normalized HTTPS URL, service type, order, and
  safe `rel` values. When analytics is enabled, public managed links go through
  `/go?link={id}` to record only an aggregate click.
- `content_embeds` permits YouTube, Spotify, Apple Music, Bandcamp, and
  SoundCloud source URLs. Only YouTube/Spotify currently render iframe players;
  the other providers render a safe outbound link until an explicit embed form
  is added.
- `content_collections`/`content_collection_items` create public or private
  curated lists such as Featured, New, or Staff picks. Manage them at
  `/admin/collections`.

All string references are validated; polymorphic entity references are owned by
application code so a copied project can add a local entity type without a core
foreign-key migration.

## Workflow and scheduling

Content supports `draft`, `submitted`, `needs_changes`, `scheduled`,
`published`, `archived`, and `rejected`. Only users with the existing
article/page publish capability can set article/page content to published or
scheduled. A future date supplied with `published` becomes `scheduled`.

No worker is required: public queries treat a scheduled item as published once
its `published_at` is at or before the database current timestamp. It is never
visible before then. Editors choose the status and publish date on each content
form; API v1 accepts `published_at` with the same rules.
