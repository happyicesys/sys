# Daily Jobs

Plan and record each driver's daily refill route: which machines to visit, what to pick from the warehouse, what was loaded, the cash collected, plus spot stock counts and repair visits.

## Daily Jobs ▸ Jobs

**What it is for**
A **job** is one driver's route for one date. Inside it, each machine to visit is one **job item** (with its own **Job ID#**). You pick stock for each item in the warehouse, the driver loads it into the machine (**Stock In**), and the office checks the result (**Verified** / **Flagged**). A job can also hold extra stops: tasks, stock counts and service notices.

**What you see (job list)**
- Filters: **Job ID#**, **Machine ID#**, **From** / **To** (job date; opens on 3 days ago to 1 week ahead), **Delivery By** (driver), **Created By**, Operator. Press **Search**.
- **Date / Deliver By / # of Job / Refillable Value (Qty)**: click the date box to open the job. Today's job is green, tomorrow's yellow.
- **Jobs**: how many items are **Picked(%)**, **Stock-in(%)**, **Verified(%)** (and **API Invoices(%)** where the warehouse invoicing link is set up). A box turns green at 100%.
- **Picked** and **Stock-in**: value, qty and cost. Stock-in value shows red when it is less than what was picked.
- **Stock-out (Transactions)**: sales since each machine's previous visit.
- **Cash Amount**: **Cash Collected (fr VM)**, **CashAmt$ (VMC)** (what the machine reports), **% Cash Collected/ CashAmt$**, **Cash Adjustment** (green above, red below).
- **Created By / Created At**.

**What you see (inside a job)**
- **Date**, **Delivery By**, **Remarks** (with its own **Save**).
- **Add Job / Stock Count / Service Notice**: a machine dropdown with three buttons, **Open New Job**, **Open Stock Count**, **Open Service Notice**.
- Buttons: **Show Map Markers**, **Qty List (Live)**, **Qty List (Picked)**, **Qty List (Stocked In)**, **Route Planning**, **Batch Assign Driver**, **New Task**.
- Counts: total jobs, stock counts, service notices and API invoices.
- Table, one row per stop:
  - **Job Sequence**: the visiting order, with a **Renumber** button.
  - **Machine ID / Machine Prefix / Product Mapping / Job ID# / Remarks**. Click the **Job ID#** to open the item. A "New" mapping tag means a new product mapping is waiting.
  - **Status / Site / Postcode / Ops Note**. Status is Pending, Picked, Stock In, Verified, Flagged or Cancelled. After Stock In it also shows a tally result (**All tally**, **Not tally fixed**, **Not tally havent fixed**), plus the coin float and any channel errors.
  - **Value (Qty)**: **Refillable**, **Picked**, **Stock-in**, **Stock-out (Transactions)**.
  - **Cash Amount**: **Cash Collected (Machine)**, **CashAmt$ (VMC)**, **Cash Adjustment**, **CashAmt$ (Trans)**, **Cashless$ (Trans)**.
  - **Refilling Routes / Address** (with GPS link and map pin).
  - **Action**: **Driver** (move the stop to another driver or date).
- Tasks show a **TASK** badge. Service notices show an amber **SERVICE** badge and stock counts a cyan **COUNT** badge, each with an **Open** / **View** button.

**How to create a job**
1. Open **Daily Jobs ▸ Jobs** and press **Create**.
2. In **Create New Job**, pick the **Date** (it starts on tomorrow) and **Delivery By** (the driver).
3. Press **Save**. The job appears in the list. Click its date box to open it.

You can also add machines to a driver's day from **Operations ▸ Dashboard** with **Assign Job(s)**. That uses the driver's existing job for that date, or creates one.

**How to add machines (job items)**
1. Open the job.
2. In **Add Job / Stock Count / Service Notice**, choose the machine. Machines already on the job say "in job".
3. Press **Open New Job**. The machine is added with all its channels and the quantities each one needs.
4. A machine already on this job cannot be added twice as a refill, but you can still open a stock count or a service notice for it.

**How to pick stock (warehouse)**
1. Click the item's **Job ID#**.
2. Each channel shows **Needed Qty/Capacity** and a **To Pick Qty** dropdown. It starts at the live needed qty, shown in blue.
3. Change any **To Pick Qty** you need. Changed ones turn red. **Zero-up To Pick Qty** sets every channel to 0 so you can choose by hand. **↺ Live** puts one channel back to the live needed qty.
4. Optional: press **Save & Freeze 'To Pick Qty'**. It keeps only the quantities you changed (red). Untouched ones (blue) keep following the live needed qty.
5. Press **Picked** and confirm.

**How to Stock In (load the machine)**
1. Open the item. It must be **Picked**.
2. For each channel, choose the **Stock In** qty you actually loaded. It shows red if less than picked and green if more.
3. Fill in **Cash Collected (Machine)** and **CashAmt$ (VMC)**. **Cash Adjustment** is the difference.
4. Press **Stock In** and confirm. You get an extra warning if either cash figure is 0.
5. Add photos under **Attachment(s)** (upload or drag and drop).

**How to use a Stock Action**
Open the item while it is Pending and use the **Stock Action** menu:
- **Implement New Mapping**: switch the machine to its waiting new product mapping. This option only shows when a new mapping is waiting. New products show as **Upcoming** / **To Be**, and the ones they replace show as **Current**.
- **Return All Stock**: take all stock out of the machine.
- **Stock Adjustment**: correct the quantities on site.
- **Melted Ice Cream**: write off and remove melted stock.
- **Clear Action**: remove the action.

**Return All Stock**, **Stock Adjustment** and **Melted Ice Cream** need no warehouse picking. The item moves straight to **Picked**, and at Stock In you can enter negative quantities (stock taken out).

**How to add or remove a channel on an item**
- While the item is Pending with no stock action, press **Add New SKU** under the channel table. Choose the channel, set **Pick Qty:**, and optionally **To replace current #** (that channel's pick qty then defaults to 0). Press **Add**.
- Remove a channel with its **Remove Channel** (trash) icon.

**How to set the visiting order (route)**
- Type a number into an item's **Job Sequence** field. It saves when you leave the field. It is locked once the item is stocked in.
- **Renumber** rewrites the sequence as 1, 2, 3… in the order the table shows now. If the table is sorted by another column, you are asked to confirm first.
- **Route Planning** opens a map page:
  1. Pick the **Origin** and, if offered, tick **Return to … (last stop)**.
  2. Press **Generate Route (Google API)** or **Generate Route (Nearest Distance Algo)**. A **Generated Sequence** column and the **Total travel** distance appear.
  3. Press **Sync Generated Sequence to Current** to save it. This overwrites the current order.
  4. Or press **Generate Route (Claude JSON)**: copy the planning prompt, plan the route with Claude, paste the JSON it returns, then press **Generate & Overwrite Current Sequence**. Stops missing from the JSON go to the end.

**How to change the driver or date of stops**
- One stop: press **Driver** in its **Action** column, choose **Date** and **Delivery By**, then press **Change Driver**.
- Many stops: press **Batch Assign Driver**, tick the items and tasks, then press **Batch Assign Driver (n)**. Choose **New Date** and **New Driver** and press **Assign n Item(s)**.
- The stops move to that driver's job for that date. If no such job exists, one is created.

**How to add a task (a non-machine stop)**
1. Press **New Task**.
2. Fill in **Task Name** (for example "Collect key from security"), **Postcode**, **Address**, and optionally **Sequence**, **Amount**, **Qty**, **Ops Note**, **Ref URL**.
3. Press **Create Task**.
4. To update its progress, click its status badge and use **Mark as Picked** or **Mark as Completed** (and **Undo Picked** / **Undo Completed**). **Edit** opens the same form, where you can also **Delete** it.

**How to check and finish an item (office)**
- After Stock In, press **Verified** if all is correct, or **Flagged** if it needs follow-up.
- If the machine's own count does not match a channel, that channel shows a **Fix?** button. Press it once the mismatch is fixed. The job page then reads "Not tally fixed".
- **Update Cash** confirms the cash collection. **Undo Cash Collection** reopens it.

**Good to know**
- **Picked** takes the picked quantities out of warehouse stock. **Undo Picked** puts them back.
- **Stock In** records what is in the machine. With **Implement New Mapping**, Stock In also switches the machine to the new mapping. Any old products left in the machine are returned automatically, and the new product list is sent to the machine.
- Undo buttons: **Undo Stock In** takes the item back to Picked. **Undo Verified** and **Undo Flagged** take it back to Stock In. **Undo Stock Action & Picked** takes a Return / Adjustment / Melted item back to Pending and clears the action.
- About 10 minutes after Stock In, an item's figures are locked as a snapshot (marked **🔒 Freezed**). Undo Stock In releases the lock.
- A Pending item can be **Cancel**led. A cancelled item can then be **Delete**d, which cannot be undone. A whole job can be deleted (**Delete** at the bottom of the job) only while it has no machines.
- Once an item is stocked in and its cash is confirmed, its attachments can no longer be changed.
- **Bypass Capped Qty** ignores a product's quantity cap for that day on this machine. A capped channel shows "capped (n)". **Abide Capped Qty** turns the cap back on.
- **Create API Invoice(s)** shows only where the account is linked to the warehouse invoicing system. It sends stocked-in items there after you confirm.
- Drivers only see their own jobs: their **Delivery By** filter is locked to themselves.
- Pickers can pick and stock in, but they do not get **Stock Action**, **Renumber**, the Undo buttons, **Verified** / **Flagged** or **Undo Cash Collection**.
- Cancel and Delete need the delete right for operations.
- Smart freezer and smart chiller only:
  - Their items have no cash section.
  - Take the **1. Before restock photo** before **Open Door (Restock)** can be used.
  - After Stock In, you are asked for the **2. After restock photo**.
  - The door opening is logged with your name.
  - On a chiller, Stock In sends the count to the chiller supplier's system (**Retry push** if it failed), and Undo Stock In restores their previous count.
  - A chiller does not offer **Implement New Mapping** or **Melted Ice Cream**.

## Daily Jobs ▸ Summary

**What it is for**
Totals of the Daily Jobs per driver over a date range. Use it to compare drivers' picking, stock-in and cash collection.

**What you see**
- Filters: **From** / **To** (opens on the last 7 days up to yesterday), **Delivery By**, Operator. Press **Search**.
- One row per driver (**Deliver By**, **# of Days**), plus a **Total** row:
  - **Jobs**: **Picked(%)**, **Stock-in(%)**, **Verified(%)**, **API Invoices(%)**.
  - **Picked** and **Stock-in**: value, qty and cost.
  - **Stock-out (Transactions)**: total amount and qty.
  - **Cash Amount**: **Cash Collected (fr VM)**, **CashAmt$ (VMC)**, **% Cash Collected/ CashAmt$**, **Cash Adjustment**.

**Good to know**
- Drivers see only their own row.
- Not every role has this page: office staff, technicians and drivers have it. Pickers and partner-operator accounts do not.

## Daily Jobs ▸ Stock Counts

**What it is for**
Spot checks: a driver counts the real quantity in some or all channels of a machine during a job, and the office compares it with the system quantity. (This is not the nightly stock valuation report under **Report**.)

**What you see**
- Filters: **Job Date From** / **Job Date To**, **Machine ID**, **Site**, **Status** (Pending, Counted, Cancelled), **Assigned To**, **Result** (All, Any mismatch, Short only). Buttons **Search**, **Reset**, **Export Excel**.
- Columns: **Ref** (opens the count), **Job Date** (opens the job), **Machine ID**, **Site**, **Assigned To**, **Status**, **Channel(s)** (number counted, random or all), **Mismatch**, **Variance (pcs)**, **Variance (amount)**, **Synced**, **Counted**, **Remarks**.

**How to open a stock count**
1. Open the job in **Daily Jobs ▸ Jobs**, choose the machine in **Add Job / Stock Count / Service Notice** and press **Open Stock Count**.
2. Optional: limit it to **Only these product(s)**.
3. Optional: tick **Random channels?** and say **How many channels**. Unticked means every stocked channel is counted. Channels the system already shows as empty are left out.
4. Optional: add **Remarks** and a **Job Sequence**.
5. Press **Open Stock Count**. It appears on the job as a **COUNT** row.

**How to do the count (driver)**
1. Open the count from the job (**Open**) or from this list.
2. For every channel, choose the **Real Qty** next to **Current Qty**. You can add a note per channel and photos under **Photo(s)**.
3. Press **Submit Count 提交**. This only works once every channel has a real qty.

**How to correct the system quantity (Sync)**
1. Open a counted stock count that has mismatches.
2. Under **Sync the system quantity with this count**, press **Sync n channel(s)** and confirm.
3. Each mismatched channel's system quantity is corrected by its variance, and the result per channel is listed.

**Good to know**
- Opening a count, **Re-draw** (draw a new random sample), **Sync**, **Delete** and **Export Excel** are for supervisors and above. Drivers can count and submit.
- For a vending machine, Sync corrects ConnectVend only. The machine keeps its own count, so correct it on the machine too, or its next report overwrites the correction.
- Smart chiller only: a count cannot be synced. Correct the stock through a refill visit with **Stock Adjustment** instead.
- **Reopen** clears the counted figures. **Cancel Count** stops a pending count.
- Once a count has been synced, it cannot be reopened or deleted.

## Daily Jobs ▸ Service Notices

**What it is for**
Repair stops inside a Daily Job. Each notice lists the repair items for one machine, and the driver or technician records what was done, with before and after photos.

**What you see**
- Filters: **Job Date From** / **Job Date To**, **Machine ID**, **Site**, **Status** (Pending, Completed, Cancelled), **Assigned To**, **Has Incomplete Item?**. Buttons **Search**, **Reset**, **Export Excel**.
- Columns: **Ref**, **Job Date**, **Machine ID**, **Site**, **Assigned To**, **Status**, **Item(s) Done** (done / total), **Incomplete**, **Completed**, **Opened**, **Remarks**.

**How to open a service notice**
1. Open the job in **Daily Jobs ▸ Jobs**, choose the machine and press **Open Service Notice**.
2. Type the **Service Item(s) 维修项目**, one per line (for example "Compressor noisy" on one line, "Door seal torn" on the next).
3. Optional: add **Remarks** and a **Job Sequence**.
4. Press **Open Service Notice**. It appears on the job as a **SERVICE** row.

**How to work a service notice**
1. Open it from the job or from this list.
2. For each item, fill in **Description 维修项目**, **Before 维修前** and **After 维修后**. Each has its own photos.
3. Give each item a progress status:
   - **Done 完成**.
   - **Incomplete 未能完成**: you must give the reason.
   - **Cancelled 取消**.
   - The back-arrow button resets an item to New.
4. Add more items with **Add Item**.
5. Press **Complete 完成维修单**. This only works when every item has a progress status.

**Good to know**
- Use **Has Incomplete Item? = Yes** to find repairs that could not be finished on the day.
- **Reopen** a completed notice to change it. **Cancel Notice** stops a pending one.
- Deleting a notice or an item also deletes its photos and cannot be undone. Deleting and **Export Excel** are for supervisors and above.
- Drivers and technicians can open and work service notices. Pickers cannot.
