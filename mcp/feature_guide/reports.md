# Report

Stock, sales and gross profit reports across your machines, with filters and Excel exports.

Common to these reports:
- The filters at the top are the usual ones: **Machine ID**, **Site name / ID**, **Machine Prefix**, **Operator**, **Location Type**, **Product**, **Site Binded?** and others. Set them and press **Search**.
- **Machine Prefix** has a **Single UD** choice that picks the single-unit machine prefixes together.
- Amounts are in your operator's currency.
- Only completed sales count as sales. Sales that failed to dispense are left out.
- The margin reports (**Machine Monthly Snapshot**, **GP by VM**, **GP by Product**, **Sales Performance by Product**, **GP by Location Type**) are for admins and supervisors only. Operator users do not see them.

## Report ▸ Stock Count Dashboard

**What it is for**
Charts of how much stock (and money) sits in your machines and warehouse, day by day for the month.

**What you see**
- Filters: **Machine ID**, **Site name / ID**, **Machine Prefix**, **Product**, **Location Type**.
- Totals: **Stock Value in Machines**, **Total Stock Cost - before GST**.
- Chart **Stock Value, Stock Cost & Coin Float by Day**.
- Chart **Stock Qty in Machine; Stock Qty in Warehouse; Machine Stock Balance %, by Day**, with **Quantity (#)** and **Machine Stock Balance (%)**.

**What the numbers mean**
- **Stock Value in Machines**: the units in the machines times their selling price on the machine.
- **Stock Cost**: units in the machines plus units in the warehouse, times the product's unit cost, before GST.
- **Coin Float**: the coins the machines report holding.
- **Machine Stock Balance %**: units in each machine ÷ its total channel capacity, averaged over the machines.

**How to look at one group of machines**
1. Pick the **Machine Prefix**, **Location Type** or type a **Machine ID** / **Site name / ID**.
2. Press **Search**. Hover over a day in a chart to see its figures.

## Report ▸ Daily Stock Count

**What it is for**
A per-product stock count for three days side by side, so you can see stock in machines and in the warehouse, and what it is worth.

**What you see**
- Filters: **Machine ID**, **Site name / ID**, **Machine Prefix**, **Product**, **Location Type**, **Filter Date** (or **Custom Date** with a **Date**).
- Three day groups: **Yesterday**, **2 Days Ago**, **3 days ago** (counted back from the date you pick).
- Per product and day: **Unit Cost $ (before GST)**, **Stock Value in Machine $**, **Stock Qty in Machine**, **Stock Qty in Warehouse (from API)**, **Total Stock cost $**.
- Footer rows: **Subtotal**, **Receivable - Daily cash sales**, **Receivable - Daily cashless sales**, **Coin Float in machines**.

**What the numbers mean**
- **Stock Value in Machine**: units in the machines times their selling price on the machine.
- **Total Stock cost**: (units in the machines plus units in the warehouse) times the unit cost.
- **Receivable** rows: that day's cash sales and cashless (all other payment) sales.

**How to export**
1. Set the filters and press **Search**.
2. Press **Export Excel**.

**Good to know**
- Click a column heading to sort by it.

## Report ▸ Machine Monthly Snapshot

**What it is for**
A record of each machine's state at month end, for looking back at past months.

**What you see**
- The note "(Machine stats will be captured on the last day of every month)".
- Filters: **Machine ID**, **Channel ID**, **Site name / ID**, **Machine Prefix**, **Site Binded?**, **Operator**, **Location Type**, **Current Month**.
- Columns: **Month**, **Capture Datetime**, the machine, **Inventory Status (#Channel, Sold, Balance/Capacity)**, **Status**, **Drop Sensor**, **Fan Speed**, **Door** (**Open** / **Close**), **Coin**.

**How to see a past month**
1. Pick the month in **Current Month**.
2. Press **Search**.

**How to export**
1. Press **Export Channels Excel** to download each machine's channels for the month.

**Good to know**
- The snapshot is taken on the last day of the month. The current month has no snapshot until then.

## Report ▸ Sales Report

**What it is for**
Sales counts, error counts and amounts for a period, cut by operator, machine, Site, product, category or location type.

**What you see**
- Tabs: **Operator**, **Vending Machines**, **Site**, **Product**, **Category**, **Location Type**.
- Filters: **Machine ID**, **Site name / ID**, **Machine Prefix**, **Product ID**, **Product Name**, **Site Binded?**, **Location Type**, **Machine Model**, **Machine Contract**, **Product Mapping**, **Filter Date** (or **Date From** / **Date To** with **Custom Date**).
- Columns:
  - **Count (Success Only)**: units sold, with the share of the overall total.
  - **Count (Error #3, #6, #7, #9) – Likely Stock Lost**, with the **(Error Rate)**. Error Rate = Likely Stock Lost ÷ (Success + Likely Stock Lost). Green = lower than overall, red = higher.
  - **Count (Error #4, #5) – Unlikely Stock Lost as Motor Not Turning**.
  - **Count of Machine (as of last day of period)**.
  - **Channel Availability (In-stock channels × days)**.
  - **Average Daily Count per Channel** = success count ÷ channel availability. Green = higher than overall, red = lower.
  - **Amount**: the money taken for the successful sales.

**How to run it**
1. Pick a tab.
2. Set the filters and the dates.
3. Press **Search**. Nothing loads until you do.

**How to export**
1. After searching, press **Export Excel**.

**Good to know**
- Users who may see only some products cannot open this report, because it shows whole-machine sales.

## Report ▸ GP by VM

**What it is for**
Gross profit per machine for this month, last month and the month before.

**What you see**
- Filters: **Machine ID**, **Site name / ID**, **Machine Prefix**, **Product ID**, **Product Name**, **Site Binded?**, **Location Type**, **Current Month**.
- For **This Month**, **Last Month** and **2 Months Ago**: **Sales (Qty)**, **Sales ($)**, **GP ($)**, **GM (%)**.

**What the numbers mean**
- **Sales ($)**: what customers paid for successful sales.
- **GP ($)** (gross profit): sales after taking out GST/VAT, minus the unit cost of the products sold. The GST/VAT rate is the operator's rate (set in **Admin ▸ Operators**). The unit cost is the product's cost recorded at the time of the sale.
- **GM (%)** (gross margin): GP ÷ Sales ($), as a percentage.
- A product with no unit cost counts as zero cost, so its GP looks too high. Check unit costs if a margin looks wrong.

**How to see another month**
1. Pick the month in **Current Month**. It becomes "This Month" and the two months before it follow.
2. Press **Search**.

**How to export**
1. Press **Export Excel**. You need export rights for the margin reports.

**Good to know**
- **Site Binded?** starts on **Yes** (machines on a Site only) for staff.
- Click a column heading to sort.

## Report ▸ GP by Product

**What it is for**
Gross profit per product for this month, last month and the month before.

**What you see**
- The same filters as **GP by VM**.
- Per product, for each of the three months: **Sales (Qty)**, **Sales ($)**, **GP ($)**, **GM (%)**.

**What the numbers mean**
- The same as **GP by VM**: GP = sales without GST/VAT minus unit cost; GM % = GP ÷ Sales ($).

**How to export**
1. Set the filters, press **Search**, then **Export Excel**.

## Report ▸ Sales Performance by Product

**What it is for**
How well each product sells for the space it gets, for this month, last month and the month before.

**What you see**
- The same filters as **GP by VM**.
- For each of the three months: **# of CH**, **Sales ($)**, **Sales (Qty)**, **Qty per Day**, **Availability (%)**.

**What the numbers mean**
- **# of CH**: how many machine channels carried the product.
- **Qty per Day**: units sold ÷ the number of days in that month.
- **Availability (%)**: the share of time the product's channels had stock. Low availability means lost sales from empty channels.

**Good to know**
- This report has no Excel export.

## Report ▸ GP by Location Type

**What it is for**
Gross profit grouped by the Site's location type (for example office, school), to compare kinds of locations.

**What you see**
- The same filters as **GP by VM**.
- Per location type, for each of the three months: **Sales (Qty)**, **Sales ($)**, **GP ($)**, **GM (%)**.

**What the numbers mean**
- The same as **GP by VM**. Location types come from **Data Management ▸ Location Types** and are set on each Site.

**How to export**
1. Set the filters, press **Search**, then **Export Excel**.
