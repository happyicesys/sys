# Operations

The live status of every machine, a short version of it, operations trends over time, and grouping of sites that stand together.

## Operations ▸ Dashboard

**What it is for**
The Operation Dashboard (Live Status) is the main screen for running machines day to day. One row per site (or per
machine), with its temperatures, stock, errors, sales, last and next refill job, connection status and payment devices.
From here you also pick machines and assign them to a driver's job.

**What you see when the page opens (before you press Search)**
- The filter box. Nothing is listed in the big table until you press **Search**.
- Two summary panels for all machines that match the current filters:
  - **Last 30 days — Total (Avg / VM)**: **Stock-in Value $**, **Sales $**, **VendEarning $** (sales minus location
    fees), **Loc Fees**, **# of Job done**, **% of VM, Sales L30D > LMth** (share of machines whose last 30 days beat
    last calendar month).
  - **Current — Avg**: **Stock Qty Bal** (average stock balance %), **Stock SKU Bal**, **% of VM, Avg Daily Sales L30D
    >= Avg/Day**, two **>Refillable $: # of VM** boxes (how many machines have more than 120 / 150 / 200 / 250 / 300 /
    350 / 400 / 450 of refillable value; the blue number in brackets is how many of those already have a job tomorrow),
    and **# of Job, next day**.
- **Needs attention**: the machines that need someone now, grouped by problem. Red groups are critical, amber are
  warnings:
  - **(1) Lost connectivity or electricity**, **(1B) Controller not responding**, **(1C) Temperature probe error**
  - temperature alerts **2A)** to **2E)** (cooling fan off, T1 or T2 above 0°C, above -8°C, did not reach -18°C, above
    -17°C and rising)
  - **APK reboot (connection issue)**, **Touch screen frozen**, **No sales for 48h**
  - **Freezer products not on the AI's list** (smart freezers only)
  - **Paid, not served** (only shown when there is one; a QR payment was taken but no machine confirmed the sale.
    **Show list** opens those payments.)
  Each group lists its machines as "machine - site" with a short reason. Click a count (**show entries**) or a machine
  line to show only those rows in the table below. **Refresh** checks again.
- Under the panel, the full rows of the flagged machines, most urgent first, each with small red/amber tags saying why.
  The temperature alerts (1)–(2E) are checked on normal vending machines only.

**Filters**
- Always shown: **Machine ID** (4 to 5 digits, use "," for several), **Site** (site name or ID), **Machine Prefix**,
  **Operator**, **Location Type**, **Machine with no Site** (**Include unbound?** lists machines not bound to any site too).
- Press **Show All Filters** for the rest, including **Channel ID**, **Serial Num**, **T1 >>**, **T2 >>**, **T1-T2 Delta >>**,
  **Channel Errors**, **Is Online?**, **Site Status**, **Machine Status** (Factory, Active, Not Active, Disposed, Sold),
  **Sensor Status**, **Is Door Open**, **Fan RPM**, **Last Visited Day >>**, **Balance Stock(%) <<**,
  **Remaining Channel(%) <<**, **Firmware Ver**, **APK Ver**, **Android Device Type**, **Card Terminal**, **Modem**,
  **LCD Monitor**, **SimCard Package**, **Avg/Day Sales (30d) <<**, **Is MQTT?**, **Is MQTT Active?**, **Account Manager**,
  **Coin Amount <<**, **Upcoming Job Driver**, **Upcoming Job Date**, **Ref Price Type**, **Machine Model**,
  **Refilling Routes**, **Preferred Day(s)**, **#Refill per Week**, **Delivery Platform**, **Setting Chart**,
  **Machine Contract**, **Product Mapping**, **Upcoming Mapping**, and **Site Grouping** (**Grouped? (show siblings
  together)**).
- ">>" means "higher than" and "<<" means "lower than" the number you type.
- The page size box (50, 100, 200, 500, All) sits next to "Showing x to y of z results". **Last loaded** shows when the
  data was read.
- Most filters need full dashboard access. Accounts with limited access see only a few (for example Machine ID,
  Machine Prefix, Channel ID, Channel Errors, Is Online?, Card Terminal).

**Buttons above the table**
- **Search**: loads the table with the filters.
- **Show / Hide All Filters**.
- **Operations**: shows the tick-box column and the job tools (see below).
- **Show Alert/Error**: lists only machines with at least one red badge or a Needs attention reason. The red number on
  the button is how many. A bar above the table then counts each reason (for example **HTTP offline**, **Channel
  error**, **Error rate ≥3%**, **Drop sensor off**, **Modem offline**, **Bill acceptor off**, **Coin acceptor off**,
  **Coin float low**, **No card sale**, **No QR sale**, **No cash sale**); click a reason to narrow the list, or
  **Show all machines** to go back.
- **Show Map Markers**: all listed sites on a map, numbered in table order.
- **Hide header / Show header** (the tab on top of the table): folds the column titles away to see more rows.

**Columns (one row per site)**
Every column title with a small arrow can be clicked to sort. A grey row means the machine or site is not active.
- **Machine ID** block: the machine ID (opens the machine's settings), **Setting Chart** (red when the machine is not on
  the latest version; hover for the versions), **Machine Prefix**, **Product Mapping** (opens the mapping; a **New**
  badge means a new mapping is planned; "Mapped date · age" shows how long the current one has run), the site
  (opens the site), CMS / delivery platform / postcode / **RP** (reference price tier) pills, a map pin and **GPS**
  (opens Google Maps), **Campaign** chips (name, expiry, pieces sold last 7 days) and **Happy Hour** chips
  (discount, name, dates, pieces sold, **L7d Non-HH %**, a daily Happy Hour sales graph; **⚠ Not running** or
  **⚠ Ignored: UI Setting** when it is not running on that machine).
- **Temperature**: **T1: Machine** (top of the freezer) and **T2: Evaporator**. Red above -12°C, blue -12 to -18°C,
  green below -18°C. **Updated** (refreshed about every 3 minutes), **T1 lowest L48hr** (red above -18°C, blue -21 to
  -18°C, green below -21°C), **ΔT1-T2** (normal 1.5 to 3.5°C; red at 4 or more, or 0 or less), **Fan RPM** (green
  running, red 0, N/A when the fan signal is disabled). Click a temperature to open its history. Machine Health alert
  tags such as (1), (2A) show here. At the bottom: **SIM Card**, the network the machine reports with its signal
  (1-2 red, 3 yellow, 4-5 green), and the **more** button that opens the full machine record.
- **Inventory Status**: one line per channel: "#channel, required, balance/capacity (last stock in)".
  - Channel number red = the machine's price is not the same as the reference price.
  - Balance green above 2, blue at 1–2, red at 0.
  - A grey line = the product's quantity is capped for the next day; a red line = the product is marked not available
    in the warehouse.
  - Below: **Cost** (without GST/VAT), **Value** (with GST/VAT), **Full Load Value**.
  - Click the box to open **Channel Status** (see below).
- **Error**: **Uncleared Error(s)** (channel number, error code and time; errors that must be cleared on the machine's
  controller), **1d Rate / 2d Rate / 7d Rate** (red at 3% or more; the 1d rate opens today's transactions), and
  **# of Refund** 1d / 2d / 3d (refund requests submitted per day; red if higher than the day before, green if lower).
- **Stock**: **Balance Qty** % (red under 30%, blue 30–50%, green 50% and up), **Remaining Channel#** (sellable channels ÷
  all channels; an empty channel or one with an uncleared error does not count; hover for detail), and **Refill
  Planning**: **Refillable Value** and **Refillable Qty**.
- **Sales(qty)**: **Today**, **Y'day**, **Last7d**, **Last30d**; **Mthly Sales $** (**Current Mth**, **Last Mth**, **Last 2Mth**,
  **Last 3Mth**, with up/down arrows against the month before); **Avg Mthly Sales $**.
- **Last Job**: the last refill visit: date, **Stock In Value**, **Qty**, **Transaction (Qty)** since, and **Last 2 Job**
  under it. Badges show special visits (**Implement New Mapping**, **Return Stock**, **Melted Stock**). Click to open
  that job item.
- **Upcoming Job**: the next planned visit with **Picked Value** and **Qty**, then **# of Job Done L30d** and
  **Avg $ per Job**.
- **Refilling Routes**: route, **Preferred Day(s)**, **#Refill per Week**, and **Ops Note** (type and click away to save;
  the name and time of the last change show below it).
- **Lifetime Sales**: **Lifetime Sales**, **Accum VendEarning**, **Begin Dt**, **Contract End Date** (with auto-renewal
  icon), **Notice Period**, **Avg Sales/ Day**, **AvgDailySales L30d** (coloured against the lifetime daily average),
  **VM Binding History** (the machine now and the one it replaced), **Site Tag**.
- **Contract Type**: **Contract Type**, **Ext Subsidize**, **Loc Fees**, **L30d GrossEarning**, **L30d VendEarning**,
  **Loc Grading** (Placement / Access / Flexibility, A to C; hover the help icon for the meaning) and **Site Note**
  (**Cust Notes** box; you can @mention a user; saves when you click away).
- **Machine Status**: badges, green = good, red = problem, grey = no data or inactive machine:
  **HTTP Online/Offline**, **MQTT Online/Offline** (the live link used for QR payments and remote commands; grey
  when offline), smart freezer health badges, **SimCard Package**, **Remote Modem**, **Product Drop Sensor**
  (Enabled/Disabled), **Dispensing Door** (Open/Close), **Temp Limit**. Then three small trends:
  - **PWRON** 1d / 2d / 3d: how often the machine reconnected after dropping the line. Red if higher than the day
    before, green if lower.
  - **MQTT Offline (min)** 1d / 2d / 3d: minutes without the live link. Green under 10, amber 10–59, red 60+.
  - **Unsent Sales**: sales the machine has stored but not yet delivered, with the age of the oldest. Green 0, amber
    under 30 minutes, red 30 minutes or more. "–" means the machine's app is too old to report it.
- **Payment Device**: **Keypad QR Code**, **Modem**, **LCD Monitor**, **Bill Acceptor** and **Coin Acceptor**
  (Active/Inactive), **Coin Float** (red when low; click for the 14-day **Coin Float History**, with **Export CSV (14
  days)**), **Card Terminal** (company and terminal ID; **Auto refund** means that terminal model voids a failed
  single-item sale by itself), and touch screen / cashless share graphs.
  A red frame on Card Terminal, LCD Monitor (QR) or Coin Float means no sale of that kind in the last 48 hours; hover
  for details, click to open that machine's Sales Transactions.
- **Operator**: **Operator**, **Acc Manager**, **Location**, then **VMC Board**, **Firmware Rev**, **Android Board**,
  **APK Ver**, **ACB Rev**, **Screen**. Users who can change machine settings also see the newest picture of the
  machine's screen (taken automatically twice a day or by View Screen); click to enlarge.

**Channel Status pop-up** (click the Inventory Status box)
- Lists each channel: product, **Needed**, **Bal**, **Cap**, **Machine Price**, **Ref Price**, **Error**, last sale and
  **Last Out Of Stock Since**. Click a channel error to see its **Error List**.
- **Prices on machine** shows whether the machine has loaded its prices: **On machine** (green), **Waiting** (amber),
  **Not checked yet** (grey). **Send to machine again** asks the machine to reload prices (needs machine settings rights).
- **Edit Product** lets you change which product sits in a channel; press **Save** to store it.
- Admins and supervisors can open the **Dispense** column (arrow button in the last column title) on an active machine
  with a live link. **Dispense** drops one item from
  that channel for free. It really releases a product, so use it only for testing or to help a customer.
- Smart freezers open a planogram (basket) view instead, and smart chillers a layer view.

**How to find one machine quickly**
1. Type the machine ID in **Machine ID** (add a comma after a single ID for an exact match).
2. Press **Search** (or Enter).
Any machine ID link elsewhere in ConnectVend also opens this page on that machine.

**How to see only machines with problems**
1. Set the filters you want (for example your **Operator** or **Refilling Routes**).
2. Press **Show Alert/Error**.
3. Click a reason in the red bar to narrow the list. Press **Show all machines** to return.

**How to assign machines to a driver's job**
1. Search for the machines.
2. Press **Operations** to show the tick boxes.
3. Tick the rows (the box in the header ticks all). Machines with no site cannot be ticked.
4. Press **Assign Job(s)**, choose the **Date** (tomorrow by default) and the **Driver**, then press **Assign Job(s)**.
5. The machines are added to that driver's job for that date. If the driver has no job that date, a new one is created.
   Check it under **Daily Jobs ▸ Jobs**.

**How to make a pick list**
1. Press **Operations**, tick the machines.
2. Press **Generate Pick List**. A list of products and quantities appears (**Qty List (Live)**, **Qty List (Picked)**,
   **Qty List (Stocked In)**), with **Needed**, **Stocked In** and **Total** per product.

**Other Operations tools**
- **Export Channels Excel**: downloads the channels of the listed machines.
- **Set Product Availability**: opens the warehouse availability page in a new tab.

**Good to know**
- The table loads only after **Search**; the summary panels and Needs attention load straight away.
- With **Grouped?** on, sites in the same Site Grouping appear together with a coloured left edge: if one matches the
  filters, its group-mates show too.
- Driver accounts of an operator see fewer columns (no stock, sales, job, contract, status or payment columns).
- The **Operations** button needs both export rights and full dashboard access.
- Notes (Ops Note, Site Note) save as soon as you click outside the box; there is no Save button.

## Operations ▸ Dashboard (Lite)

**What it is for**
A short version of the Operation Dashboard for checking machines at a glance: identity, temperatures, stock and recent
sales quantity. It is meant for product owners who should see only their own products.

**What you see**
- The same filters as the full Dashboard, except **Grouped?**, which is switched off here.
- Columns: **Machine ID** block (prefix, mapping, site, postcode, **Ref Price**, **Campaign**), temperature (**T1:
  Machine Temp**, **T2: Evaporator Temp**, **Updated**, **T1 lowest L48hr**, Fan RPM), **Inventory Status** (with
  **Stock Cost**, **Stock Value**, **Full Load Value**), and **Sales(qty)** (**Today**, **Y'day**, **Last7d**, **Last30d**).
- Colours mean the same as on the full Dashboard.

**How to check a machine**
1. Type its ID in **Machine ID** and press **Search**.
2. Click the Inventory Status box to see its channels.

**Good to know**
- No summary panels, no Needs attention, no Show Alert/Error and no job tools on this page.
- If your account only sees some products, stock figures cover only your products, and sales quantities are left blank.
- Admins and product owners can open it.

## Operations ▸ Ops Performance

**What it is for**
Daily and monthly operations KPIs for the machines you choose, plus a count of the fleet by machine status and parts.

**What you see**
- Filters: **From**, **As of (To)** (the day the columns count back from; yesterday by default), **Machine ID**, **Site**,
  **Operator**, **Location Type**, **Machine Prefix**, **Vend Model**, **Category**, **Machine Status**, **Site Status**.
  Buttons **Apply** and **Export to Excel**.
- **Key KPI** table. Columns: **Avg L7d**, **Avg L30d**, the last 8 days (**Yesterday**, then weekday names), then
  **This Mth**, **L1m**, **L2m**, **L3m**. Rows include **Total Active Machines**, **Stock-in Value, $**, **Transaction $**,
  **# of qty sold (Transaction)**, **Total Gross Margin, %**, **Total Gross Margin, $**, **Avg per vm, L30d VendEarning, $**,
  **L30d sales higher than LastMth**, **L30d VendEarning, $**, **Monthly VendEarning, $**, **Current Mth sales higher
  than Previous Mth**, **Avg Daily Sales, L30D >= Overall Avg/day** (machine counts with % of total).
  A cell is green when it is higher than the period before it and red when lower.
- **Machines' Status & Component** table: counts and % for **LCD**, **Bill Acceptor**, **Coin Acceptor**, **Card
  Terminal**, **Card Terminal (type)**, **Modem Model**, **VMC Firmware Ver**, **Android APK Ver**, **ACB Rev**.

**How to compare a group of machines**
1. Set **As of (To)** and the filters (for example one **Operator** or **Location Type**).
2. Press **Apply**.
3. Press **Export to Excel** to download the same view.

**Good to know**
- **Machine Status** filters only the Status & Component table.
- The component counts are a snapshot taken each night (03:00). For today they show the live fleet, with a note saying so.
- Admins and supervisors only. Not available to accounts limited to certain products.

## Operations ▸ Site Grouping

**What it is for**
Group sites that stand at the same place (for example several machines in one building) so they can be handled
together. On the Operation Dashboard, ticking **Grouped?** then shows a whole group whenever one member matches.

**What you see**
- **Find a group** (filter by name) and **+ New Group**.
- One card per group: name, notes, number of sites, the member sites, **Rename / notes**, **Delete group**, and a box to
  add sites.

**How to create a group**
1. Press **+ New Group**.
2. Type the **Group name** (for example "Blk 123 cluster") and optional **Notes**.
3. Press **Save**.

**How to add or remove sites**
1. In the group's card, type in **Add site by name, Machine ID or Site ID…** and pick the site.
2. To take a site out, press **Remove from group** beside it.

**How to rename or delete a group**
- **Rename / notes** opens **Edit Group**; change and **Save**.
- **Delete group** asks to confirm. The sites are only ungrouped, not deleted.

**Good to know**
- A site can be in one group only. The add box only offers sites that are not in a group yet.
- Group names must be unique.
- Admins and supervisors only.
