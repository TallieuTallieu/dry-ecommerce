# AGENTS.md — dry-ecommerce

E-commerce package for DRY applications (`Tnt\Ecommerce\`). Provides cart,
order, customer, fulfillment, discount, stock and tax building blocks on top of
oak's container, dry's ORM and dry-dbi's query/table builders.

## Repository conventions

- Main branch: master
- Remote: `git@github.com:TallieuTallieu/dry-ecommerce.git` (GitHub, not Bitbucket)
- Branch naming: `<type>/sc-<id>--<slug>`, e.g. `chore/sc-11167--dry-ecommerce-fix-composer-manifest-and-oak-3`
- Commits: Conventional Commits, one focused commit per ticket
- Never force-push. Never push without being asked.

> [!important] Never set a story's state by hand
> Shortcut state is driven **automatically by the branch**. Check out the
> branch matching the ticket *before* committing, and the story follows along.
> Pushing and merging the pull request is the user's job, not an agent's —
> commit on the right branch and stop there.

> [!important] `master`, not `main`
> The house CI template this package adopts (copied from dry-dbi) keys its
> workflows off `master` — `ci.yml` on `pull_request: branches: [master]` and
> `release.yml` on `push: branches: [master]`. Renaming would break both.

## Issue tracker

Work is tracked in **Shortcut** (workspace `tallieu--tallieu`), not in Obsidian
TaskNotes.

| | |
|---|---|
| Epic | **11166** — `dry-ecommerce` |
| Story title prefix | `dry-ecommerce:` |
| Workflow | DRY — `500000052` |
| Default state for new stories | Planned — `500001509` |
| Team / group | webdev — `5f05c15d-7e01-4911-80dc-2f6094ee7f1f` |

Use the `short` CLI (see the `dry-skills:shortcut` skill). It reads
`SHORTCUT_API_TOKEN`, `SHORTCUT_URL_SLUG` and `SHORTCUT_MENTION_NAME` from the
environment. The CLI has no epic-update command; use the REST API directly for
epic descriptions and story links.

Story dependencies are modelled as Shortcut story links with the `blocks` verb.

## Current state

> [!warning] Mid-revamp
> This package is being revamped under epic 11166 to work with the modern stack.
> Much of the tooling described as the target below **does not exist yet** — it
> arrives with `sc-11168`. Check before assuming a command works.

As of the start of the revamp: 2,205 LOC, no Docker, no PHPStan, no Prettier, no
tests, no CI, no `require-dev`. `composer.json` requires `oak: ^1.0 | ^1.1` and
omits two hard runtime dependencies (`tallieutallieu/dry`,
`tallieutallieu/dry-dbi`), so the package is not installable into a current
project.

## Target stack

| Dependency | Constraint |
|---|---|
| PHP | `^8.2` (CI runs 8.4) |
| `tallieutallieu/oak` | `^3.0` |
| `tallieutallieu/dry` | v4 |
| `tallieutallieu/dry-dbi` | `^3` |
| `tallieutallieu/dry-accounts` | `^3` — supported pairing, not a hard dependency |

## Tooling (target — arrives with sc-11168)

Copied from **dry-dbi**, which is the house template for GitHub-hosted packages:

- `make docker` / `make docker-exec` — `dry-docker#php8.4` + MySQL + Adminer
- `make test` — Pest (`tests/Unit`, `tests/Feature`)
- `make phpstan` — PHPStan level 9
- `make yarn-format` — Prettier with `@prettier/plugin-php`
- `make sync-docs` — rsync `docs/` to an Obsidian vault
- `.github/workflows/ci.yml` — tests, PHPStan, Prettier, `composer audit`
- `.github/workflows/release.yml` — auto-tag, changelog, GitHub Release

Run commands through Docker once it exists, as in the sibling packages.

## Related packages

| Package | Relationship |
|---|---|
| `dry-accounts` | First-class pairing. `Customer.user` is a nullable FK to its user model. |
| `dry-mollie` | **Stranded.** Pinned to `dry-ecommerce: ^1.2.1`, `oak: ^1.1.15`, `php: ^7.4\|^8.0`. Only real `PaymentInterface` implementation — until ported, no 3.0 shop can take payment. |
| `dry-sendcloud` | Already on the modern line. No dependency on this package; relevant prior art for fulfillment. |
| `dry-dbi` | Source of the tooling template, and of `BaseRepository` / `QueryBuilder` / `TableBuilder`. |
