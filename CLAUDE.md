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

Pest + Orchestra Testbench, on **real PostgreSQL** — the DDEV `db` service, in a
database of its own named `testing`, created by a `post-start` hook in
`.ddev/config.yaml`. Every value is overridable via `TAXON_TEST_DB_*` env vars;
see `tests/TestCase.php::getEnvironmentSetUp`. This matches the reference package.

The suite uses `RefreshDatabase`, so migrations run **once** and each test is
wrapped in a transaction that rolls back. Two consequences worth knowing:

- Tests share one database. A test that assumes a pristine schema, or that commits,
  will leak into its neighbours. Nothing currently does; keep it that way.
- Sequences are **not** rolled back, so auto-increment ids keep climbing across the
  run. Never assert on a literal id value.

The package migrations are registered with the migrator from `TestCase` (the
service provider only *publishes* them), alongside the fixture consumer tables in
`tests/Fixtures/database/migrations/`. Registering the path — rather than calling
`loadMigrationsFrom()` — is what keeps Testbench from rebuilding the schema per test.

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

Namespace `RobinsonRyan\Taxon\`, PSR-4 from `src/`. It is small: **18 files and
~2,255 lines** in `src/`, plus one config and three migrations. Auto-discovered via
`extra.laravel.providers → TaxonServiceProvider`, which only merges config and
registers the `taxon-config` / `taxon-migrations` publish tags — no bindings, no
commands, no routes.

## The data model — one table, one tree

Everything is **two tables and a self-referencing `parent_id`**:

- **`tags`** — `id`, `name`, `slug`, `parent_id` (nullable, cascade-delete),
  `tenant_id`, `assignable`, `single_select`, `meta` (json). Unique on
  `(slug, COALESCE(parent_id::text,''), COALESCE(tenant_id,''))`; `parent_id`
  indexed.
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
`roots`, `categories`, `assignable`, `slug`, `childrenOf`, `inCategory`. The
arbitrary-depth half lives here too: `path()`, `resolvePath()`, `ancestors()`,
`descendants()`, `moveTo()`.

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
`UnguardedTransitionException` (`transitionTo()` on a definition that declares no
guard) · `ImmutableTagDefinitionException` · `CircularTagHierarchyException`
(`moveTo()` into the tag's own subtree) · `CrossTenantTagMoveException`
(`moveTo()` under another tenant's parent) · `TagDepthExceededException` (a tree
walk passed `taxon.max_tree_depth` edges — in practice, a cycle in `parent_id`)
· `DuplicateTagSlugException` (a write would collide on `(slug, parent, tenant)`).

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
exactly how the bug above shipped. The whole suite now runs on Postgres, so that
blindness is gone by default. `tests/Feature/PostgresTaggableIdTypeTest.php`
remains separate because it exercises the migrations under *non-default*
`id_type` / `taggable_id_type` combinations: it needs to drop and recreate `tags`
and `taggables`, which would destroy the schema `RefreshDatabase` migrated once
for everyone else. It therefore works in the `db` database on its own connection,
never in `testing`.

**A self-referencing foreign key needs its primary key declared explicitly.**
`$table->uuid('id')->primary()` compiles the primary key into a command appended
*after* the `foreign('parent_id')` command, so Postgres rejects the FK — its
target has no unique constraint yet. `Support\SchemaSupport::uuidPrimary()` calls
`$table->primary('id')` as its own statement for exactly this reason, and both
uuid key columns go through it. SQLite hides this too (it folds foreign keys into
the create).

**In uuid7 mode PostgreSQL generates the keys and PHP never does.** As of 0.5.0
`ConfiguresIdentifiers` sets `$incrementing = true` and `$keyType = 'string'` and
stops there — no `creating` hook, no `Str::uuid7()`. `$incrementing = true` beside
a UUID is not a mistake: in Eloquent it means "the database assigns the key on
insert", and it is what makes the INSERT compile with `returning "id"` so the
generated key comes back. Set it false and `create()` hands you a null key. The
column default is therefore load-bearing, which is what
`SchemaSupport::uuidPrimary()` and the `add_default_uuidv7_to_taxon_ids` migration
exist for — and `attach()` writes the pivot through the **query builder**, never
through a model, so no model hook could have covered `taggables.id` anyway.
`tests/Feature/Uuid7DatabaseGeneratedKeysTest.php` is the guard; its decisive case
drops the column default and asserts the insert then fails.

**A tag attribute assigned on an unsaved model is held, not written.** The write
lands in the `taggables` pivot and needs the model's key, which does not exist
during `fill()` — so a factory `definition()` naming a tag attribute used to die
on a null `taggable_id`. `HasTags::setAttribute()` now stashes the value in
`$pendingTagAttributes` and `bootHasTags()`'s `saved` listener flushes it. On a
model that already exists the write still goes through immediately, without a
`save()`; that is long-standing behaviour and deliberately unchanged.

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

**The transition contract is inherited API, and it is loud.** As of 0.4.0
`TagDefinition` supplies `transitions()` (null = no state machine declared),
`default()`, `canTransition()` (reads the map) and `availableTransitions()`
(walks the map, filtered through `canTransition()`), plus `guardsTransitions()`,
`normalizeState()`, `declaredStates()` and `declaresState()`. The map is the
vocabulary: with no `default()` the *first* state must be one the map mentions
(it used to fall through to `isValidValue()`, which says yes to everything when
`values()` is empty), and a database-backed definition's `values()` includes the
map's states whether or not tags exist for them. Map **keys** are matched on the
stored value first and `normalizeState()` second, so `'In Progress' => [...]` is
no longer a state nothing can leave. `HasTags::transitionTo()` no longer duck-types: a
definition declaring **neither** a map **nor** a `canTransition()` override gets
`UnguardedTransitionException` rather than the silent unguarded write it used to
get. `setTagAs()` is the unguarded write. PHP rejects a narrower parameter type
in an override, so a guard must take `string|BackedEnum|null $from` and narrow
inside the body — `tests/Fixtures/Definitions/StatusDefinition.php` is the worked
example and `ClearanceDefinition.php` is the code-only-guard one.

**Trees are `Tag`'s, categories are `HasTags`'.** `path()`, `resolvePath()`,
`ancestors()`, `descendants()` and `moveTo()` work at any depth (the two walks
use recursive CTEs — two queries each, whatever the depth). Both walks are
**bounded** at `config('taxon.max_tree_depth')` edges (64) and raise
`TagDepthExceededException` past it: a recursive CTE over an adjacency list spins
forever on a cycle, and killing the client does not stop the query. `moveTo()`
runs in a transaction that locks the tag, the destination and the destination's
ancestors in primary-key order *before* re-checking for a cycle — the check used
to be check-then-write, and two opposing concurrent moves could commit one
between them. The category API is
still hard-wired to two levels and nothing here changed that: nesting a
category's values deeper does not make them visible to `tagsIn()` or
`withTagIn()`. `docs/trees.md` states the boundary.

**Unscoped means `NULL`, and NULL breaks unique indexes.** The scope migration
drops the plain unique constraint and issues raw SQL building
`taggables_unique_tag_model_scope_tenant` over
`COALESCE(scope_type,''), COALESCE(scope_id,''), COALESCE(tenant_id,'')`,
precisely because `NULL != NULL` would let duplicate unscoped rows through on
Postgres/MySQL/SQLite alike. `tags_unique_slug_parent_tenant` had the identical
hole and enforced nothing for root or global tags until 0.4.0's third migration
rebuilt it the same way (with `parent_id` cast to text first, since `''` is
neither a valid bigint nor a valid uuid). That migration de-duplicates before
creating the index, because consumers may already hold rows it forbids.

Matching that, `applyScopeFilter()` treats "no
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

**Writes slug, reads match every spelling.** The string API stores
`Str::slug()` of what it is given, so `'Web Development'` and
`'web-development'` are the same tag — but `TagDefinition::valueTag()` stores an
enum's backing value *verbatim*, underscores and all, because
`Enum::from($tag->slug)` has to round-trip. Reads therefore cannot slug
unconditionally: `HasTags::tagSlugCandidates()` expands a value into the value as
given, its slug, and that slug re-underscored, and every read path matches with
`whereIn`/`in_array` [T: src/HasTags.php, tests/Feature/UnderscoreSlugMatchingTest.php].
Write paths (`resolveOrCreateTags()`, `resolveOrCreateValueTag()`,
`valueTag()`) keep minting one canonical slug apiece — widening those would need
a data migration. Direct tags and categories share the root namespace
(`parent_id IS NULL`), so `$post->tag('status')` can collide with a `status`
category.

## Docs

`docs/` holds hand-written user-facing docs — `installation.md`,
`basic-usage.md`, `categories.md`, `trees.md`, `tag-definitions.md`,
`tenant-scoping.md`, `magic-attributes.md`, `api-reference.md`.
`build-spec.md` (110 KB) is the original build specification and is historical. Deferred work is in `QUEUE.md`
(currently: widening to Pest 5 / PHPUnit 13 / PHP 8.4+, which consuming apps are
waiting on).

## Quick reference

- **DDEV**: `ddev start`, `ddev ssh`
- **Gate**: `ddev composer quality`
- **Tests**: `ddev composer test`
- **Style fix**: `ddev composer lint`
- **Rector fix**: `ddev composer refactor`
