# Machine Management

Set up each machine (vending machine, smart freezer, smart chiller), bind it to a Site, send commands to it, and manage the on-screen look, Happy Hour discounts, alert limits and app updates.

## Machine Management ▸ Machines View

**What it is for**
A live list of machines with their temperatures, stock, errors, sales and connection status. Use it to spot problem machines and jump to their settings.

**What you see**
- Filters: **Machine ID**, **Site**, **Is Online?**, **Machine Status**, **Is Door Open**, **Temperature**, **Channel Errors**, **Balance Stock(%)**, **Remaining SKU(%)**, **APK Ver**, **Firmware Ver**, **Machine Prefix**, **Setting Chart**, **Modem IMEI** and more. Press **All Filters** to show them all, then **Search**.
- Summary boxes: **Total Sales (Last 30 days)**, **Avg per VM (Last 30 days)**, **Avg per Day per VM (Last 30 days)**.
- Columns: Machine ID, **Product Mapping**, **Ref Price**, **T1: Machine Temp** / **T2: Evaporator Temp**, **Modem**, **Status**, **Inventory Status**, **Errors**, **Error Rate 2d / 7d**, **Balance Stock**, **Remaining SKU#**, **Sales(qty)** (Today, Yesterday, Last7d, Last30d), **Payment Device**, **Last Visited**, **Begin Date**, **Operator**.
- **Operations** (with the export permission): **Export Channels Excel**.

**How to open a machine's settings**
1. Find the machine with the filters and press **Search**.
2. Click the Machine ID. This opens the machine's edit page in **Machines Settings**.

**How to see what is in each slot**
1. Click the inventory list in the **Inventory Status** column.
2. A window shows each channel (or basket, for a smart freezer; layer, for a smart chiller) with product, stock and capacity.

**How to restart a machine from this list**
1. Press **more** at the end of the row. This opens the **Advance Control for Machine ID#** page.
2. Follow the steps on the page: press **Restart VMC**, wait 1 minute, then press **Restart APK**.
3. **Push Products Info to Machine** makes the machine reload its product menu. **Reset Modem** restarts the modem (shows **N/A Remote Modem** when the modem cannot be reset remotely).

**Good to know**
- A temperature value opens that machine's temperature history in a new tab.
- A **Reset** button under the modem appears only for modem models that can be reset remotely.
- Remote restarts do not reach a machine that shows as offline.
- For a smart freezer, the **more** page has only **Restart APK**, which restarts the freezer app. The machine refuses while a sale is in progress.

## Machine Management ▸ Machines Settings

**What it is for**
The main place to set up a machine: its type, status, hardware, Site, product mapping, pricing source and card terminal. It also has the remote buttons that send commands to the machine (restart, reload menu, reload settings, logs, screenshot).

**What you see (the list)**
- Filters: **Machine ID** (use "," for several), **Site**, **Machine Status**, **Is Server Price?**, **Card Terminal**, **Modem IMEI**, **Simcard**, **Machine Prefix**, **Setting Chart**, **Machine Model** and more.
- Columns: **Model**, **Machine ID**, **Setting Chart** (with **Current Ver** and **Latest Ver**), **Prefix**, **Site** (with **Ref Price** and **Postcode**), **Product Mapping (Current)**, **Product Mapping (Upcoming)**, **Machine Status**, **Bill Acceptor**, **Coin Acceptor**, **Cashless Terminal**, **Modem**, **Simcard**, **Operator**, **Deploy Date**, **Retired Date**.
- **Log** under a Machine ID shows the change history of that machine.
- **Create** adds a new machine.

**What you see (the edit page, "Editing Machine")**
- **Machine** at the top: type a code, Site or ref ID to jump to another machine.
- Machine details: **Machine ID#** (fixed), **Label**, **Machine Type** (fixed), **Begin Date**, **Retired Date**, **Status**, **Model**, **Machine Sticker**, **Serial Number**, **Machine Key**, **Operator**, **Contract**.
- Vending machine setup: **Setting Chart**, **Current Version** / **Latest Version**, **Machine Prefix**, **Menu Frame**, **LCD Monitor**, **LED Matrix Panel**, **Fan speed signal**.
- Connection and payment: **Simcard & Number**, **Card Terminal Company**, **Card Terminal (Terminal ID)**, **Card Terminal Bound From**, **Modem Model**, **Modem IMEI**, **Is Server Price?**.
- Products: **Product Mapping (current)**, **Product Mapping (upcoming)**, then a table of channels with **Channel Code**, **Product**, prices and **Ref Price**.
- **Site** box: the bound Site, or a picker to bind one, plus **Site Binding History**.
- **APK Logs**, **APK Parameter** (what the machine reports), **Advance Control** (remote buttons), **End of Month Inventory Snapshots**.
- Small grey lines under many fields show who last changed that field and when.

**How to create a machine**
1. Press **Create**.
2. Choose the **Machine Type**: Vending Machine, Smart Freezer or Smart Chiller. It cannot be changed later.
3. For a vending machine or smart freezer, type the **Machine ID** and the **Begin Date**. For a smart chiller, pick the device from the list (its machine ID, model and status are filled in for you), then choose whether to bind a Site now or later.
4. Press **Save**, then open the machine and fill in the rest.

**How to bind a machine to a Site**
1. Open the machine. If it has no Site, the **Site** box shows **Plan** with **Select Existing Site**.
2. In **Site**, pick the Site. The list only shows Sites that have no machine yet.
3. Press **Save Site**.
4. The Site now shows in the box, and **Site Binding History** gets a new line with your name.

**How to unbind a machine from its Site (or move it to another Site)**
1. Open the machine.
2. Press **Unbind Machine & Site** to free the machine and keep the Site active. Press **Unbind Machine & Deactivate Site** to free the machine and also set the Site to inactive.
3. Confirm the message.
4. To move the machine, now pick the new Site and press **Save Site** as above.

**How to change the products on a machine**
1. In **Product Mapping (current)**, pick the new mapping.
2. Press **Save** (the button reads **Save Vending Machine**, **Save Smart Freezer** or **Save Smart Chiller**).
3. The new product menu is sent to the machine straight away.
4. To prepare a change for later, pick it in **Product Mapping (upcoming)** and save. When it is time, press **Replace Current Product Mapping** to make the upcoming mapping the current one. If the machine still shows the old products afterwards, press **Push Products Info to Machine**.

**How to set where prices come from**
1. In **Is Server Price?**, pick **Server Price** to sell at the Site's Reference Price (RP), or **Machine Price** to sell at the price set on the machine itself.
2. Press **Save**. The machine is told to reload its prices.

**How to put a card terminal on a machine**
1. In **Card Terminal Company**, pick the supplier.
2. In **Card Terminal (Terminal ID)**, pick the terminal. Pick **--- Clear ---** if the machine has no terminal.
3. Optional: set **Card Terminal Bound From** if the terminal was actually fitted earlier. Leave it blank to start from the moment you save.
4. Press **Save**. A line under the picker shows when and by whom the terminal was bound.

**How to send commands to the machine (Advance Control)**
1. Scroll to **Advance Control**.
2. Use the button you need:
   - **Restart VMC**: restarts the machine's controller board. Then wait 1 minute and press **Restart APK** (restarts the touchscreen app). Use this when the card terminal does not respond, or sales stop coming in from cash, card, QR or the screen.
   - **Push Products Info to Machine**: makes the machine reload its product menu now.
   - **Sync APK Settings**: makes the machine reload its UI Setting and settings now. The button shows when the machine last answered.
3. If the problem stays after a restart, someone needs to check the machine on site.

**How to see the machine's screen**
1. Press **View Screen** under **Machine ID#**.
2. The latest picture opens. Press **Capture again** for a fresh one.
3. Nothing appears on the machine's screen. If a customer is using it, the machine waits until they finish (up to a minute), and never interrupts a sale.
4. The window also shows the last 5 pictures. The system takes one by itself at 07:00 and 17:00.

**How to get the machine's log files**
1. Under **APK Logs**, pick the date in **From**.
2. Press **Trigger Log Upload**.
3. When the machine has sent it, the file appears in the table below. Press **Download**.

**Good to know**
- **Machine Type** is set when the machine is created and cannot be changed. It decides which product mappings you can pick.
- **Status** options: **Factory (JB)**, **Active**, **Inactive**, **Disposed**, **Sold**. Happy Hour never runs on an inactive machine.
- Changing **Operator** also moves the bound Site to the new operator.
- **Setting Chart** is required for a vending machine. The **Machine Prefix** list only shows prefixes that belong to the chosen Setting Chart, so pick the chart first.
- A red note "Covers 0 of N active slot(s)" under a mapping means that mapping fits a different machine model. Sales would record no product and no cost.
- Unbinding sets the Site's end date to today and keeps a snapshot of the machine's last status on the Site. With no Site (or a Site with no Reference Price Type), the machine sells at its machine price.
- A Site that comes from the CMS can only be edited in the CMS ("** Customer Data only editable from CMS"). Use **(Click Here)** to open it.
- **Save Site**, **Unbind Machine & Site** and **Unbind Machine & Deactivate Site** only show if your role may change Site bindings. **Save** and the remote buttons need permission to edit machine settings.
- Remote commands do not reach a machine with offline status.
- **View Screen** is greyed out on machines that cannot send a screenshot (for example, older machine apps or small boards). The warning icon next to it explains why.
- **Fan speed signal** switches on by itself after the machine reports a running fan for 60 minutes in a row. Press **Reset fan detection** only after a board or fan was changed or removed.
- **APK Parameter** only shows what the machine reports (Grab, display screen, QR, cash, credit card, HID payment). You cannot change these here. **Not Detected** means the machine has not reported it.
- **End of Month Inventory Snapshots**: download the machine's stock as at 11:59:59 pm on the last day of each month.

**Smart freezer only**
- **Is Server Price?** is always Server Price. A freezer has no machine price.
- The products show as a basket **Planogram**. You can type the qty on hand straight into a slot. Press **Refresh qty** to reload.
- **Remote controls** panel: the machine's current status (online or offline, chamber temperature, each part), plus **Sync now**, door **Test lock** and **Unlock** (unlocking opens with no order, no video and no AI check, and is logged with your name), fan modes, light, **Mute**, **Temperature setpoint**, **Setpoint schedule**, **Compressor control**, camera **Take photo**, **Pull logs**, and machine **Self-check**, **Beep**, **Restart app** and **Reboot Android**. Most commands are refused while a sale is in progress, and all controls are disabled while the machine is offline.
- **Remote card terminal** panel: shows the wireless card terminal's state. Press **Check terminal** to test it. To use one on the freezer, pick it in **Card Terminal (Terminal ID)** and save. The freezer follows within 5 minutes.

**Smart chiller only**
- The model, price (**CityBox Price**) and device link come from the chiller supplier and cannot be edited here.
- **Open Door (Restock)** unlocks the cabinet for restocking. **Pull from CityBox** refreshes status and stock now.
- A "Not loaded in CityBox" warning means a mapped product must first be added to that machine's Pre-Stock Setup in the supplier's OPS Pro. Until then, stock is not pushed for it.

## Machine Management ▸ Machine Alert Parameters

**What it is for**
Change, per machine, how long ConnectVend waits before it sends an offline, power-restored or no-sales alert.

**What you see**
- Filters: **Machine ID**, **Site Name**, operator.
- Columns: **Machine ID**, **Site Name**, **Operator**, **Offline Threshold**, **Power Restored Threshold**, **No Sales Threshold** (each shows **Default** with its value, or **Custom**), **Last Transaction**, **Last Sync**.

**How to change a threshold for several machines**
1. Tick the machines in the list.
2. In **Alert Parameter**, pick **Offline Threshold (mins)**, **Power Restored Threshold (mins)** or **No Sales Threshold (hours)**.
3. Type the new number.
4. Press **Apply**.

**How to go back to the default**
1. Tick the machines and pick the parameter.
2. Tick **Clear override (use default)**.
3. Press **Apply**.

**Good to know**
- The defaults are 50 minutes for offline, 50 minutes for power restored, and 72 hours for no sales.
- The limits are 0 to 1440 minutes, and 0 to 168 hours for no sales.
- The offline threshold sets when the first offline alert goes out. Later alert levels (2 hours, 4 hours and so on) stay the same.

## Machine Management ▸ Happy Hour Campaign

For smart freezers and touchscreen vending machines (big board) only. The badges at the top of the page show the minimum app version each needs.

**What it is for**
Give an automatic discount on one product at a time, in time slots, during set hours. During a slot, the machine shows the discounted product on its welcome screen (the Happy Hour page) and charges the promo price for it. About 2 minutes before each slot, the system picks the product from that machine's live stock.

**What you see**
- **New campaign** button.
- Columns:
  - **Campaign**: figures for the **Last 24h** / **Last 30d**, and **Show graph**.
  - **Status**: **Running now**, **Scheduled**, **Paused** or **Ended**, plus one box per machine with what is on offer right now.
  - **Days**, **Hours** (the dates it runs from and to).
  - **Per Slot**: minutes per slot, and how many top SKUs take turns.
  - **Discount**.
  - **Skip When**: stock floor, minimum units, the bestseller hold.
  - **Machines**.
  - **Action**.
- In each machine box: the Site, its RP, **L24h** (discount given · units sold on that machine in the last 24 hours), and each product on offer with (on hand / capacity), **PRIORITY** if prioritized, and **HH: n** (sold at the promo price in this slot).
- Under the box, the products the machine is NOT offering right now, with a tag: **LOW** (too little stock), **N/A** (not available in the warehouse), **PRICE** (no price, or the discount changes nothing), **COST** (would go below cost), **NEVER** (on the never-discount list), **TOP n** (top seller held back after a Stock In).
- Machine chips in **Machines**: click one for **Lineup now** (**Rank**, **SKU**, **Stock**, **Sold / Day**, **Days of Cover**, **Price**, **Why Not**). ⚠ means the machine cannot run Happy Hour yet (app too old). An exclamation icon means Happy Hour is paused there (see Good to know).

**How to create a campaign**
1. Press **New campaign**.
2. Type a **Name** and keep **Active** ticked.
3. Choose the machines:
   - Tick a **Machine groups** box (**All VM - Big Board** or **All Smart Freezers**) to include every active machine of that kind belonging to the campaign's operator. New machines join by themselves once they qualify.
   - Use **Exclude machines** to leave some out of the group.
   - Add single machines in **Machines** (or **Other machines** when a group is ticked).
4. Set **Discount %** (1–90) and **Duration per slot (minutes)**.
5. Set **Total SKUs** (how many top products take turns) and **At the same time** (how many are on offer together in one slot).
6. Choose **Pick SKUs by**:
   - **Highest stock balance % (qty ÷ capacity)**
   - **Most days of cover (stock ÷ daily sales)**: also set **Sales look back (days)**.
   - **Random (fixed per machine per day)**
7. Set the skip rules: **Skip when stock ≤ (%)** and **Skip when fewer units than**.
8. Choose **Round promo price up to**. The steps follow your currency.
9. Optional: **After Stock In, hold top sellers for (hours)** (0 = off) and **How many top sellers to hold**.
10. Pick the **Days** (or press **Every day**, **Weekdays** or **Weekends**), **Hours from** and **Hours to**, and optionally **Active from** / **Active to** dates.
11. Optional: add products to **Never discount**, or to **Prioritize** (offered first in every other slot, down to the last unit).
12. Press **Save**.

**How to change or stop a campaign**
1. Press **Edit** on the campaign row.
2. Change the fields, or untick **Active** to pause it.
3. Press **Save**.
4. To remove it for good, press **Delete**. Its remaining slots today stop at once.

**How to see how a campaign is doing**
1. Press **Show graph** in the **Campaign** column.
2. Pick **From** / **To** dates, or press **7d**, **14d** or **30d**.
3. You see:
   - **Total sales**: paid for Happy Hour units.
   - **Total $disc**: discount given.
   - **Total qty**.
   - **Avg daily qty / VM**.
   - **Related sales**: the whole of every sale that held a Happy Hour unit.
   - **Non-HH %**: the share of related sales that was not the Happy Hour units.

**Good to know**
- On a vending machine, the promo price applies only to orders placed on the touchscreen. Keypad, cash and coin stay at the normal price.
- A machine bound to a UI Setting ignores Happy Hour and runs its UI Setting only. Happy Hour comes back at the next slot once the machine is unbound from the UI Setting.
- A machine that has no UI Setting but is in an active Happy Hour campaign shows the bear welcome scene by itself.
- Happy Hour never runs on a machine whose status is not Active.
- A product that sells out ends its slot early.
- **Hours to** must be after **Hours from**. A window cannot run past midnight.
- **At the same time** cannot be more than **Total SKUs**.
- Two active campaigns cannot cover the same machine at the same days and hours. A machine picked by name in one campaign wins over a group in another.
- **No discount on SKUs not Available in the warehouse** is on by default. **Allow below unit cost** is off unless you tick it.
- The promo price is never applied below cost unless you allow it, and products with no selling price are skipped.
- **New campaign**, **Edit** and **Delete** only show if your role is allowed to create, change or delete campaigns.

## Machine Management ▸ UI Setting (Marketing & Campaign Remote Setting)

For vending machines with a touchscreen. Smart freezers do not use UI Settings.

**What it is for**
A named set of screen and promotion settings (background media, welcome scene, company and refund details, promotions) that you bind to one or more vending machines. Every machine bound to it shows the same settings.

**What you see (the list)**
- Filters: **Name**, **Machine ID**, **Machine Prefix**.
- Columns: **UI Name**, **Campaign Name**, **Binded Machine ID** (with a count and an unbind icon per machine).
- **Edit** and **Delete** per row. **Delete** is greyed out with **(In Use)** while machines are bound.
- **Create** to add a new one (**Name** and **Remarks**).

**What you see (the edit page)**
- **Name**, **Remarks**.
- **Branding & Display**:
  - **Background Video/ Picture**: **Video**, **Picture** or **Mixed (Pic + Video)**.
  - **Bear welcome screen**: **Yes** or **No**.
  - **Display Text**: the text in the top right corner.
- **Pricing**: **Pricing Source** (for information only), **Enable P2 Price?**, **Disable P1 P2 Cross Group?**.
- **Contact & Support**: **Company Name**, **Company URL**, **Company Address**, **Refund URL**, **Refund Button Wording**, **Support contact num**.
- **System**: **Enable Debug Mode**.
- **Default Media — Pictures & Videos**: upload the background pictures and videos.
- **Show Campaign Section** opens the promotions:
  - **Enable Campaign Advertisement?** with start and end dates, and **Campaign Advertisement Material**.
  - **Enable Label Promo?** with dates.
  - **Campaign Media — Pictures & Videos**.
  - **Campaign Running Text 1** (main page) and **Campaign Running Text 2** (above the soft keypad).
  - Buy 1 Free 1, Buy 2 Free 1, Bundle Discount, Buy 2 / 3 / 4 Get Discount (each with its own on/off, groups or %, and dates).
  - **Campaign Bindings**: attach campaigns made in **Campaign Management ▸ Settings**.
  - **Old Campaign Item Bindings** (Legacy).
- **Show Machine Binding Section** opens the list of bound machines.
- **Push Settings to Binded Machines**.
- **Save Parameter Settings**.

**How to bind machines to a UI Setting**
1. Open the UI Setting (**Edit**).
2. Press **Show Machine Binding Section**.
3. Pick a machine in **Vending Machine** and press **Add**. Repeat for more machines.
4. Press **Save Parameter Settings**. The bound machines get the settings shortly after.

**How to unbind a machine**
1. On the edit page, press the remove icon on the machine's row, then press **Save Parameter Settings**.
2. Or, on the list page, press the unbind icon next to the Machine ID. This takes effect at once.

**How to show a Refund or Complaint button on the machine**
1. Type the link in **Refund URL**.
2. In **Refund Button Wording**, choose **Refund** or **Complaint**.
3. Press **Save Parameter Settings**.

**How to turn on the bear welcome scene**
1. Set **Bear welcome screen** to **Yes**.
2. Press **Save Parameter Settings**. On newer machine apps, the bear scene replaces the banner on the idle screen. It is for display only.

**How to change the background pictures or videos**
1. Under **Default Media — Pictures & Videos**, upload the files.
2. Choose **Background Video/ Picture**. In **Mixed** mode, the machine plays the files in filename order: pictures for 5 seconds each, videos to the end. Name files 01_…, 02_… to set the order.
3. Press **Save Parameter Settings**.

**How to add a promotion campaign**
1. Press **Show Campaign Section**.
2. Under **Campaign Bindings**, pick campaigns in **Campaigns** and press **Add Campaign**.
3. To remove one, press the remove icon on its row.

**Good to know**
- After **Save Parameter Settings**, the bound machines are updated automatically within a short time. **Push Settings to Binded Machines** (or **Sync APK Settings** on the machine's settings page) sends them again now.
- Adding a machine in the binding section is not saved until you press **Save Parameter Settings**.
- A machine can belong to only one UI Setting. The **Vending Machine** list only shows machines that are not bound yet.
- An unbound machine keeps the last settings it received.
- A machine bound to a UI Setting does not run Happy Hour.
- From machine app 325, the refund button shows only when **Refund URL** is filled in.
- **Pricing Source** is not set here. It is set per machine in **Is Server Price?** on the machine's settings page, or on the Site edit page. The bound-machine table shows each machine's choice.
- Recommended media size: 960 wide × 1280 high (portrait). Uploads are resized for you. Pictures are compressed to 1.5 MB or less, and videos converted to MP4 of 4 MB or less (keep clips under about 80 seconds). Animated GIFs are not resized, so keep them under 1.5 MB.
- **Old Campaign Item Bindings** are read only by very old machine apps (below v213). Values there are in cents (500 = 5.00).
- The **Deprecated** section holds old settings kept for older machines only.

## Machine Management ▸ Setting Charts

For vending machines.

**What it is for**
Keep the named, versioned setting charts for vending machines, with their files, and say which machine prefixes belong to each chart. Each vending machine is linked to one chart in **Machines Settings**.

**What you see**
- Filters: **Name**, **Machine Prefix**, **Version**, **Machine Status**, **Setting Chart Status**.
- Columns: **Name** (with Active or Inactive), **Latest Version**, **Desc**, **Compatible**, **Machine Count**, **Latest Attachment**, **Machine Prefixes**.

**How to create a setting chart**
1. Press **Create**.
2. Type the **Name** and **Desc**, and pick the **Operator**.
3. Press **Save**.

**How to edit a chart, add files or bind prefixes**
1. Press **Edit** on the row.
2. Change **Name**, **Latest Version**, **Desc** or **Compatible Setting Chart(s)**.
3. Under **Attachment(s)**, upload files.
4. Under **Machine Prefix Binding**, pick a **Machine Prefix** and press **Add**. Use the remove icon to unbind one.
5. Press **Save**. Use **Deactivate** / **Activate** to switch the chart off or on.

**Good to know**
- A machine's **Current Version** is set on its settings page. The chart's **Latest Version** is shown next to it, so you can see machines that are behind.
- Charts that belong to no operator (global charts) cannot be deleted.
- **Delete** is greyed out with **(Binded)** while machine prefixes are bound to the chart.

## Machine Management ▸ Machine Prefix

**What it is for**
Keep the list of machine prefixes and see which setting chart each belongs to. A vending machine's prefix is picked on its settings page.

**What you see**
- Filters: **Machine Prefix**, **Setting Chart**, **Machine Status**.
- Columns: **Setting Chart**, **Prefix**, **Desc**.

**How to create or edit a prefix**
1. Press **Create**, or **Edit** on a row.
2. Type the **Name** and **Desc**, and pick the **Operator**.
3. Press **Save**.
4. To link the prefix to a setting chart, use **Machine Prefix Binding** on the chart's edit page in **Setting Charts**.

**Good to know**
- **Delete** works only when no machine uses the prefix (disposed machines count too). Move those machines to another prefix first.
- A deleted prefix still shows on past transactions and stock counts. The setting chart is not changed.

## Machine Management ▸ Modem IMEI

**What it is for**
Keep the list of modems by IMEI, see which machine each is in and whether it is online, and restart a modem remotely.

**What you see**
- Filters: **IMEI**, **Modem Type**, **Machine ID**.
- Columns: **IMEI**, **Model**, **Binded Machine**, **Status** (Online or Offline, with the last update time).

**How to add a modem**
1. Press **Create**.
2. Type the **IMEI** and pick the **Modem Model**.
3. Press **Save**.
4. To put it in a machine, open the machine in **Machines Settings**, pick it in **Modem IMEI**, and press **Save**.

**How to restart a modem remotely**
1. Find the modem.
2. Press **Reset** and confirm.

**Good to know**
- **Reset** only shows for modem models that support a remote reset.
- **Delete** is greyed out with **(Binded)** while the modem is in a machine.

## Machine Management ▸ APK OTA Updates

**What it is for**
Upload new versions of the machine app and send them to machines over the air (OTA), a little at a time or to all machines.

**What you see**
- Tabs, one per kind of machine app: **Vending Machine APK**, **Vending APK (Small / No-Touch)**, **Smart Freezer APK**. Each has its own versions.
- **Fleet version spread**: how many machines run each app version. Click a bar to list those machines (**Machine ID**, **Site**, **Status**, **Last OTA check-in**). Click a row to open the machine's settings.
- **Push OTA check to fleet**: tells all active machines on this tab to check for an update now.
- **Upload new build**: **Signed APK file**, **versionCode**, **versionName**, **Rollout (‰, 0–1000)**, **Min supported versionCode**, **Mandatory**, **Release notes**.
- **Releases** table: **Version** (the live one is marked **live**), **Status**, **Rollout**, **Mandatory**, **Size**, **Uploaded**, **Actions** (**Publish**, **Unpublish**, **Delete**).

**What the rollout number means**
The rollout is "how many machines out of 1000" may take the new version.
- 10 = 1% of machines (a small test group).
- 500 = half.
- 1000 = every machine.

Each machine has its own fixed place in that line. When you raise the number, more machines join, and the ones already updated stay updated. **Mandatory** ignores the rollout and sends the version to every machine. Use it only for urgent fixes.

**How to release a new version**
1. Open the right tab.
2. Under **Upload new build**, choose the file. Type the **versionCode** and **versionName** exactly as the build's own numbers.
3. Set a small **Rollout** to start (for example 10), and press **Upload as draft**. Nothing reaches a machine yet.
4. In **Releases**, press **Publish** and confirm.
5. Watch the **Fleet version spread**. Raise the rollout step by step: type the new number in the **Rollout** box and press **save**.
6. After saving the rollout number, you can press **Push OTA check to fleet** so machines check now instead of waiting.

**How to roll back**
1. A version cannot be taken back by publishing an older number. Machines refuse to go down.
2. Upload the previous good build again under a higher **versionCode** and publish that.

**Good to know**
- Machines get the published version with the highest versionCode on that tab.
- The **versionCode** must be new on that tab and higher than the version it replaces. The form uses the number you type and does not read it from the file, so check it.
- Save the rollout number before you press **Push OTA check to fleet**. Machines read the number when they check, and some only check again hours later.
- A published version cannot be deleted. **Unpublish** it first.
- **Delete** removes the file too.
- Only people given the APK OTA permissions see this page. Upload, publish and delete each need their own permission.
