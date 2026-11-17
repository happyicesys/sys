# Dashboards

Sales performance charts for your machines, and a health board that lists machines needing attention.

## Dashboards ▸ Performance

**What it is for**
A management view of sales over time: this month against last month and last year, monthly totals, how monthly
sales split into earnings, fees and cost, and which machines sell best and worst.

**What you see**
- **Filters** (shown when the page opens): **Machine ID** (use "," for several), **Site** (site name or ID),
  **Machine Model**, **Machine Prefix** (includes **Single UD**), **Operator**, **Location Type**, **Product**,
  **Product Category**, **Product Category Group**. Then **Search** or **Hide Filters**.
- Nothing loads until you press **Search** ("Use the available filters and click Search to load performance data").
- **Historical Analysis**: choose **2 Yrs** or **3 Yrs** of history and a **Chosen Month**, then **Apply**.
  - **Sales Comparison (Last Year vs Chosen Month Year)**: the chosen month's year against the year(s) before.
  - **Last Month vs Chosen Month** (chart "Sales by Days"): day-by-day sales amount and count.
  - **Sales by Months**: monthly sales amount and count.
  - **Sales by Months — Vend Earning / Loc Fees / Product Cost / GST**: each month's **Total Sales (incl GST)**
    split into those four parts, with a line for the **VE ratio** (Vend Earning ÷ Total Sales).
- **Monthly Analytics By Criteria**: tabs **Location Type** and **Operator**; for each name and month it shows
  **Daily Sales/ VM**, **Machine Count** and **Total Sales**. The current month is highlighted.
- **Average Number of Vending Machines (Site) in operation, by Month** chart.
- **Past 30 Days - Top 10 Best Performance** and **Top 10 Worst Performance** tables: **Vending Machine**,
  **Amount**, **Sales(#)**. Buttons **Load Top 50** / **Load Bottom 50** extend the list.
- **Past 7 Days - 10 Best Sellers** chart (products).

**How to look at one machine or site**
1. Open **Dashboards ▸ Performance**.
2. Type the **Machine ID** (or several, separated by commas) or the **Site** name.
3. Press **Search**.

**How to compare a month with past years**
1. After a search, go to **Historical Analysis**.
2. Pick **2 Yrs** or **3 Yrs**.
3. Pick the month in **Chosen Month** and press **Apply**.

**How to see sales of certain products only**
1. In the filters, pick one or more in **Product**, **Product Category** or **Product Category Group**.
2. Press **Search**. A blue note "Picked products only." appears: sales cover just those products, and machine
   and site counts include only machines that sold one of them. The machine chart then becomes **Number of Sites
   that sold the picked products, by Month**.

**Good to know**
- Testing machines are left out of all figures.
- The earnings chart (Vend Earning / Loc Fees) comes from the monthly Site Summary, so it covers only machines
  bound to a site and can be slightly below the **Sales by Months** totals. It is hidden while a product filter is
  set, because site earnings are not split by product.
- Outlined parts of the earnings chart are provisional: Loc Fees and Vend Earning are recalculated from each
  site's current contract until that month is locked, so changing a contract changes those months. Hover a bar to
  see how much of the month is still open.
- A negative Loc Fee means a subsidised plan (the location pays you); a negative Vend Earning means the site's
  Loc Fee was larger than what it earned; a negative Product Cost is usually a refund or a re-costed product.
- If your account is limited to certain products, an amber note "Whole-machine figures." warns that this page
  counts every product in the machine. For figures limited to your products use **Dashboards ▸ Performance
  (Lite)** or **Transactions**.
- **Operator** and **Location Type** filters, and the **Show Filters** button after a search, are for admins,
  supervisors and operator admins only.

## Dashboards ▸ Performance (Lite)

**What it is for**
The same charts as **Dashboards ▸ Performance**, but counted per product, so a user limited to certain products
sees only their own products' sales.

**What you see**
- Filters: **Machine ID**, **Site**, **Machine Model**, **Machine Prefix**, **Operator** and **Location Type**
  (admins only), **Product**, **Product Category**, then **Search**.
- **Historical Analysis** (2 Yrs / 3 Yrs, **Chosen Month**, **Apply**), **Sales Comparison**, **Last Month vs
  Chosen Month**, **Sales by Months**, **Monthly Analytics By Criteria**, the machine-count chart, the top and
  bottom 30-day machine tables and **Past 7 Days - 10 Best Sellers**.
- A green note "Your products only." when your account is limited to certain products.

**How to use it**
1. Open **Dashboards ▸ Performance (Lite)**.
2. Set any filters and press **Search**.

**Good to know**
- There is no Vend Earning / Loc Fees / Product Cost / GST breakdown here.
- Who can open it: admins and product owners. Product owners see only this Lite page, not the full Performance page.

## Dashboards ▸ Machine Health

**What it is for**
One page that lists machines with problems: offline machines, silent controllers, broken temperature probes,
temperature alerts, machines with no sales, channel errors, and how long channels stay sold out. Each section
says what may cause it and what to do.

**What you see**
- **Filters**: **Machine Limit** (how many machines per list, default 15), **Vend Prefix**, **Operator**,
  **Machine ID** (use "," for several), then **Apply**.
- **(1) Alert on Lost of Connectivity or Electricity**: offline machines in boxes by hours offline
  (< 1hr, < 2hr, < 4hr, < 8hr, < 12hr, > 12hr; the last box shows up to 60 hours). Each machine shows hours
  offline, its last connect time and its card terminal.
- **(1B) Alert on Controller Not Responding**: the machine is still connected but its controller has stopped
  reporting, so there is no temperature reading. Boxes by silent hours, with last controller data and last reading.
- **(1C) Alert on Temperature Probe Error**: a probe that was reading normally now sends an error. Boxes by hours
  in error.
- **Temperature Alert** (T1 = the machine's freezer temperature, T2 = evaporator temperature):
  - **(2) Operation Error / Critical Parts Failure**: 2A cooling fan off, 2B T1 or T2 above 0°C, 2C above −8°C,
    2D did not reach −18°C, 2E above −17°C and rising. Columns are severity levels (for example > 30 mins and
    > 50 mins). Each row lists possible causes and when the alert clears by itself.
  - **(3) Preventive maintenance / Temp raise alert**: 3A lowest T1 & T2 in the last 24 hrs, 3B in the last
    72 hrs (above −21°C / −20°C / −19°C), 3C lowest temperature rising (24 hrs vs 48 hrs, by 1°C / 2°C / 3°C),
    3D T2 never above 2°C (possible defrost failure, > 24 / 48 / 72 hr).
- **(4) Alert on Lost of Transaction/Sales**: machines with no sales for a set time. You can change the hours for
  **No any Sales**, **No Cash Sales**, **No Sales via Card Terminal**, **No Sales via QR** and **No Digital Screen
  Activity** (defaults 48, 48, 48, 72, 72), then **Apply**. Lists show when the last sale of that kind was, and the
  cash lists show the **Coin Float**.
- **(5) Channel Errors**: two groups, **Dispense Stability (Error 7 & 9)** and **Motor & Mechanical (Error 3, 4,
  5, 6)**, each with a short repair guide. Columns: **Machine**, **Site**, **Counts of Error**, one column per error
  code. Settings: **Error Window (days)** (default 7) and **Show All Error(s)?** (**Yes (Include Cleared)** or
  **No (Not yet Cleared only)**).
- **(6) Stockout KPI Overview**: over a rolling 30 days, **Avg Stockout Duration**, **Longest Stockout**,
  **Recovery Within Target**, and **Top Channels by Stockout Duration** (**Machine / Channel**, **Product**,
  **Duration**, **Stockout At**, **Restocked At**; "Ongoing" = still sold out). Settings: **Channel Limit
  (Stockouts)** and **Stockout Target (hours)** (default 72).

**How to see past alerts of one kind**
1. Find the box or column (for example "> 12hr" offline, or "2B > 50 mins").
2. Press the small clock icon in its header.
3. A history window lists each **Machine** with **Triggered at** and **Dismissed at**. Press **Show More** for
   older ones, **Close** to finish.

**How to check machines with no sales**
1. Go to **(4) Alert on Lost of Transaction/Sales**.
2. Change the hours if needed (for example **No Sales via QR (>= hr)** to 24) and press **Apply**.
3. Click a Machine ID to open it on the Ops Dashboard. Suggested fixes: remote restart of the VMC and app, an
   onsite check and reboot, or a change of SIM card telco if the signal is poor.

**How to find channels that keep failing**
1. Go to **(5) Channel Errors**.
2. Set **Error Window (days)** and choose whether to include cleared errors, then **Apply**.
3. Read the error-code columns per machine: a green tick means that error is already cleared, red ones are not.

**Good to know**
- Temperature alerts cannot watch a machine that is offline; check section (1) for those.
- Alerts in sections (2) and (3) clear themselves once the temperature recovers (each row says at what temperature).
- Long lists show the first 10 machines; press **Load remaining** to see the rest.
- Who can open it: admins, supervisors, technicians, operator admins and operator supervisors.
