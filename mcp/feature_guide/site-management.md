# Site Management

Sites are the places where your machines stand: their address, contacts, location-fee contract, price tier and the machine bound to them, plus the monthly location-fee figures and payouts.

## Site Management ▸ Sites

**What it is for**
The list of all your sites (locations). Each site holds its address, contacts, bank details, placement contract (location fees), the price tier its machines sell at, and the machine bound to it.

**What you see**
- Filters: **Site ID**, **Machine ID**, **Site** (name or ID), **Category**, **Attached to Machine?**, **Is Server Price?**, **Contract Attachment?**, **Site Status**, **Refilling Routes**, **Tags**, **Ref Price Type**, **Machine Model**, **Machine Prefix**, **Location Type**, **Setting Chart**, **Preferred Day(s)**, **#Refill per Week**. Press **Search** to apply.
- Columns include **Site**, **Machine ID**, **Operator**, **Status**, **Begin Date**, **Placement Contract Detail**, **Product Mapping**, **Sales (Last30d)**, **AvgDailySales (Last30d)**, **Gross Margin S$ (Last30d)**, **Lifetime Sales**, **Full Load Value**, **Preferred Visit Days**, **Ops Note** and **Tags**.
- **Show Map Markers** shows the filtered sites on a map. **Export Excel** downloads the list.

**How to create a site**
1. Open **Site Management ▸ Sites** and press **Create**.
2. Fill in **Site Name** and **Operator** (both required). Pick the **Status (Site)** and the **Reference Price Type** (RP1–RP5), which decides the price tier the site's machines sell at when they use Server Price.
3. Under **Site Address (Machine Placement Exact Location)**, key in the **Postcode** (it fills the building and street for you), then **Unit Num**, **Block Num**, and optionally **Google Map URL**, **Site Contact Person**, **Site Phone Number**, **Remarks for Address** and **Grab driver instruction**.
4. Under **Billing Contact**, fill **Bill From (Company Full Name or Personal Name)**, **Billing Contact Person**, **Email** (one per line), **Phone Number**. Tick **Billing Address same as Site Address**, or untick it and key in the billing address.
5. Under **Bank Details**, pick **Bank Name** and fill **Account Holder Name** and **Account Number** (if you fill one of the three, all three are required).
6. Press **Save Site**. The contract, machine binding, tags, photos and attachments are set on the edit page afterwards.

**How to edit a site and its contract (location fees)**
1. On the Sites list, press **Edit** on the site.
2. In **Placement Contract Detail**, pick one **Location Fees** type:
   - **F: Free Placement** – no fee.
   - **S: Subsidized Plan** – enter the **Subsidized Amount**.
   - **R: Fix Rental** – enter the **Fix Rental Amount**.
   - **U: Utility only** – enter the **Utility Amount**.
   - **R + U** – a fixed rental plus a **Utility Amount**.
   - **PS: Profit sharing only**, **PS + U**, **PS OR U (whichever higher)** – enter the profit-sharing % and the **PS Term** % (default 50); the U types also take a **Utility Amount**.
3. Optionally switch on **External Subsidize** and enter its amount, and fill **Contract From**, **Contract Until**, **Auto Renewal**, **Notice Period** and **Remarks for Contract**.
4. To attach the signed contract, choose the file under **Attach Contract** and press **Upload**.
5. Press **Save Site**. The page shows "Last Updated by" with the name and time of the last contract change.

**How to schedule a contract change for a future date**
1. On the site's edit page, in **Placement Contract Detail**, press **Set Upcoming Term**.
2. Pick the **Effective Date** (must be in the future) and fill the new contract fields.
3. Press **Confirm & Schedule**. On that day the new terms replace the current contract automatically.
4. To change or drop it before then, press **View Upcoming Term**, then **Edit** or **Cancel scheduled change**.

**How to bind a machine to a site**
1. Open the site's edit page and scroll to **Binded Machine**.
2. Pick the machine in **Machine ID#** (only machines not bound to any site are listed).
3. Press **Bind Machine**.
4. After binding, the section shows the machine, its **Product Mapping** and the products in its channels with **Actual Price on Vending Machine** and **Ref Price**.

**How to move a machine to another site (unbind)**
1. Open the current site's edit page, go to **Binded Machine**.
2. Press **Unbind Machine** (the site stays as it is), or **Unbind Machine & Deactivate Site** (the site is also set inactive). Confirm the pop-up.
3. Open the new site's edit page and bind the machine there with **Bind Machine**.

**How to set Server Price or Machine Price for the site's machine**
1. On the site's edit page, under **Binded Machine**, find **Is Server Price?**.
2. Choose **Server Price** (the machine sells at the site's **Reference Price Type** prices set on each product) or **Machine Price** (the machine sells at the prices keyed on the machine itself).
3. It saves at once; it is the same setting as on the machine's settings page.

**How to change a site's status**
1. On the edit page, change **Status (Site)**: **Potential**, **New**, **Active**, **Removed** or **Inactive**.
2. When you pick **Active** or **Removed**, a box asks for the **Active Date** or **Removed Date**. Commission is counted from the Active Date and stops after the Removed Date (that month is prorated). **Inactive** stamps today as the **Inactive Date** by itself.
3. Press **Save Site**. **View status change history** shows every change, who made it and when.

**Good to know**
- Changing the site's **Operator** also changes the operator of the bound machine when you save.
- Changing the **Reference Price Type** tells every Server Price machine on the site to load the new prices right away.
- **Delete Site** is shown only when no machine is bound. It is refused if the site has settlement entries or monthly summaries; deactivate the site instead. Delete asks no confirmation, so be sure first.
- **Site Grouping** puts co-located sites in one group (groups are managed under **Operations ▸ Site Grouping**).
- **Site Tags** take tags from **Site Management ▸ Tags**.
- **Performance Report Email**: enter a valid email and switch on **Enable Send Performance to Email?** to get the email action for this site on **Summary & Comm**.
- **Location Grading** (Placement / Access / Flexibility, A–C) and **Operations** fields (**Refilling Routes**, **Ops Note**, **Preferred Visit Days**, **#Refill per Week**) are saved with **Save Site**.
- **Machine Binding History** at the bottom lists every bind and unbind with date and user. Photos and attachments can be added once the site is saved.
- Anyone with Sites access sees this page; only users allowed to update sites see the save and bind buttons.

## Site Management ▸ Summary & Comm

**What it is for**
One row per site per month: sales, gross earning, the location fee owed under the contract, and what is left as vend earning. You check and lock each month here, record payments to site owners, and prepare the performance report emails. HQ staff only (admin, supervisor, technician); operator users do not see it.

**What you see**
- Filters: **Site**, **Machine ID**, **Billing Company**, **Payment Ref**, **Site Status**, **Tags**, **Location Type**, **Period Report** (default: current month, refreshed daily around 01:00 with yesterday's figures), **Custom Range (From)** / **(To)**, **Placement Contract Type**, **Contract Attachment?**, **Period Locked?**, **Location Fee Paid?**, **Payment Date** range. **All Filters** shows or hides the extra ones.
- **Aggregate Totals** boxes: **Total Sales**, **Total Gross Earning**, **Total Location Fees**, **Total Vend Earnings**, contracts without attachment, contracts to expire in 30 days, and **Payment to Loc Fees** (outstanding, paid, waived).
- Main columns: **Site Name**, **Ref Price**, **Begin Date**, **Avg Mthly Sales**, **Period**, **Sales ($)** (with and without GST), **Gross Earing**, **Location Fees Rate**, **Location Fees**, **External Subsidize**, **Net Loc Fee**, **Vend Earning**, **Accumulate Vend Earning**, **Loc Grading**, **Contract End Date**, **Site Note**, **Period Verify & Lock**, **Loc Fees paid?**, **Outstanding($)**, **Remarks for Loc Fees**.
- **Unread Cust Note** / **me only** show sites with notes you have not read.

**How to lock a month (verify the figures)**
1. Filter to the month with **Period Report**, check the row's figures.
2. Press **Lock** in **Period Verify & Lock**. The month's figures are frozen as they are now.
3. To lock many at once, tick the rows and press **Lock (n)** in the bar that appears, then **Confirm Lock**.
4. **Unlock** reverses it, but not while the row is marked paid.

**How to record a location-fee payment for one site**
1. The month must be locked first.
2. Press the pay button in **Loc Fees paid?**. A box opens: **Send to Settlement / Mark as Paid / Mark as Waived**.
3. Enter the **Amount paid** and **Payment date** (empty = today) and an optional **Comment**. To waive, tick **Waived?** and give the **Reason for waiving** (required).
4. Press **Send to Settlement** to add it to a Site Settlement batch for bank transfer (see the next section), or **Mark as Paid** if it was paid another way, or **Confirm Waived**.
5. **Undo Verify Paid** reverses a paid mark.

**How to send the performance report to a site owner**
1. The site needs a report email switched on in its edit page.
2. Press **Report Content** on the row to preview the report, then **Email** to open your mail program, or **Copy Email Content** to paste it into webmail.
3. For several machines of one client, tick the rows and press **Export Batch Report Content**. For paid rows, **Export Batch Paid Report** builds payment-advice texts.

**How to see a site's payment ledger**
1. Press **Payment History** on a row.
2. The ledger shows every fee, payment and waiver with **Debit**, **Credit** and **Balance**. You can **Record Waived**, **Record Adjustment** (charge or credit), and **Export** a Statement of Account or an Excel file.

**Good to know**
- Free Placement and Subsidized Plan sites have no report content; other types need a complete contract.
- The current month is prorated to date and shows the full month once it closes.
- **Show all Periods** opens one site's history in a new tab.

## Site Management ▸ Site Settlement

**What it is for**
Pays location fees to sites in batches by bank transfer. Rows sent from **Summary & Comm** gather in one open settlement per payout group per day. HQ staff only.

**What you see**
- List: **Settlement** (reference), **Date**, **Payout Group / Operator**, **Status** (Open or Closed), **Sites**, **Total**, **Paid**. A red **stale** tag marks an open settlement from an earlier day.
- Inside a settlement: each **Site**, **Month**, **Amount**, **Bank / Proxy**, **Paid?**, the **Exported files**, and an **Audit trail**.

**How to pay a settlement**
1. Open the settlement from the list.
2. Press **Export CIMB text** and upload the file to the bank to make the transfers.
3. After the bank has paid, tick the rows that were really paid, pick the **Paid date** and press **Mark selected as Paid**. This records each payment in the site's ledger.
4. When every row is paid, the settlement closes by itself.

**Good to know**
- Only rows that are locked, unpaid, from a finished month, owe a fee and are not already in a settlement can be sent here.
- A row marked "missing" has no bank account or PayNow details; fix it in the site's **Bank Details** before exporting.
- **Remove** sends a row back to Summary & Comm (only while the settlement is open). **Void (empty)** deletes an open settlement with no rows. **Undo close settlement** reopens a closed one.

## Site Management ▸ Performance

**What it is for**
A month-by-month overview across all filtered sites: the average of the last 30 days and the last 8 completed months. HQ staff only.

**What you see**
- Filters: **Site**, **Machine ID**, **Site Status**, **Tags**, **Machine Prefix**, **Location Type**, **Placement Contract Type**. **Search**, **Reset**, **Export Excel**.
- Rows (one per **Metric**): total active sites, total sales (with and without GST), gross earning, location fees, vend earning; site status counts; how many months are locked; location fees paid and unpaid; sites per contract type; contracts with and without attachment; contracts ending in the next 15/30/60 days without auto renewal; and averages per machine of net location fee, vend earning and accumulated vend earning, split into bands.
- Green means better than the month before, red worse.

**Good to know**
- Money figures per month come from the stored monthly snapshots. Site status and contract end dates are today's settings, applied to each month.

## Site Management ▸ Tags

**What it is for**
The list of tags you can put on sites (the page title reads "Customer Labels"). Tags are used to filter on **Sites**, **Summary & Comm** and **Performance**. HQ staff only.

**How to add or change a tag**
1. Press **Create**, enter **Name** and optional **Desc**, press **Save**.
2. To change one, press **Edit** on its row.
3. Put tags on a site from the site's edit page (**Site Tags**).

**Good to know**
- A tag still used by a site cannot be deleted. Remove it from those sites first; the row tells you how many records use it.
