# Tag Trees

Tags nest through `parent_id` to any depth. This page is about working with that
tree directly on the `Tag` model.

## Trees are not categories — know which one you want

The package has two shapes, and they do not overlap:

| | Depth | Addressed by | Used through |
|---|---|---|---|
| **Categories** | exactly two — a category and its values | category slug + value slug | `HasTags`: `setTag()`, `addTag()`, `tagsIn()`, `setTagAs()`, the `*In` scopes |
| **Trees** | any | a `/`-joined slug path | `Tag` itself: `path()`, `resolvePath()`, `ancestors()`, `descendants()`, `moveTo()` |

The category API is deliberately flat: `setTag('status', 'published')` means
"the value `published` inside the root category `status`", and it looks exactly
one level down. Nothing on this page changes that, and nesting a category's
values deeper does **not** make them visible to `tagsIn()` or `withTagIn()`.

Use a tree when the hierarchy itself is the point — a per-project *Topics*
outline, a subject taxonomy, a location hierarchy. Use a category when a model
holds one value out of a flat list.

## Addressing a tag by path

```php
$vue = Tag::resolvePath('topics/web/frontend/vue');

$vue->path();   // 'topics/web/frontend/vue'
```

`path()` joins the slugs from the root down to the tag. `resolvePath()` walks
back the other way, starting at a **root** tag: a path that begins part-way down
the tree (`web/frontend`) resolves to `null`, not to the matching subtree.

Segments are slugged on the way in, so `Topics/Web` and `topics/web` address the
same tag, and leading or trailing slashes are ignored. A missing segment returns
`null` rather than throwing. It costs one query per segment.

### Paths are tenant-scoped

```php
Tag::resolvePath('topics/web', 'tenant-a');   // that tenant's tag
Tag::resolvePath('topics/web');               // only tags with no tenant
```

A null `$tenantId` means "tags with no tenant", the same way the rest of the
package treats an absent tenant. It is **not** a wildcard: it will not find a
tenant's tags.

## Walking up and down

```php
$vue->ancestors();      // Collection: topics, web, frontend  (root first)
$topics->descendants(); // Collection: the whole subtree, nearest level first
```

Both run a recursive CTE for the ids and one query to hydrate them — two queries
whatever the depth or the size of the subtree, not one per level.

`ancestors()` is empty for a root tag; `descendants()` is empty for a leaf.
`parent()` and `children()` are still there as ordinary Eloquent relations when
one level is all you need.

## Re-parenting

```php
$frontend->moveTo($ops);    // move under another tag
$web->moveTo(null);         // promote to a root
```

`moveTo()` refuses three moves before touching the database, because none of
them is recoverable afterwards:

- **Into its own subtree** — `CircularTagHierarchyException`. A cycle is a
  perfectly valid set of rows, so the database will store it; every ancestor walk
  over it afterwards runs forever.
- **Onto a slug the destination already holds**, for the same tenant —
  `DuplicateTagSlugException`. The unique index would reject this anyway, but as
  a `QueryException` that also aborts the surrounding PostgreSQL transaction.
- **Under a parent belonging to another tenant** — `CrossTenantTagMoveException`.
  A subtree belongs to one tenant, and every other write path propagates the
  parent's tenant to its children. Grafting across tenants is not a recoverable
  state: the subtree walks filter on `parent_id` alone, so the destination
  tenant's `descendants()` starts returning the foreign tag, and `resolvePath()`
  — which requires every segment to share one tenant — can address it from
  neither side. "No tenant" is its own space here too, so a tenant-less tag and a
  tenant-less parent match, and a tenant-less tag may not be grafted under a
  tenant's parent.

All three leave the tag exactly where it was. Everything beneath the moved tag travels
with it — the subtree hangs off `parent_id`, so nothing else has to be rewritten,
and the paths of every descendant change accordingly.

## What the schema gives you

`tags` is unique on `(slug, parent_id, tenant_id)`, built over `COALESCE`
expressions so that "no parent" and "no tenant" are compared as values rather
than as NULLs. Two roots with the same slug, or two same-slug children of one
parent, are rejected — including for global (tenant-less) tags, which the
original column-list index let through. `parent_id` is indexed.

Deleting a tag cascades to its children, at every depth. `safeDelete()` refuses
when the tag or anything beneath it is still assigned to a model;
`forceDelete()` does not.
