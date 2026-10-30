# Grab pages: before and after the revamp (2026-10-30)

What each page under **Delivery Platform** did before the Grab revamp, and what it does now. Everything below is
live on prod as of 2026-10-30 15:05. Source: `GRAB_REVAMP_PLAN_2026-10-29.md` (§6, §9 "Step 1 as built", "Step 3a as
built"), checked against the code and prod.

## The menu

| Before | Now |
|---|---|
| Grab Platform ID | **Grab Stores** (new; replaces Grab Platform ID + Machines) |
| Machines | **Grab Errors** (new) |
| Grab Orders | **Grab Promotions** (new) |
| Grab Product Mapping | Grab Orders |
| Grab Campaign | Grab Product Mapping |
| | Grab Campaign |

Grab Platform ID and Machines left the menu on 2026-10-30, once Grab Stores could do everything they did. Their old
addresses still open, so bookmarks keep working.

---

## 1. Grab Stores (`/grab-stores`) — new; replaces Grab Platform ID + Machines

**Before.** A Grab store was split across three pages:
- **Grab Platform ID** typed the merchantID.
- **Machines** showed the binding and held Pause / Unbind.
- **Grab Product Mapping → Bind VM** did the binding itself.

The merchantID was stored twice (the ref number, plus a text copy on the binding), and renaming one did not update
the other. "Active" meant two different things on the two pages. Nothing recorded who bound or unbound a store or
why, and a pause overwrote the record, so no past pause was ever kept.

**Now.** One row per Grab store:
- **Columns:** merchantID, machine (opens the Operation Dashboard), Site, since when, template, status with its
  reason, the machine ID Grab sent on the last order (red when it is not the bound machine), orders in 30 days, last
  order, a count of Grab errors, and "n promotions on Grab".
- **Totals chips** at the top filter the list.
- **Actions:** Add store (checks the merchantID with Grab first, with a "Save without Grab check" tick), **Edit**
  (merchantID, operator, remarks), Put on machine, Move to another machine, Pause (reason, optional "until"), Resume,
  Remove from machine (with a reason), Switch off / on, History.
- **On Grab now:** the store's menu exactly as Grab pulls it, one line per slot: product, Grab sub-category, Grab
  price, qty in the machine, units held by open orders, qty offered on Grab, and for anything Grab cannot sell, why
  (store paused, switched off in the template, slot fault, no capacity, sold out, back at the next stock sync). It
  also says whether Grab has already pulled this version. Read only.
- **Edit rules:** a new merchantID is checked with Grab first (tick to skip). The operator decides which Grab account
  serves the store, so it can only change while the store is on no machine.
- **History:** every machine the store has been on, who bound and unbound it and why, every pause, switch-off and
  Grab error.
- A store still open on a second machine shows **"Close that one"**. The 4-C7CDNZDXKEBJPE / 2658 leftover is closed
  from here, by ops.
- HIPL staff see every operator's stores; other users see only their own.

## 2. Grab Errors (`/grab-errors`) — new

**Before.** A failed call to Grab, or a Grab callback we refused, existed only in the server log. Nobody saw it
unless they went looking. An order Grab took but never sent to us was invisible.

**Now.** Every failed exchange with Grab is kept for 30 days, in both directions:
- **Our calls to Grab** that were refused or got no answer.
- **Grab's calls to us** that we answered with an error (a crash included).
- **Missed orders:** each night (03:30) yesterday's orders on Grab are compared with ours, and any order we never
  received is listed once.

The page lists them; each Grab Stores row shows its count, and the store's History shows the details. Client users
see only their own stores' errors. Customer details and secrets are never stored.

## 3. Grab Promotions (`/grab-promotions`) — new, read only

**Before.** mark1 only knew what it had sent to Grab. It never asked Grab what was actually live. When an end
request to Grab failed, mark1 still marked the campaign ended. Grab kept it running, and the next campaign for that
store was refused ("same item in two campaigns"). That is the error ops saw when they moved a machine or re-ran a
deal.

**Now.** Every 10 minutes mark1 reads each live store's ongoing and upcoming campaigns from Grab and shows them:
- **Each row:** store and machine, the deal in words ("20% off, up to $3"), the items or category by name, from →
  to, and Live now / Upcoming / Gone.
- **Who made it:** Made in mark1 / **Leftover** (red: mark1 thinks it ended, Grab still runs it) / Grab merchant app
  / Grab team / Not found in mark1.
- **Chips** filter by state and by who made it; an "Ended" view lists what has gone from Grab (kept 30 days).
- **"Could not be read" box:** stores whose last read failed, each with "Read again" (a read, never a change).
- **It never creates, changes or ends anything on Grab.** "End it" and "End all leftovers" are the next step (3b).

First run on prod (14:45): 70 stores read, **no campaigns live or upcoming anywhere, so no leftovers.** One
store, GFSBPOS-800-085 on machine 2002, is a sandbox merchantID on the live account. Grab answers "Merchant not
found" for it, so it stays in the "could not be read" box.

## 4. Grab Platform ID (`/delivery-platform-ref-numbers`) — off the menu; use Grab Stores

The address still opens. Its Edit now goes through the same writer as Grab Stores, so a change is recorded and the
operator cannot change while the store is on a machine.

| | Before | Now |
|---|---|---|
| Create / edit | Saved the ref number only | Goes through the same writer as Grab Stores, so it is logged |
| merchantID typed with or without "4-" | Accepted as a second store (`C7CCRTEJJYUJR6` exists this way) | Refused as a duplicate |
| Switch a ref **Inactive** | Stopped orders, but Grab kept listing the store until the 10-minute re-pause | Grab is paused at once |
| Switch it back **Active** | Grab was never reopened | Grab is reopened at once |
| Inactive store still bound to a machine | Its menu, orders and stock kept going in some paths (GoWell 2870 sold on a closed listing) | Counts as off everywhere: menu, orders, stock, re-pause |

## 5. Machines (`/delivery-product-mapping-vends`) — off the menu; use Grab Stores

The address still opens. Its channel view is now **On Grab now** on Grab Stores. Its per-channel edits are not
carried over on purpose: reserved % / reserved qty is set on no channel on prod, and a channel pause set there was
overwritten by the next stock sync.

| | Before | Now |
|---|---|---|
| Pause | Overwrote the binding; no record kept | Recorded as a store event (who, why); Grab paused |
| Unbind | Closed the binding but left the store **open on Grab** | Closes it with who and why, and pauses the store on Grab |
| Status filter | Meant "has an open binding", not the store's own status | Unchanged on this page; Grab Stores uses the store's status only |

## 6. Grab Product Mapping (`/delivery-product-mappings`)

| | Before | Now |
|---|---|---|
| Bind VM | Allowed a store on two machines at once, or two stores on one machine | Refused; use Move on Grab Stores |
| Unbind | Store stayed open on Grab | Store paused on Grab; a later bind reopens it |
| Pause All | Also paused stores that had already moved on to another template | Pauses only stores open on this template |
| Delete a template | Deleted every binding row, past ones included, and ended no Grab campaign | Refused while a machine still uses it; past periods are kept |
| Menu sent to Grab | Told Grab to re-pull on every change, ~2,250 times a day | Waits 3 minutes and asks only when the menu really changed |
| A product with no photo | Failed the whole store's menu | Sent without a photo |
| Price sent to Grab | Dollars × 100 as a decimal, so it could be off by a cent | Stored cents, exact |

Still the same: the template is copied from the machine mapping and matched by channel code. That is why Grab sells
the wrong product on stores whose machine mapping has changed. It is fixed by step 2 (Grab Menu & Prices), which
is not built yet.

## 7. Grab Campaign (`/delivery-platform-campaigns`)

| | Before | Now |
|---|---|---|
| Delete a campaign or an item | Removed it in mark1 only; Grab kept running it | Ends it on Grab first |
| Which store a campaign goes to | Found by machine code | Found by merchantID, the same as menu and pause |
| What is live on Grab | Not known | Shown on Grab Promotions |

Still the same: the campaign is set up per template, then per machine, then "Submit". The Edit page's Save still
writes to the wrong record (a product mapping with the same id). Both are replaced by step 3b / §8 (one promotion
per operator).

## 8. Grab Orders (`/delivery-platform-orders`)

The page itself is unchanged; the order flow behind it was fixed:

| | Before | Now |
|---|---|---|
| Grab re-sends an order | Could book it twice | Answered once; the repeat gets the same reply |
| Grab edits an order | Re-allocated blind | Kept for a person (`is_edited`), not re-allocated |
| Mark ready / cancel to Grab | Done inside Grab's 10-second callback | Done in the background, 3 tries |
| Stock held for open orders | Read from a counter that drifted (62 units stuck) | Counted from the open orders themselves |
| An order for the wrong store | Resolved by machine code first, so it could land on the wrong binding | Resolved by merchantID |
| An update for an order we never stored | Crashed (500), 96 times on machine 4754 in Oct | Answers 404 and is listed on Grab Errors |

Still to do (step 4): remove the broken "Save Order" and form Save buttons, items by product, and a "Needs
attention" view.

---

## Behind the pages (no screen of their own)

| Job | When | What it does |
|---|---|---|
| Pause re-assert | every 10 min | Grab reopens a paused store after 24 h; this closes it again. Calls are spaced, and a store Grab refuses is skipped for a day |
| `grab:resume-due-pauses` | every 5 min | Reopens a store when its "Pause until" time passes |
| `grab:mirror-campaigns` | every 10 min (:05, :15 …) | Fills Grab Promotions |
| `grab:missed-orders` | 03:30 | Grab's orders vs ours for yesterday → Grab Errors |
| `grab:prune-api-errors` | 03:45 | Drops Grab errors older than 30 days |
| Webhook check | every Grab call | Checks Grab's token. **Logs only**; refusing calls waits for a clean week and Brian's go |

## Not built yet

- **Step 2, Grab Menu & Prices:** the menu follows the machine mapping, by product, with prices from RP. This fixes
  the wrong-product bug.
- **Step 3b:** "End it" / "End all leftovers" on Grab Promotions, then one promotion per operator (§8).
- **Step 4, Grab Orders clean-up:** broken buttons removed, items by product, a "Needs attention" view, and a
  daily `grab:health` email.
- **Step 5:** batch stock updates, then removing templates and the old campaign tables.
