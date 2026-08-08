# Taxon

A hierarchical tagging system for Laravel: any Eloquent model gains `tag()` /
`setTag()` / `getTagAs()` through one trait, and tags form a parent→child tree so a
root tag can act as a category ("Status") whose children are its values ("pending",
"complete"). Adds optional multi-tenant partitioning, per-relationship scoping, and
class-based tag definitions that validate values and guard state transitions.

Composer name: `robinsonryan/taxon` — a **library**, not an application.

## Conventions

@import ./constitution.md
@import ./imports/package-conventions.md
@import ./imports/package-quality-gate.md
@import ./imports/testing-conventions.md
@import ./imports/php-conventions.md
@import ./imports/git-conventions.md

> Linking the `laravel-package` stack also drops the inherited Laravel app
> conventions into `.claude/imports/` — `authorization-conventions.md`,
> `frontend-conventions.md`, `pwa-conventions.md`, `ddev-worktrees.md`. They are
> **deliberately not imported above**: they describe Inertia `can` maps, app-shaped
> Vite wiring, and nested app worktrees, none of which exist in a package. Read
> them if a question genuinely calls for one; do not load them by default.
>
> Taxon has **no frontend half** — no `resources/`, no build step, no npm
> dependencies. `frontend-conventions.md` stays unimported.

> `.claude/` is a set of **harness symlinks** and is gitignored — a fresh clone has
> none of them and the `@import`s above resolve to nothing. If a convention file is
> missing, restore the link rather than guessing:
> `~/workspace/harness/link.sh project laravel-package $(pwd)`

## The gate

`ddev composer quality` — `lint:check` → `analyze` → `refactor:check` → `test`.
Verify-only: it never rewrites files. Fix with `ddev composer lint` /
`ddev composer refactor` and re-stage.

PHPStan runs at **level 8 with zero `ignoreErrors`**, over `src` *and*
`tests/Fixtures`, pinned to `phpVersion: 80200` (the declared floor, not the
container's PHP 8.5). Keep it that way — the blanket
`missingType.iterableValue` suppression was deliberately removed and the
underlying findings fixed.

`.githooks/pre-commit` runs **the whole gate, tests included** (~12 s measured
here). It is path-aware, so a docs-only commit skips it. Never bypass with
`--no-verify`; `PACKAGE_SKIP_GATE=1` is a human emergency valve and **agents must
never set it**.

That hook file is a **copy** of the harness's canonical one. Do not edit it here
— edit `$CLAUDE_HARNESS_DIR/core/stacks/laravel-package/hooks/pre-commit` and
re-run that directory's `install.sh`.

`harness package-check` sweeps every first-party package: the gate, a
`--prefer-lowest` run proving the declared version floor really resolves,
outdated and vulnerability scans, and in-constraint updates behind a re-run of
the gate. It never tags a release. Run it before any app re-resolves its
packages.

Full definition: `imports/package-quality-gate.md`. Skill: `/package-quality`.

## Testing

Pest + Orchestra Testbench. **Unlike the reference package, this suite runs on
SQLite `:memory:`, not Postgres** — see `tests/TestCase.php::getEnvironmentSetUp`.
It can: Taxon generates UUIDv7 in PHP (`Str::uuid7()` in `ConfiguresIdentifiers`)
rather than leaning on a `uuidv7()` column default, so no Postgres feature is
required. DDEV still runs a Postgres 18 `db` service; the suite does not touch it.

That is a **coverage hole, not a design choice you should copy** — see the
`uuidMorphs` gotcha below for a bug class SQLite's loose typing hides.

```bash
ddev composer test
ddev exec vendor/bin/pest --filter=SomeTest
```

There is no `ddev artisan` and no `ddev pest` here — those are app commands.
`ddev test` and `ddev quality` exist as host shortcuts.

## Releases

**Never tag.** Automation may update, gate, commit and push a branch, then report
"ready to tag" with a suggested version. Ryan cuts every tag. A version number is
a claim about behavior that a green gate cannot substantiate.

Behavior changes land in `CHANGELOG.md` in the commit that makes them.

The package was **renumbered down to `0.x`** to signal an unsettled API: old
`vN.m.p` became `v0.N.<ordinal>`. Under Composer `^0.4.0` means `>=0.4.0 <0.5.0`,
so **every minor may break** — that is the point. It goes `1.0.0` when the
consuming apps ship publicly.

## Reference package

`~/dev/php/packages/robinsonryan/hey-you/` is the reference implementation —
service provider shape, Testbench setup, tool configs, table prefixing. Read it
before inventing a variant.

---

# What is actually in here

Namespace `RobinsonRyan\Taxon\`, PSR-4 from `src/`. It is small: **13 files and
~1,490 lines** in `src/`, plus one config and two migrations. Auto-discovered via
`extra.laravel.providers → TaxonServiceProvider`, which only merges config and
registers the `taxon-config` / `taxon-migrations` publish tags — no bindings, no
commands, no routes.

## The data model — one table, one tree

Everything is **two tables and a self-referencing `parent_id`**:

- **`tags`** — `id`, `name`, `slug`, `parent_id` (nullable, cascade-delete),
  `tenant_id`, `assignable`, `single_select`, `meta` (json). Unique on
  `(slug, parent_id, tenant_id)`.
- **`taggables`** — the polymorphic pivot: `tag_id`, `taggable_type`,
  `taggable_id`, `scope_type`, `scope_id`, `tenant_id`.

There is no separate "category" table or type column. A tag's **role is positional**:

| Position | Means | Convention |
|---|---|---|
| `parent_id IS NULL`, no children | a **direct tag** — `$post->tag('featured')` | `assignable = true` |
| `parent_id IS NULL`, has children | a **category** — the "Status" in `setTag('status', …)` | `assignable = false` |
| `parent_id` set | a **value** inside that category | `assignable = true` |

`isRoot()` / `isCategory()` / `isLeaf()` on `Tag` just read that shape.
`isCategory()` fires a `children()->exists()` query every call — it is not cached.

### Table names are configurable, and the defaults are dangerous

`config('taxon.tables.tags')` and `.taggables` default to bare **`tags`** and
**`taggables`** — no package prefix. Any host app with its own `tags` table
collides head-on. The migrations, the `Tag`/`Taggable` `getTable()` overrides, and
every raw `DB::table()` / pivot query in `HasTags` all read that config, so
renaming works — but the default is a footgun, and it differs from the
prefixing convention in the reference package.

## Entry points

**`HasTags` trait** (`src/HasTags.php`, 746 lines — the bulk of the package). Put
it on any model. It is organized in banner-commented sections; the public surface:

- *Direct tags*: `tag()`, `untag()`, `retag()`, `detachAllTags()`,
  `hasTag()`, `hasAnyTag()`, `hasAllTags()`
- *Category tags*: `setTag()` (replaces within the category), `addTag()` (adds),
  `addTags()`, `removeTag()`, `removeTagsIn()`, `tagsIn()`, `getTagIn()`,
  `getTagValueIn()`, `hasTagIn()`, `hasAnyTagIn()`, `hasAllTagsIn()`
- *Definition-backed*: `setTagAs()`, `addTagAs()`, `getTagAs()`, `hasTagAs()`,
  `transitionTo()`
- *Query scopes*: `withTag`, `withAnyTag`, `withAllTags`, `withoutTag`,
  `withTagIn`, `withAnyTagIn`, `withoutTagIn` — all take an optional trailing
  `?Scope`

**`Tag` model** (`src/Models/Tag.php`) — `createCategory()`, `createValue()`,
`addChild()`, `addChildren()`, `syncChildren()`, `safeDelete()` /
`forceDelete()`, `taggablesCount()` / `totalTaggablesCount()`, plus scopes
`roots`, `categories`, `assignable`, `slug`, `childrenOf`, `inCategory`.

**`TagDefinition`** (`src/TagDefinition.php`) — abstract base for a class-backed
category. Subclass sets `$slug`, `$name`, `$singleSelect`, `$global`. Two flavors:

- **Enum-backed** — override `enum()`. Values come from the enum's cases and are
  **immutable**: `addValue()` / `removeValue()` throw
  `ImmutableTagDefinitionException`. `getTagAs()` hydrates back into the enum.
- **Database-backed** — override nothing. Values are the category tag's children,
  mutable at runtime via `addValue()` / `removeValue()` /
  `firstOrCreateValue()`. `getTagAs()` returns the raw **slug string**.

`valuesMutable()` decides between them by reflection: it is `false` if `enum()`
returns non-null **or** if the subclass overrode `values()`.

**`Scope` contract + `CanScopeTags` trait** (`src/Contracts/`, `src/Concerns/`) —
lets one model carry *different* tags per related scope (e.g. a user's role tags
per organization). The scope is written into the pivot's `scope_type`/`scope_id`.
Implement `Scope` **and** use `CanScopeTags`; the trait's
`initializeCanScopeTags()` throws `LogicException` if you use it without the
`implements`.

**`ConfiguresIdentifiers` trait** — flips a model between auto-increment and
UUIDv7 keys off `config('taxon.id_type')`. Used by `Tag` and `Taggable`; test
fixture models use it too.

## Exceptions (`src/Exceptions/`)

`TagNotFoundException` (category missing and `auto_create` is off) ·
`TagInUseException` (`safeDelete()` on a tag with pivot rows, checked recursively
through children) · `InvalidTagValueException` · `InvalidTransitionException` ·
`ImmutableTagDefinitionException`.

## Gotchas — the things that will actually bite

**`HasTags` overrides `getAttribute()` and `setAttribute()`.** Declare a
`protected array $tagAttributes` on the model and those keys stop being real
columns and start reading/writing tags. Indexed entries (`'status'`) map to a
string category; associative entries (`'priority' => SomeDefinition::class`) map
to a definition. This runs on **every** attribute access on the model, and a name
collision with a real column silently shadows the column. See
`tests/Fixtures/Models/TestModelWithAttributes.php`.

**`Tag` itself uses `HasTags`.** Tags can tag tags — that is the supported RBAC
pattern (`$role->tag(['users.create'])`), not an accident. It also means `Tag`
inherits the `getAttribute` override.

**`taggable_id` follows `taxon.taggable_id_type`, not `taxon.id_type`.**
`id_type` governs Taxon's *own* primary keys (`tags.id`, `taggables.id`);
`taggable_id` holds the *host application's* keys, which need not be the same
type. `taggable_id_type` is `null` by default, meaning "follow `id_type`" — set
it only for a mixed app (uuid7 tags over integer-keyed models, or the reverse).
Until 2026-08-08 the migration called `uuidMorphs('taggable')` unconditionally,
which made the shipped default (`incrementing`) emit a real `uuid` column on
Postgres and reject integer keys on insert.

**Anything about column *types* must be tested on Postgres, not SQLite.** SQLite
compiles `uuid`, `bigint` and `varchar` down to the same loose affinity, which is
exactly how the bug above shipped. `tests/Feature/PostgresTaggableIdTypeTest.php`
runs the published migrations against the DDEV Postgres service and reads
`information_schema` directly; it is the only test in the suite that can see a
column type at all.

**A self-referencing foreign key needs its primary key declared explicitly.**
`$table->uuid('id')->primary()` compiles the primary key into a command appended
*after* the `foreign('parent_id')` command, so Postgres rejects the FK — its
target has no unique constraint yet. The `tags` migration therefore calls
`$table->primary('id')` as its own statement. SQLite hides this too (it folds
foreign keys into the create).

**`config('taxon.tag_model')` is honored in exactly one place** —
`HasTags::tags()`, when building the `morphToMany`. Every other write path
(`resolveOrCreateTags()`, `resolveCategoryTag()`, `resolveOrCreateValueTag()`,
all of `TagDefinition`) calls `Tag::` statically. Swapping the model gives you a
custom class on reads and a plain `Tag` on writes. Do not advertise `tag_model`
as a working extension point without fixing that first.

**`assignable` and `single_select` are stored but never enforced.** Nothing
checks `assignable` before attaching, and `single_select` is written to the row
and read by nobody — single-vs-multi is purely the caller's choice of `setTag()`
(replaces) vs `addTag()` (appends). `config('taxon.morph_map')` is dead: it
appears in the config file and is referenced nowhere in `src/`.

**The transition system is duck-typed; the base class supplies none of it.**
`TagDefinition` has no `canTransition()`, `transitions()`, `default()`, or
`availableTransitions()`. `HasTags::transitionTo()` does a `method_exists($definition,
'canTransition')` check and **silently falls through to a plain `setTagAs()` if the
method is absent** — a typo in the method name turns every guard off with no error.
The richer conventions (`transitions()` map, `availableTransitions()`) exist only
in `tests/Fixtures/Definitions/StatusDefinition.php`; treat it as the worked
example, not as inherited API.

**Unscoped means `NULL`, and NULL breaks unique indexes.** The scope migration
drops the plain unique constraint and issues raw SQL building
`taggables_unique_tag_model_scope_tenant` over
`COALESCE(scope_type,''), COALESCE(scope_id,''), COALESCE(tenant_id,'')`,
precisely because `NULL != NULL` would let duplicate unscoped rows through on
Postgres/MySQL/SQLite alike. Matching that, `applyScopeFilter()` treats "no
scope" as `WHERE scope_type IS NULL AND scope_id IS NULL` — a scoped and an
unscoped tag are genuinely different rows, and querying without a scope will not
find scoped ones. `applyScopeFilterToHas()` deliberately does *not* add the null
check (a null scope there means "don't filter").

**Tenancy is opt-in and off by default.** `taxon.tenant.enabled => false` makes
`TagDefinition::currentTenantId()` return null for everything. When enabled it
resolves via `callback` first, then `resolver => 'auth'` reading
`auth()->user()->{auth_attribute}` — and `auth_attribute` defaults to
**`account_id`** in the shipped config (the code's own fallback if the key is
absent is `tenant_id`; the config file wins). A definition with
`public static bool $global = true` bypasses tenancy entirely.

**Slugging is `Str::slug()` everywhere**, applied on both write and lookup, so
`'Web Development'` and `'web-development'` are the same tag. Direct tags and
categories share the root namespace (`parent_id IS NULL`), so
`$post->tag('status')` can collide with a `status` category.

## Docs

`docs/` holds hand-written user-facing docs — `installation.md`,
`basic-usage.md`, `categories.md`, `tag-definitions.md`, `tenant-scoping.md`,
`magic-attributes.md`, `api-reference.md`. `build-spec.md` (110 KB) is the
original build specification and is historical. Deferred work is in `QUEUE.md`
(currently: widening to Pest 5 / PHPUnit 13 / PHP 8.4+, which consuming apps are
waiting on).

## Quick reference

- **DDEV**: `ddev start`, `ddev ssh`
- **Gate**: `ddev composer quality`
- **Tests**: `ddev composer test`
- **Style fix**: `ddev composer lint`
- **Rector fix**: `ddev composer refactor`
