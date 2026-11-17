# Grab

Selling from your machines through GrabFood: orders, stores, menus and prices, promotions and errors.

Only for setups that sell through GrabFood (Singapore). Each Grab store sells from one machine; a Grab rider collects the order from the machine.

## Grab ▸ Grab Orders

**What it is for**
Every Grab order your machines received, with its status, items and any complaint.

**What you see**
- Filters: **Grab order ID**, **Short Order ID**, **Machine ID**, **Grab merchant ID**, **Delivery Platform**, **Status**, **Has Complaint?**, and dates (**From** / **To**).
- Totals: **Total Amount (Delivered)**, **Total Orders (Delivered)**, **Avg Per Order (Delivered)**.
- Columns: **Order Time**, **Dispensed Time**, **Delivered/ Cancelled Time**, **Platform**, **Machine**, **Order ID**, **Short Order ID**, status, **Transaction**, **Items (slot) × qty**, **Subtotal**, **Campaign**, **Channel Error(s)**, **Driver phone · Complaint**.
- "Driver came N min after ready" shows how long after the order was ready the rider collected it.

**How to find an order a customer asks about**
1. Type the **Short Order ID** (or the full **Grab order ID**) and press **Search**.
2. Click the status badge to see each status change with its time.
3. Click **Complaint** (when shown) to see the rider's phone, name, problem and qty affected.

**How to cancel an order**
1. Click the **Order ID** to open the order.
2. Press **Request to Cancel Order** and confirm.
3. ConnectVend asks Grab to cancel it and tells you whether Grab accepted.

**How to export**
1. Set the filters and press **Export Excel**.

**Good to know**
- **Request to Cancel Order** shows only while the order is not cancelled and not yet finished.

## Grab ▸ Grab Stores

**What it is for**
Put each Grab store on a machine, move it, pause it or switch it off, and keep its address on Grab right. Menus and prices are set on **Grab ▸ Grab Menu & Prices**.

**What you see**
- Filters: **Machine ID**, **Grab store** and status (**Selling**, **Paused**, **Switched off**, **All**).
- Grab sales of the listed stores over the last 7 and 30 days.
- Per store: the machine, **Status**, **Machine ID on Grab**, **Orders** and **Grab sales per day** (delivered orders less Grab promo), the Grab name and address read from the GrabMerchant website, Grab errors and promotions of the last days, and warnings (for example "Grab shows postal code X, this Site is Y").
- Buttons per store: **Edit**, **History**, **On Grab now** (the menu exactly as Grab pulls it).

**How to add a store**
1. Press **Add store**.
2. Pick the **Operator** and type the **Grab merchant ID**. ConnectVend checks it with Grab before saving.
3. Optional: type the **Machine ID** and **Remarks**.
4. Press **Save**. If the store is not live on Grab yet, use **Save without checking with Grab**.

**How to put a store on a machine, or move it**
1. Press **Edit** on the store, then **Put on machine…** or **Move to another machine…**.
2. Type the machine. ConnectVend shows what the machine will list on Grab and the price rule. Give any product it asks for a sub-category and price, or it stays off Grab.
3. Press **Move**. The old machine stops and the new one starts at the same moment; the store stays open on Grab.

**How to pause, switch off or remove a store**
- **Pause…**: a short break. Give a **Reason** and optionally **Until**. It comes back by itself at that time, or when you press **Resume…**.
- **Switch off…**: closed on Grab until someone presses **Switch on…**.
- **Remove from machine…**: no machine sells it; it is paused on Grab. Its history is kept.

**How to change a store's address or name on Grab**
1. Press **Edit**, then **Update on Grab…**.
2. Pick **What should Grab change?** (address, store name, opening hours or phone, something else).
3. For an address, check the new address and postal code from the Site, the go-live date, and copy the **Grab driver instruction**.
4. Send it to Grab by following the steps shown: the GrabMerchant app for one store, or the Grab Help Centre form for several stores. Grab does not take these by email.
5. When Grab shows the change, press **Done** on the store.

**Good to know**
- Moving or removing a store ends the promotions made in ConnectVend on that store. Promotions made in the Grab app stay. Add them again afterwards if you still want them.
- Grab allows 2 address changes a month.
- A store Grab has closed for good shows **Closed by Grab**. It cannot sell again; ask Grab for a new store.
- The merchant ID cannot be changed: it is the store on Grab.
- Adding, moving and pausing stores need Grab edit rights.

## Grab ▸ Grab Performance

**What it is for**
How each Grab store sells, to decide which machines stay on Grab and which move.

**What you see**
- Filters: **Machine ID**, **Period** (last N days).
- Totals for the period compared with the period before: **Grab sales**, **Orders**, **Average order**, **Stores selling**, **Dropping**.
- A **Grab sales per day** chart.
- Per store: machine, **Status**, **Sales per day**, change against the period before, **Days with orders**, **Last order**, and a small bar per day.
- Tags: **No orders**, **Dropping** (under half the period before), **New on this machine** (figures not comparable yet).

**How to find stores to look at**
1. Pick the **Period** and press **Search**.
2. Sort by a column, or look for rows tagged **No orders** or **Dropping**.

**Good to know**
- Figures are delivered Grab orders less Grab promo, the same as on Grab Stores, counted for the machine on the store now.
- **No orders** on a store that was selling all period may mean it is closed on Grab. Check the store on Grab.

## Grab ▸ Grab Menu & Prices

**What it is for**
Decide what each Grab store sells and at what price. A store on the machine menu sells what is in its machine, priced by a price rule.

**What you see**
- **Operator** picker and counts: **On the machine menu**, **Still on the old Grab menu**, **With their own price rule**, **Products to fix**.
- **Company price rule**: the rule every store follows unless it has its own.
- **Grab stores**: each store with its machine, Site, **Grab menu** (machine menu or old menu), **Price rule** and **Grab orders (30 days)**.
- **Products**: each product in the machines with its **Grab sub-category**, **Grab price (company rule)** and **Status**.

**How to set the company price rule**
1. Press **Change company rule**.
2. Choose how Grab prices are made:
   - **RP × multiplier**: Grab price = the product's RP price (pick the **RP tier**) times a number, for example 1.25, always rounded up (**Round up to**).
   - **Typed price**: you type each product's Grab price under **Products**.
3. Set the **Grab category** every product sits under (**Load Grab's list** to pick it).
4. Press **Save company rule**. Stores on the machine menu that follow this rule show the new prices on Grab within a few minutes.

**How to give one store its own price**
1. Press **Open** on the store.
2. Under **Price rule for this store**, choose **Give this store its own rule** and set it. Add a reason if you like (for example "Quiet store, lower prices").
3. Check **What Grab customers would see with this rule** (prices up, down, new, leaving Grab).
4. Press **Save price rule for this store**.

**How to switch a store to the machine menu**
1. Tick the store(s) and press **Switch N to machine menu…** (or **Switch to machine menu…** inside a store).
2. Check the list and press **Switch**. Within a few minutes Grab shows what is in each machine. Products not in the machine leave Grab.
3. You can put a store back with **Put back on old menu…** at any time.

**How to fix products that are not on Grab**
1. In **Products**, filter **To fix**.
2. Give each product what the **Status** column asks for: a **Grab sub-category** and/or a price. Tick several and use **Set on ticked products** to set a sub-category at once.

**Good to know**
- A product with no price or no Grab sub-category is left off Grab, never sold at zero.
- A store on its old Grab menu ignores the machine and price rule until it is switched.
- A product's Grab sub-category is the same at every store.

## Grab ▸ Grab Promotions

**What it is for**
See, create, change and end promotions running on Grab. The list is read from Grab every 10 minutes.

**What you see**
- Last time Grab was read, and promotions sent to Grab in the last 24 hours (accepted / refused / waiting).
- Filters: search by store, machine or promotion name; **On Grab now** / **Gone from Grab**; who made it (**Made in ConnectVend**, **Grab merchant app**, **Grab team**).
- Per promotion: **Promotion**, **On** (whole order or every item), **When**, **Grab stores**, **Status** (**Live now** / **Upcoming**), **Made by**, with **Edit** and **End**.

**How to create a promotion**
1. Press **New promotion**.
2. Pick the Grab stores.
3. Pick the **Deal** (for example percent off each item, dollar off the order, any mix of items for a fixed price, delivery fee off) and fill its amounts (**Up to ($)**, **Min. spend ($)** where offered).
4. Set **Starts** and **Ends**. Check the **Name on Grab**.
5. Optional, under **More options**: **For** (all customers or new customers only), **Uses in all**, **Uses per customer**.
6. Press **Send to N Grab stores**.

**How to change or end a promotion**
1. Press **Edit**, change it, and press **Save on N Grab stores**. Or press **End** and then **End on Grab**.
2. To end it on one store only, open the promotion and press **End on this store**.

**Good to know**
- Changes are sent straight to Grab, store by store. The list shows what Grab has.
- A promotion not made in ConnectVend may be refused when you try to end it here. End it where it was made.
- If a store could not be read, its promotions may be out of date. Press **Read again**.

## Grab ▸ Grab Errors

**What it is for**
Problems talking to Grab: a menu, order, pause or promotion that did not go through, or an order Grab has that never reached ConnectVend.

**What you see**
- Filters: **What**, search (store, order ID or message), **From** / **To**.
- Columns: **When**, **Direction** (**We → Grab** or **Grab → us**), **Grab store**, **Order**, **Status**, **Message**. Open a row to see what was sent and answered.

**How to check a store's problems**
1. Type the store's merchant ID in the search and press **Search**.
2. Open the rows to read Grab's answer.

**Good to know**
- Each row is one failed try, so one problem can show many times.
- Errors are kept for a limited number of days (shown on the page).

## Grab ▸ Grab Product Mapping (old menus)

**What it is for**
The old Grab menus (templates). Only stores still on their old menu use them. A store switched to the machine menu takes its menu and prices from **Grab ▸ Grab Menu & Prices**.

**What you see**
- Filters: **Name**, **Machine ID**, **Grab merchant ID**, **Operator**.
- Columns: name, **Platform**, **Channel - Product**, **Machines on this menu**, with **Edit** and **Delete**.

**How to pause stores on an old menu**
1. Press **Edit** on the menu.
2. Under **Vending Machine Binding**, press **Pause VM** / **Resume VM** for one store, or **Pause every Grab store on this menu**.

**Good to know**
- When every store on a template has moved to the machine menu, its items can no longer be changed here.
- **Reserved Percentage (%)** / **Reserved Quantity**: stock kept back from Grab. The higher of the two is reserved; a channel with less stock than that goes inactive on Grab. Both start at 0.
- A template still in use cannot be deleted (**(in use)**).
- For new setups, use **Grab Menu & Prices** instead.
