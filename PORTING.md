# Porting a change from HCPHP into an application built on it

**Audience: a developer or a coding agent, working in the *application*, not in this
repository.** Link someone here and this is the whole brief.

Read it end to end before touching anything. The steps are ordered because each one can stop
the port, and the expensive mistake is applying a change that looked mechanical.

---

## What you are actually doing

HCPHP is a reference implementation. An application built on it holds its **own copy** of
`application/core` and `application/lib`, and owns that copy. There is no package, no version,
and **no shared ancestor you can diff against** — the copies have been edited independently for
years.

So this is **not** "copy the file across". Every core class differs between copies: class names
differ (`DatabaseSQL` in some, `SqlDatabase` in others), variable names differ inside the same
method, brace style differs, and some copies carry extra guards. A file-level copy silently
reverts whatever that application changed.

**You are porting a *change*, not a file.** Read what the HCPHP commit does and why, then make
the equivalent change in code that may look quite different.

---

## Step 0 — Get both trees and name the change

```shell
git clone <the application>            # work in a branch, never on the default branch
git clone https://github.com/matasarei/HCPHP.git   # the reference
```

In HCPHP, identify the commits. Each fix is one commit and the message states the defect, the
consequence and the reasoning:

```shell
git -C HCPHP log --oneline
git -C HCPHP show <sha>                # read the whole message, not just the diff
```

If the message does not explain why, stop and ask. A change you cannot explain is a change you
cannot verify, and you are about to apply it to something in production.

## Step 1 — Is the defect actually present here?

Never assume. Find the same code in the application and read it.

```shell
grep -rn "<a distinctive fragment from the HCPHP before-state>" application/core application/lib
```

Three outcomes:

- **Present, same shape** — port it.
- **Present, different shape** — port the *intent*. Expect to rewrite the patch.
- **Absent** — this application never had the defect, or already fixed it. Record that and move
  on. Do not add the fix "for consistency"; you would be changing working code for no reason.

## Step 2 — Audit the callers before any behaviour change

**This is the step that prevents the expensive mistake, and it is the one most often skipped.**

A security fix often narrows behaviour. Anything relying on the old, looser behaviour breaks
silently — it does not error, it just quietly does less.

Worked example. HCPHP once chose its SQL operator by inspecting the *value*: a condition value
containing `%` was promoted from `=` to `LIKE`. That is an authentication bypass when the value
is a cookie. The fix makes `LIKE` an explicit opt-in — but it also turns every wildcard search
that relied on the old behaviour into an exact match.

So before applying it, every call site passing a `%` had to be found and read:

```shell
grep -rn "getRecords\|getRecord\|deleteRecords\|updateRecords" application --include="*.php" \
  | grep -v vendor
```

In one application four call sites looked like dependents. Reading them showed the values were
consumed by hand-written SQL that built its own `LIKE`, so nothing depended on the implicit
behaviour. **That conclusion required reading them.** Had one genuinely depended on it, the port
would need a `Like::contains()` at that call site in the same commit.

Write down what you found. It belongs in the pull request.

## Step 3 — Check the runtime, not the manifest

`composer.json` states an intention. The container states the truth, and they disagree.

```shell
grep -rih "^FROM php" docker/ 2>/dev/null
python3 -c "import json;print(json.load(open('composer.json'))['require']['php'])"
```

One application declares `>=7.4` and runs **PHP 7.1**. That mattered: the cookie-hardening
change uses the options-array form of `setcookie()`, which is **PHP 7.3+**. On 7.1 it does not
error — it returns null with a warning and **sets no cookie at all**, which breaks login while
appearing to be a security improvement.

`php -l` will not catch this. It is a runtime signature change, not a parse error. When the
runtime is older than the change requires, either write a guarded fallback or say the port is
blocked on the upgrade. Do not ship it and hope.

## Step 4 — Apply it, matching the local code

- **Match the local names.** The class may be `SqlDatabase`; the loop variables may be `$name`
  and `$value` rather than `$cname` and `$cvalue`. Read the surrounding method and follow it.
- **Preserve line endings.** Many of these files are CRLF, and there is usually no
  `.gitattributes`. Check with `file <path>` first; an editor that rewrites them turns a
  three-line fix into a whole-file diff nobody can review.
- **Keep the comments that explain why.** They are the reason the next person will not undo it.
- **One commit per fix**, with the HCPHP reasoning restated. The reviewer does not have this
  context and will not go looking for it.

## Step 5 — Prove it, in this application's own code

A passing HCPHP suite proves nothing about this application. Four things, in order:

**1. Lint at the runtime version from step 3** — not HCPHP's, not your host's.

```shell
docker run --rm -v "$PWD":/app -w /app php:<their-version>-cli \
  sh -c "find application -name '*.php' -not -path '*/vendor/*' -print0 | xargs -0 -n1 php -l"
```

**2. Baseline the test suite before and after.** Most of these applications have failing tests
for environmental reasons. The number alone is meaningless; the *delta* is what matters.

```shell
<test command> 2>&1 | tail -3     # before applying
<test command> 2>&1 | tail -3     # after — the counts must be identical
```

One application ran 2107 tests with 522 errors and 10 failures, before and after, unchanged.
That is what "no regression" looks like when the suite is already red.

**3. Execute this application's own classes.** Write a throwaway script that loads *its*
`application/core` and exercises the defect and the fix directly. Inference is not verification —
the copies differ, and the only thing that settles whether the fix works here is running it here.

**4. Drive the real thing** where the change is reachable over HTTP or the CLI: log in, submit
the form, run the command. A unit test would not have caught the PHP 7.1 cookie problem; a login
attempt would.

## Step 6 — Ship it

Open a pull request. In the body, state:

- **what the defect was and what it allowed** — concretely, not "a security issue";
- **the caller audit from step 2**, and what it found;
- **how it was verified**, including the baseline numbers;
- **anything deliberately excluded**, and why;
- **what must happen after merging** — for an auth fix that usually means *rotating credentials*,
  because closing a hole does not invalidate what leaked through it.

Do not batch unrelated fixes into one pull request. One reviewable change per pull request, one
commit per fix inside it.

---

## Traps that have actually bitten

Each of these cost real time on a real port.

| Trap | What happens |
|---|---|
| Copying a file wholesale | Silently reverts that application's own changes |
| Trusting `composer.json` for the PHP version | The container runs something older; runtime-only breakage |
| Skipping the caller audit | Searches quietly become exact-match; nothing errors |
| Linting at HCPHP's version | Misses syntax the target rejects, or flags what it accepts |
| Editing a CRLF file with a tool that normalises | Whole-file diff, unreviewable |
| Reading the suite's absolute pass count | Meaningless when it is already red; compare the delta |
| Verifying by reasoning | The copies differ; run it in the target |
| Porting a fix you cannot test here | If it cannot be verified, say so rather than guess |

## When not to port

Say so plainly instead:

- the defect is not present in this application;
- the code has diverged so far that the change would need a rewrite — that is a separate piece
  of work, scoped and reviewed on its own;
- the runtime cannot support it (step 3) and the upgrade is the real fix;
- there is no way to verify it here, and the change touches something that matters.

A port that cannot be verified should not be merged just because it is available upstream.
