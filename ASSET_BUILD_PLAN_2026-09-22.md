# Move `public/build` out of git and into the deploy — plan (2026-09-22)

> **Status: PLAN ONLY. Nothing implemented, nothing changed on the server.**
> Three facts still have to be read off production before phase 1 can start
> (§6) — the plan is blocked on them, not on a decision.
>
> Written against mark1 HEAD `b20a026787`.

## 1. Why

Generated assets are tracked in git. Measured today:

| | |
|---|---|
| Files in `public/build` | 352 (5.9 MB) |
| Commits touching it | 1,683 |
| **Objects under `public/build` across all history** | **310,115 — 88% of the repo's 348,988** |
| Pack size | 825.7 MiB |
| Commits in Sept that are *only* an asset rebuild | 64 of 332 (**19%**) |

So 88% of everything git stores here is machine-generated output, and a fifth
of the commit volume is the ritual of committing it.

It also causes real incidents, not just noise. All three of these are bug
classes that only exist *because* the output is tracked:

- **2026-09-18 outage** — `git commit <path>` silently skipped untracked
  assets, shipping a half-built `public/build`.
- **Stale-tree confusion** — 304 of the 767 phantom changes on 2026-09-22 were
  `public/build`; they turned a 60-file drift into something that looked like
  the app had been deleted.
- **Source/bundle skew** — a `.vue` change pushed without its rebuild makes
  production serve stale JS, silently.

`.githooks/pre-push` currently guards all three. Those guards exist only
because the output is committed; this plan deletes the need for them.

## 2. What the build actually is

Measured locally (Node v20.19.6, npm 10.8.2):

- `npx vite build --outDir <elsewhere>` → **13 s**, 352 files, 5.9 MB.
- Building into a separate directory **does not touch** the working tree, so a
  side-build-then-swap is viable.
- **The output is content-deterministic**: a fresh build byte-matched all 352
  committed files. Only `manifest.json` differed — same 351 keys, same values,
  different key *order*. (That ordering jitter is why a routine asset commit
  shows an 8,558-line manifest diff.)

That last point is what makes this safe: the server will produce the same
bytes the Mac does, given the same source and lockfile.

## 3. The risk that decides the design

Deploy is **in place** — the 2026-09-19 incident (`deploy-gap-new-column`)
showed code serving before its migration, with ~8 s of user-visible errors.
That means the naive version of this change is dangerous:

> `rm -rf public/build && npm run build` on the server leaves **every page
> throwing "Vite manifest not found"** for the whole build. `app.blade.php`
> and `mail.blade.php` both call `@vite(...)`, which reads
> `public/build/manifest.json` on every render. A 13 s build on the Mac could
> be 60–120 s on a small droplet. That is a full outage per deploy.

So the design is fixed by this constraint:

**Build into a side directory; swap with a rename; never delete the live one
until the new one is complete.** A `mv` within the same filesystem is atomic,
so the switch is sub-millisecond and there is no window where the manifest is
absent. A failed build leaves production **completely untouched**.

## 4. Options considered

| | Approach | Removes bug class | Stops repo growth | Build load on prod | Verdict |
|---|---|---|---|---|---|
| **A** | Build on the server during deploy, side-build + atomic swap | yes | yes | yes | **recommended** |
| **B** | Build in GitHub Actions, deploy the artifact | yes | yes | no | fallback if the droplet cannot build |
| **C** | Keep committing, let a hook build automatically | no | no | no | rejected — automates the ritual instead of removing it |

**A** is recommended because it is the smallest number of moving parts: no CI
to introduce (there is no `.github/workflows` today), no artifact store, no
second push loop. **B** becomes the answer if §6 shows the server cannot build
— most likely on RAM.

## 5. Phases

Ordering matters more than anything else here. Phase 1 is deliberately a
**no-op in effect**: the deploy starts building assets while they are still
committed, so the build overwrites them with identical bytes. That proves the
build works on the server with zero risk, because the committed copy is still
the safety net. Only once that is boring do we stop committing them.

### Phase 1 — teach the deploy to build (assets still committed, zero risk)

Add to the Forge deploy script, after `composer install`, before
`php artisan optimize`:

```bash
# Install JS deps only when the lockfile moved — npm ci is the slow part.
if [ ! -d node_modules ] || ! cmp -s package-lock.json .npm-lock-deployed; then
    npm ci --no-audit --no-fund
    cp package-lock.json .npm-lock-deployed
fi

# Side-build.  If this fails the deploy aborts and the LIVE assets are
# untouched, because nothing has been moved yet.
rm -rf public/build.new
npx vite build --outDir public/build.new --emptyOutDir

# Refuse to swap in a build that did not finish.
test -f public/build.new/manifest.json || { echo "no manifest — aborting"; exit 1; }

# Atomic swap.  Keep the previous build for instant rollback.
rm -rf public/build.prev
[ -d public/build ] && mv public/build public/build.prev
mv public/build.new public/build
```

Verify: deploy, confirm the site renders, confirm `public/build` still has 352
files and `git status` on the server shows **no** change to `public/build`
(identical bytes — proof the server build matches).

### Phase 2 — stop tracking it

Only after phase 1 has survived several real deploys:

```bash
git rm -r --cached public/build
printf '/public/build\n' >> .gitignore
```

`git rm --cached` leaves the files on disk, so the running server keeps
serving until the next deploy rebuilds them. **This must not be deployed
before phase 1 is live**, or the deploy deletes `public/build` and nothing
regenerates it.

### Phase 3 — retire the guards that are now dead weight

`.githooks/pre-push` checks 2, 3 and 4 (`public/build` uncommitted, broken
manifest, source-without-bundle) all become meaningless and should be removed;
check 1 (stale checkout) and check 5 (advisory) stay. Update the
`## Push is a deploy` section of `CLAUDE.md` in the same commit.

### Not in scope: rewriting history

The 825 MiB is **sunk**. Purging `public/build` from history would reclaim most
of it but rewrites every commit SHA — and this estate records SHAs everywhere:
`CLAUDE.md`, every dated plan doc, the audit's per-finding "mark FIXED `<sha>`"
workflow, and the memory files. All of them would silently start pointing at
nothing. **Recommendation: leave history alone.** The repo simply stops
growing, which is the part that matters.

## 6. Blocked on three production facts

I could not read these — production SSH is gated. Run this and paste the
output (it is strictly read-only):

```bash
ssh forge@167.172.70.186 'echo "RAM:"; free -m | grep -E "Mem|Swap"; echo "cores: $(nproc)"; echo "disk:"; df -h /home/forge | tail -1; for c in node npm npx; do printf "%-5s " $c; command -v $c >/dev/null && $c --version || echo MISSING; done; cd /home/forge/sys.happyice.com.sg && echo "node_modules: $(test -d node_modules && echo yes || echo no)"'
```

What each answer changes:

1. **RAM** — the blocker. Rollup bundling a 618 KB `app.js` typically wants
   1–2 GB. On a 1 GB droplet with no swap the build OOMs, and the answer
   becomes option **B** (or adding swap first).
2. **Node present, and which version** — vite 3 + laravel-vite-plugin 0.5 are
   old; they want Node 14–18 and can warn or break on very new Node. If the
   server's Node differs from the Mac's v20.19.6, pin one before trusting §2's
   determinism result.
3. **Disk** — `node_modules` is a few hundred MB, plus `build.prev`.

## 7. Rollback

- **Phase 1**: remove the added lines from the deploy script. Nothing else
  changed; assets are still in git.
- **Phase 2**: `git revert` the tracking commit. The assets return to git, and
  the next deploy overwrites them with an identical build.
- **A bad build reaching production**: `mv public/build public/build.bad &&
  mv public/build.prev public/build` — instant, no deploy needed. This is why
  the script keeps `build.prev`.

## 8. What this is worth

- Deletes three bug classes outright rather than guarding them.
- Removes ~19% of commit volume — the asset-rebuild ritual, and the
  8,500-line manifest diffs that hide real changes in review.
- Stops the repo growing at 88%-generated-objects.
- Costs 1–3 min of deploy time (mitigated: `npm ci` only when the lockfile
  moves) and one new failure mode — a build that fails on the server — which
  the side-build contains by design.
