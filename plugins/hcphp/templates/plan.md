# Refactoring: <area>

**Status:** draft | approved <date> by <who> | in progress | done
**Base:** <branch> at `<sha>`
**Measured:** <date>, `census.sh` and `drift.sh` output below
**Playbook:** <move-then-drain | drain behind facades | DI track | ...>, chosen because <census fact>

## Goal

<Two or three sentences: what the area looks like when this plan is done, in the terms of
reference/target-shape.md — which services become facades, which managers appear, what the
ratchets hold. Behaviour does not change; say so.>

## Verified current state

Facts from the census, quoted, not recounted:

```
<the "## facts" block of census.sh for this application>
```

What the census cannot say, read by hand — each with `path:line` and a LIVE / DEAD verdict:

- <`application/lib/X/Service/RatingService.php:120` — `find()` translates page/limit itself — LIVE, 14 callers [grep]>

## Security gate

`drift.sh` over this application, and what it means for this plan:

| Advisory | Status here | Blocks |
|---|---|---|
| <ADV-2026-001> | <fixed / defective / read by hand> | <slices that touch that area, or "none"> |

A defective advisory in an area a slice touches blocks that slice until `/hcphp:port` lands it.
Other findings: <none | "run /hcphp:port --scan first">.

## Slices

One pull request each, in order. Each names its recipe (`reference/slice-recipes.md`), its
risk class and the verification it owes. `/hcphp:refactor --implement` ticks a slice when its
pull request is open, with the link.

- [ ] **1. <verb-first title>** — recipe <S0>, <safe> · verify: <suite, mutation> · blocked by: <none>
- [ ] **2. <verb-first title>** — recipe <S1>, <risky> · verify: <suite, mutation, sweep of /rating/list/, /rating/export/> · blocked by: <1>

## Non-goals

What this plan deliberately does not do, and where it lives instead. Every deferral names a
criterion you can grep for, checked against today's census to be able to become true:

- <Migrating legacy callers onto the new managers — facades stay; criterion: none, by design>
- <Dropping magic access from Rating — criterion: `grep -rn '\->total\b' application` returns nothing>

## Risks

| Risk | Mitigation |
|---|---|
| <a template reads a moved method by magic property> | <audit grep `->found\b` before slice 3; sweep the pages> |

## Rollback

Every slice is one pull request and reverts on its own. The cheapest rollback point is
<the commit after slice N, when the legacy classes still exist but nothing uses them>.
No slice changes the schema beyond <none | the index in slice N, undone by `DROP INDEX …`>.

## Second opinion

<Offered once: <agents> chosen / declined. What came back, tagged `[agreed]`, `[changed the plan]`
or `[not taken: why]`.>

## Decisions

- <question> — <answer as given> — `[answered]` / `[assumed]`
