# Written for a small model — how plans, slices and code are shaped so a ~30B model copes

These skills are meant to be run, and the code they produce maintained, by models in the 27–30B
range on a local machine as well as by large hosted ones. A small model reads instructions well,
follows a checklist well, and fails at holding many facts at once, at estimating, and at noticing
what is absent. Everything below follows from that.

## Facts are computed, never inferred

- Counts, sizes, the Container API, which advisories apply: **a script prints them**
  (`census.sh`, `drift.sh`). The model quotes the output; it does not count by reading.
- When the plan says "144 inline constructions", the run re-runs the census before the slice and
  **stops if the number disagrees**. A plan whose facts drifted is re-planned, not patched.
- Every claim in a plan or commit carries where it came from: `[census]`, `[grep]`, `[test]`,
  `[read: path:line]`, `[assumed]`.

## Every check is yes or no

- "Is the moved body token-identical?" — not "is the move faithful?".
- "Did the test go red under the mutation?" — not "is the test good?".
- "Is `slice-check.sh`'s last line `PASS`?" — not "is the slice healthy?".
- A check that needs judgement is broken into checks that do not, or it is a stop for a person.

## One slice per session, one recipe loaded

- The run (`/hcphp:refactor --implement`) loads `SKILL.md` and `run.md`, the plan's next
  unticked slice, and **that slice's recipe only**. Not the whole of `slice-recipes.md`, not the trap catalog in full — the sections the
  recipe cites.
- A slice is sized so the files it touches fit in context with room to spare: one class moved,
  one responsibility extracted, one family's consumers repointed. A slice that needs more is
  split in the plan, by the planning model, before it runs.
- The plan file is the memory. The run ticks a slice when it lands, and re-reads the plan from
  disk at the start of each slice rather than trusting what it remembers.

## Recipes are steps, not advice

- Numbered, imperative, one action each. "Create X holding the body of Y as it is" — not
  "consider extracting".
- Each recipe says what it must **not** do. Small models over-help; the boundary is explicit.
- When a step cannot be done as written, the instruction is to stop and say which step and why,
  never to find another way.

## Code that a small model can maintain afterwards

What the refactoring produces is judged by whether a 30B model, given one file and its test, can
change it correctly.

- **One reason to change per class**, named for it: `RatingExportBuilder`, not `RatingHelper`.
- **Small files**: a class a small model can read whole, with its test, in one go. The census
  lists classes over ~300 lines and methods over ~50 as candidates.
- **Every dependency in the constructor.** Reading the constructor tells the model everything the
  class can touch. No `getContainer()` inside, no `new` of a collaborator, no static singletons,
  no magic properties.
- **Explicit types** within the copy's floor PHP: parameter and return types, nullable spelled
  `?T`, `@return Entity[]` where the language cannot say it.
- **Docblocks say where things live and why they are the way they are**, not what the next line
  does: *"Facade kept for legacy callers; logic lives in RatingFinder"*.
- **The architecture is in tests.** A ratchet that fails with "new RatingService( in
  controllers/rating.php: ceiling 3, found 4 — take it from the container" teaches the rule at
  the moment it is broken. The failure message names the fix.
- **A bundle README** describing the bundle as it is now — its services, their ids, what each
  manager is for. One screen. Updated in the PR that changes the bundle.

## Prompts and outputs

- Short sections, tables over prose, the same headings in every file, so the model finds things
  by position.
- No instruction depends on remembering an earlier section; repeat the one line that matters.
- Outputs have a fixed shape (the plan template, the commit template, the `Verified:` block), so
  the model fills slots instead of composing.

## What stays with the large model or the person

- Choosing the playbook and cutting the plan into slices (`/hcphp:refactor`, `plan.md`).
- Any behaviour question: keep the quirk, or fix it.
- Reading a census result that does not fit any recipe.
- Approving the plan.
