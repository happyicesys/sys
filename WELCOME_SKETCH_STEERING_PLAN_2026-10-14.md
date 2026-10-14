# Welcome sketch steering: feedback into the next drawing — proposal, 2026-10-14

**Status: a one-off "Instructions for the next drawing" box is BUILT (2026-10-14, Brian chose it) —
`regenerate` takes `instructions`, stored per album entry, appended to the prompt; the page pre-fills it from the
last Draw. Not built: step 0, per-product persisted notes (A), redraw-from-this (C).** Question (Brian): when the OpenAI drawing keeps coming back
wrong, can we give feedback or a prompt for the next generation, instead of re-rolling blind?

**Short answer: yes.** The endpoint we already call takes a free-text prompt (we send a fixed one)
and up to 16 input images (we send the photo + 3 fixed style sheets). Both are ours to vary per
product, and per draw. No new provider and no new endpoint are needed for the recommended design.

---

## 1. What exists today

| Piece | File |
|---|---|
| Page section (album, Draw N, star, Use, delete, upload) | `resources/js/Pages/Product/WelcomeSketch.vue` (inside `Pages/Product/Edit.vue`) |
| JSON controller | `app/Http/Controllers/ProductWelcomeSketchController.php` — routes `routes/web.php:805-817` (`/products/{id}/welcome-sketch[/regenerate\|/versions/{v}/select\|shortlist]`) |
| Orchestration (only writer) | `app/Services/Products/WelcomeSketch/ProductWelcomeSketchService.php` — `regenerate()` :132, `queue()` :390, `generate()` :410, `styleReferences()` :648, `pageData()` :672 |
| Queue job (one try, `low`) | `app/Jobs/GenerateProductWelcomeSketch.php` |
| OpenAI call | `app/Services/Products/WelcomeSketch/OpenAiSketchGenerator.php` behind interface `SketchGenerator.php` |
| Config | `config/smart_freezer.php` → `welcome_sketch` (model `gpt-image-1`, quality `medium`, 512 px) |
| Tables | `product_welcome_sketches` (live row, one per product; `version_id`, `selected_by`) and `product_welcome_sketch_versions` (album) — migrations `2026_10_08_160000_…`, `2026_10_12_200000_…` |
| Tests | `tests/Feature/ProductWelcomeSketchTest.php` (uses a `FakeSketchGenerator`) |

Flow of "Draw 3 new": `regenerate()` creates 3 `pending` versions → 3 jobs → each job claims its row
(`pending → generating`), downloads the **current** product photo, and calls
`POST /v1/images/edits` with:

- `image[]` = product photo, then the seed sketches `U-11`, `CC-01`, `U-89` (`STYLE_REFERENCES`);
- `prompt` = the fixed `OpenAiSketchGenerator::PROMPT` + "What the product is (for reference only…): {name}";
- `background=transparent`, `size=1024x1024`, `quality=medium`, `n=1`.

The result is normalised to ≤512 px WebP and lands in the album. Nothing about the product, the
person or earlier drawings changes what is sent: every draw is the same request, so the only lever
today is re-rolling.

"Use" (`select()`) copies the version onto the live row and calls `nudgeFreezers()`
(`NudgeFreezerMenu`, 90 s delay, debounced) — that is the "about two minutes" on the page. Nothing in
this proposal touches that path.

## 2. Why the ice-ball drawings drift — partly our own prompt

Looking at the three style references we send with **every** product:

- **`U-89` has a bite taken out of the top and a brand "M" logo printed on the stick.** The model is
  told to copy "exactly the same art style as the other images", and it copies content along with
  style. The bite marks and stray logo marks in the album are very likely `U-89` leaking in.
- **The text rule contradicts itself for a wrapped bar.** The prompt says "draw the unwrapped bar…
  leave the wrapper out" *and* "copy ONLY words clearly printed on what you draw". "ICE BALL" /
  "RED GRAPE" are printed on the *wrapper* in the photo, so the model sometimes "draws" the wrapper's
  words onto the bar, sometimes not — that is the text drift.
- No `input_fidelity` is sent, so the photo is followed loosely (stick sometimes dropped).

So part of the fix is cheap and global, independent of any feedback UI (step 0 below).

## 3. What `gpt-image-1` actually supports (as far as I know — verify before building)

Confident:
- `POST /v1/images/edits` takes **multiple input images** (`image[]`, up to 16 for the GPT image
  models) plus a text `prompt` (long — thousands of characters are fine). That is the conditioning
  channel: everything "steering" can be expressed as prompt text and/or extra reference images.
- `mask` exists (an alpha-masked region of the first image to repaint). Useful for "fix just the
  stick", but needs a drawing tool in the UI — out of scope here.
- **No `seed` parameter** and no way to say "same as last time but…". Each call is independent.
- `/v1/images/variations` is **DALL·E 2 only** — not usable with `gpt-image-1`.
- `n` returns several images from one call (we send `n=1` and fan out jobs instead; fine as is).

Fairly confident, check the current docs before relying on it:
- `input_fidelity: "high"` on edits (added mid-2025 for `gpt-image-1`) — keeps details of the
  input images (shapes, logos, text) closer. Costs more input tokens. Helpful for "same shape and
  colours", and makes a passed-in previous drawing a much stronger anchor.
- The **Responses API** `image_generation` tool supports multi-turn editing ("now remove the bite")
  via `previous_response_id` / passing the earlier image. That is the only "refer to a previous
  generation" mechanism OpenAI has; the Images API has none. We can get the same effect on the
  Images API by simply sending the earlier drawing back as an input image.
- Newer image models may exist by now (`gpt-image-1.5` or later); `WELCOME_SKETCH_MODEL` already
  makes the model swappable. I have not verified what is current — worth one look at the docs.

Not possible: training/fine-tuning on our approved set, or a hard guarantee of exact text. Text
rendering is good but not perfect; "spell it exactly ICE BALL" will be right most of the time, not
always. That is why the album + human Use step stays.

## 4. Options

### A. Per-product "drawing notes" (free text, persisted, appended to every draw)
A textarea on the section — e.g. *"Text on the bar: exactly ICE BALL, nothing else. No bite. Always
the wooden stick."* Saved on the product's sketch settings and appended to the prompt as a
**higher-priority** block ("Instructions for this product override the general rules above").

- + Smallest change, fixes the reported case, survives photo changes and the hourly auto-sweep.
- + Persisted, so the next person (or the automatic redraw) gets it too.
- − Free text; people will write contradictory notes. Mitigated by showing which notes each drawing
  was made with.

### B. Per-sketch feedback ("more like this" / "not this, because…")
Each album tile gets 👍 / 👎 + an optional reason. On the next draw: 👎 reasons go into the prompt
as "Avoid: …"; 👍 drawings go in as extra reference images.

- + Matches how people already use the album (they star).
- − 👎 *images* are useless as input — the model cannot be shown "not this"; only the text reason
  helps. So 👎 collapses into option A's notes with extra UI.
- − Feeding 👍 images as references makes drift converge, but also copies their flaws.
- The **star already is** the 👍 signal; no new field needed for it.

### C. "Redraw from this one" (variation of a specific album drawing)
A button on a tile: sends **that drawing** as the first image (with the product photo second, for
truth) and a short prompt "Keep this drawing; change only: {what you type}". With
`input_fidelity: high` the result stays very close.

- + The most direct answer to "this one is nearly right, just remove the bite".
- + Fits the existing version row (`parent_version_id`), shows lineage in the album.
- − Repeated "redraw from" on a redraw degrades slightly (copy of a copy). Fine in practice for 1–3
  hops; the photo as second image anchors it.

### D. Locked constraints template (structured fields, not free text)
Per product: *Exact text* (string or "none"), *Must include* (stick / cone sleeve / cup / baking
paper), *Must not* (bite, wrapper, logo, extra objects). Rendered into fixed prompt sentences.

- + Deterministic wording, no contradictions, easy to audit, defaults could come from product data.
- − Only covers the drift we anticipated; the next weird failure still needs free text.
- − More form to build and keep in sync with the prompt.

## 5. Recommendation

**Step 0 (global fix, do first) + A (notes) + C (redraw from this one).** Skip B as a separate
feature (stars already exist; reasons go in notes). Fold the most useful parts of D into A as
suggestion chips rather than a schema.

Rationale: step 0 removes the cause of most of what the screenshot shows (bite + logo from `U-89`,
wrapper text). A gives the persistent "always do this for this product", which also guides the
automatic redraw on a new photo. C gives the one-off "this is close, fix one thing". Both are
pure prompt/input changes to a call we already make.

### Step 0 — fix the shared request
- Replace `U-89` in `STYLE_REFERENCES` with a style sheet that has no bite and no logo (or crop/clean
  `U-89`), and add to the prompt: *"The other images show the ART STYLE only — do not copy their
  shape, bite marks, logos or decorations."*
- Rewrite the text rule: *"Text comes only from the food itself (e.g. printed on a cone sleeve or a
  cup). Words printed on a wrapper, box or packet you are leaving out are NOT drawn."*
- Try `input_fidelity: "high"` behind config (`WELCOME_SKETCH_INPUT_FIDELITY`), compare cost/quality
  on 5 products before turning it on.

### A — Drawing notes
- **Migration** `2026_10_xx_add_drawing_notes_to_product_welcome_sketches`:
  ```php
  Schema::table('product_welcome_sketches', function (Blueprint $t) {
      $t->text('drawing_notes')->nullable()->after('version_id');
      $t->unsignedBigInteger('drawing_notes_by')->nullable()->after('drawing_notes');
      $t->timestamp('drawing_notes_at')->nullable()->after('drawing_notes_by');
  });
  Schema::table('product_welcome_sketch_versions', function (Blueprint $t) {
      $t->text('prompt_notes')->nullable()->after('reason');      // notes this drawing was made with (snapshot)
  });
  ```
  The live row is one-per-product already, so it is the natural home. **Caveat:** that row is
  created only when something goes live, so `drawing_notes` needs `firstOrCreate` with a null
  drawing — check `hasDrawing()` / `menuUrl()` treat a notes-only row as "no drawing" (they key on
  `url`, so they should, but test it). Alternative: a column on `products` — simpler, but couples a
  freezer-only concern to the catalogue table; I'd keep it on the sketch row.
- **Service**: `ProductWelcomeSketchService::saveNotes(Product, ?string, ?int $userId)`;
  `queue()` snapshots notes into the version's `prompt_notes` at request time (so a later edit of the
  notes never changes what an in-flight draw "was made with"); `generate()` passes them on.
- **Interface**: extend `SketchGenerator::generate()` with a request object instead of more
  positional args:
  `generate(SketchRequest $req)` — `photo`, `productName`, `styleReferences`, `notes`,
  `?baseDrawing`, `?change`. Update `OpenAiSketchGenerator`, the two test fakes
  (`ProductWelcomeSketchTest`, `HappyHourVendingTest`).
- **Prompt assembly** in `OpenAiSketchGenerator`: `PROMPT` + `"\nThis product's instructions (follow
  these over the general rules above):\n{notes}"` + product name line. Cap notes at ~1000 chars.
- **Controller/route**: `PUT /products/{id}/welcome-sketch/notes` → `saveNotes`, permission
  `update products` (same as Draw). `pageData()` returns `notes`, `notes_by`, `notes_at`, and each
  version's `prompt_notes`.
- **UI** (`WelcomeSketch.vue`): a "Drawing notes" textarea above Draw with Save, and chips that
  insert common lines ("No bite", "Keep the stick", "No text", "Text exactly: …"). Each album tile
  shows a small "notes" icon whose tooltip is the snapshot it was drawn with.
- **Audit**: `user_logs` via `UserLogger` (who changed the notes), consistent with the rest of
  Product → Edit.

### C — Redraw from this one
- **Migration** (same file): `product_welcome_sketch_versions.parent_version_id` (nullable,
  unsignedBigInteger) and `change_request` (text, nullable).
- **Service**: `redrawFrom(Product, int $versionId, string $change, ?int $userId, int $count = 1)` —
  parent must be `ready` and have a file; same in-flight cap and claim rules as `regenerate()`;
  `queue()` gains `parent_version_id` / `change_request`.
- **Generation**: image order = **parent drawing first**, product photo second, style refs dropped
  (the parent already carries the style). Prompt: *"Image 1 is our drawing. Keep it exactly — same
  style, pose, colours and outline — and change only: {change}. Image 2 is the real product, for
  reference. {product notes}. Transparent background."* Send `input_fidelity: high` for this mode.
- **Route**: `POST /products/{id}/welcome-sketch/versions/{versionId}/redraw` `{change, count}`.
- **UI**: a "Redraw from this" action on each ready tile → small inline box "What should change?"
  → Draw 1/2. Tile caption shows "from #123: remove the bite".
- **Automation**: unchanged. The hourly sweep and photo-save redraws keep drawing from the photo
  (with notes); redraw-from is always a person's action and lands in the album, never live, same as
  Draw today.

### Tests (extend `tests/Feature/ProductWelcomeSketchTest.php`)
- notes are saved, audited, snapshotted on the version at queue time, and reach the generator;
- editing notes after queueing does not change the in-flight version's snapshot;
- redraw-from sends the parent drawing as the first image and the change text; refuses a
  failed/pending parent; respects `MAX_IN_FLIGHT`;
- a notes-only live row is not served on `/menu` (`welcome_sketch` stays null).

Rough size: step 0 is an hour plus a few test draws; A + C about a day including UI and tests.

## 6. Cost note

Every draw is a paid call (`quality=medium`, 1024², multiple input images). `input_fidelity: high`
and more input images both raise the input-token cost per call. Steering should cut total spend,
because today the only fix is "Draw 4 more and hope" — but measure: compare the per-call cost on the
OpenAI usage page before and after enabling high fidelity.

## 7. Decisions needed from Brian

1. **Change the shared style set?** Swapping/cleaning `U-89` changes every future drawing for every
   product (existing album and live drawings are untouched). Recommended yes.
2. **Wrapper text rule:** should a bar whose name is only on the wrapper ever show that name? The
   current prompt says no text; the screenshot suggests some people may want "ICE BALL" on it. Pick a
   default; notes can override per product.
3. **Notes apply to automatic redraws too?** Recommended yes (that is the point of persisting them).
4. **Who may edit notes** — anyone with `update products` (same as Draw), or admins only?
5. **`input_fidelity: high`** — OK to pay more per call for closer results? Decide after a 5-product
   comparison.
6. Model: stay on `gpt-image-1`, or test a newer image model if one is available now (config-only).
