# Product Management

Your product catalogue (photos, costs, price tiers), the mappings that put products into machine channels, warehouse stock and planning, and the categories and labels used to group products.

## Product Management ▸ Products

**What it is for**
The list of every product you sell. Each product holds its code, name, photo, category, unit cost and its selling prices in up to five price tiers (RP1–RP5).

**What you see**
- Filters: **Code**, **Name**, **Operator**, **Is Active?**. Press **Search**.
- Columns: **Code**, **Name**, **Category**, **SubCategory**, **Thumbnail**, **Unit Cost** (before GST), **Reference Price** (tax inclusive, per tier), **Gross Margin**, **Product Label(s)**, active or inactive, and **Edit**.
- Smart freezer and smart chiller setups also show **Artwork**, **Smart Freezer AI Photo** and **Smart Chiller AI Photo**. A product on such a machine that the AI does not know yet is marked red.

**How to add a product**
1. Press **Create**.
2. Choose a photo (image file, max 500 KB), then fill **Code**, **Name** and **Operator** (all three required). Optionally **Desc**, **Lowest Unit**, **Volume/ Weight**, **UOM**, **Category**, **SubCategory**, **Is Healthier Choice**, **Is Halal**, **Nutri Grade**.
3. Press **Save**. The edit page opens, where you add the unit cost and selling prices.

**How to set the unit cost**
1. Open the product with **Edit**.
2. Under **Unit Cost**, enter the cost (before GST) and a **Start Date** (leave blank to start now), press **Add**, then **Save**.
3. Each cost stays in the list with its start date, so older sales keep their old cost.

**How to set or change a selling price**
1. Open the product with **Edit** and go to **Selling Price(s)**.
2. To add a tier: enter the **Amount**, pick the **Ref Price Type** (RP1–RP5), press **Add**, then **Save**.
3. To change a tier: remove the existing row with its delete icon (it is removed at once), then add the new amount for the same **Ref Price Type** and press **Save**.
4. **Show price history** lists every change with when, the old and new price, and who made it.

**How to make a product inactive**
1. Open the product with **Edit**.
2. Press **Deactivate** (or **Activate** to bring it back).

**Good to know**
- Which tier a machine sells at is decided by its site's **Reference Price Type** (on the site's edit page), and only when the machine uses **Server Price**. A machine on **Machine Price** sells at the prices keyed on the machine itself.
- After you save a new price, machines that carry this product on Server Price load the new price by themselves within moments. Changing the name, photo or other menu details is sent to every machine carrying it.
- **Translated Name**: add a name in another language with **Language** + **Translated Name** + **Add**.
- **Campaign Labels** (on the edit page) puts product labels on the product; campaigns use these labels.
- **Qty in Warehouse from**: **CMS API** (stock read from the CMS warehouse system by product code) or **Manual (self-system ledger)** (you key incoming stock yourself on the warehouse page; picks deduct it).
- **Parent SKU (blind housing)**: for a housing that holds no stock itself; after saving, bind its flavours and their weight %.
- Smart freezer only: **Pieces per freezer slot** is how many fit in one basket division (baskets 1 and 4 use half). The **Freezer Welcome Sketch** section manages the drawing shown on the freezer's welcome screen, and **Smart Freezer AI Training** submits the product's photos and barcode so the freezer's camera AI can recognise it.
- Smart chiller only: **Pieces per chiller channel** and **Shelf Life (days)**. Prices of chiller products come from the chiller supplier's price groups and are shown read-only.
- The NEW badge on the machine screen is automatic (see Mappings); there is no setting on the product.

## Product Management ▸ Mappings

**What it is for**
A mapping (planogram) says which product sits in which channel of a machine. Many machines can share one mapping. When a machine is bound to a mapping, its channels take those products, and the machine's screen menu follows it. This is the most important setup step for a new machine.

**What you see**
- Filters: **Name**, **Upcoming Product Mapping**, **Machine ID**, **Site**, **Product**, **Active Product Mapping**, **Machine Type**, **Machine Status**. Press **Search**.
- Columns: **Name**, **Remarks**, **Operator**, **Channel - Product** (every channel and its product), **Binded Vending Machines** (with **RP Tier**, binding date and **Avg Mthly Sales** vs last-30-day sales per machine), **# machines at upcoming stage**, **Menu** (attached menu picture), and the buttons **Edit**, **VM Binding**, **Delete**.

**How to create a mapping**
1. Press **Create**.
2. Enter **Name** (must be unique) and optional **Remarks**, and choose the **Mapping Type**: **Vending Machine** (numbered channels 11, 12, 13…), **Smart Freezer** (basket grid) or **Smart Chiller**.
3. Press **Save**. The mapping's edit page opens.
4. Tip: to start from an existing layout, open that mapping and press **Replicate**. A copy named "…-replicated" is made with the same channels and products.

**How to put products into channels (vending machine)**
1. Open the mapping with **Edit**.
2. In **Vend Channels Product Mapping**, enter the **Channel ID** (the channel number exactly as on the machine, e.g. 11), pick the **Product**, optionally a **Display Sequence**, and press **Add**. The row is saved at once.
3. Repeat for every channel.
4. To remove a product from a channel, press the delete icon on its row (removed at once).
5. To change the display order, change **Display Sequence** on the row.
6. Press **Save** to save the name, remarks, operator and upcoming mapping and to send the menu to the machines.

**How to fill a smart freezer basket layout**
Smart freezer only.
1. Open the mapping with **Edit**; it shows the **Smart Freezer Planogram**.
2. Each basket shows its slots (code = basket + division, e.g. 11, 12, 21). Use the **Divisions** buttons to split a basket into more or fewer slots.
3. In an **Empty** slot, choose **Select product** and press **Bind**. Drag a product to reorder it within its basket.
4. Optionally type a **Real Capacity** (pieces the slot really holds, for this mapping only). Blank uses the product's **Default Capacity**.
5. A product marked **Not on AI** cannot be recognised by the freezer's camera AI until it is trained (Product edit page, **Smart Freezer AI Training**).

**How to apply a mapping to machines (bind machines)**
1. On the Mappings list, press **VM Binding** on the mapping.
2. Under **Vending Machine Binding**, pick a **Vending Machine** and press **Add**. Add as many as needed. Remove a machine with the delete icon on its row.
3. Press **Save**.
4. The machines move onto this mapping at once: their channels take the products, and the machines are told to reload their menu.
- You can also set one machine's mapping on **Machine Management ▸ Machines Settings** (**Product Mapping (current)**).

**How to schedule a mapping change for a later refill (upcoming mapping)**
1. Open the machine's current mapping with **Edit**.
2. Pick the **Upcoming Product Mapping** and, if needed, the **Upcoming Product Mapping Start Date**. Press **Save**. Every machine on this mapping follows it, unless the machine has its own upcoming mapping.
3. To give one machine its own upcoming mapping, set **Product Mapping (upcoming)** on that machine's settings page (**Machine Management ▸ Machines Settings**). The machine's own choice wins over the mapping's.
4. The switch happens when the driver completes a refill job with the stock action **Implement New Mapping** for that machine (**Daily Jobs ▸ Jobs**), and not before the start date. The old products left in the machine are returned to the warehouse automatically.
5. On the Mappings list, **# machines at upcoming stage** shows which machines are queued to switch onto a mapping.

**How to change prices on a mapping's machines**
1. A mapping holds no prices. Prices come from each product's **Selling Price(s)** (Products page), in the tier set on the machine's site (**Reference Price Type**).
2. To change the price everywhere, change the product's price. To change it for one site, change that site's **Reference Price Type**. For machines on **Machine Price**, change the price on the machine itself.

**Good to know**
- Any change to a mapping (add, remove, reorder, save, bind) is sent to the machines on it. Vending machines reload their menu within moments; smart freezers get it at once. If the save message says the refresh could not be sent to some freezers, use **Push Products Info to Machine** on those machines.
- The **Channel ID** must match the machine's own channel numbers. If it does not, the machine's settings page warns "Covers 0 of n active slot(s)" and sales record no product or cost.
- A mapping can only be bound to machines of the same kind (vending machine, smart freezer or smart chiller). **Convert to Smart Freezer** / **Convert to Vending** on the edit page changes the kind, but not while machines of the old kind are bound.
- **Delete** is greyed out while machines are bound ("(Binded)"). Shared mappings with no operator cannot be deleted. **Deactivate** on the edit page marks a mapping inactive; it notes "(Product Mapping(s) still Binded)" when machines are still on it.
- Removing a machine in **VM Binding** clears its channels' products and its upcoming mapping.
- NEW badge: on machines with a recent app, a product that newly enters a machine's mapping shows a red frame and a "★ NEW" tag on the screen. It stays until the product leaves that mapping. There is nothing to switch on.
- **Attachment(s)** lets you upload a menu picture; it shows in the **Menu** column and on the site's edit page.
- Smart chiller: channel codes run 101–599 (first digit = layer); only the chiller supplier's products can be used, and **Real Capacity** can be set per channel.

## Product Management ▸ Warehouse Qty (via API) & Planning

Shown only when your setup reads warehouse stock from the CMS warehouse system.

**What it is for**
Shows how much of each product is in the warehouse, how much drivers have picked, how much the planned refill jobs still need, and how fast it sells, so you can plan purchasing and limit picks.

**What you see**
- Filters: **Product ID/ Code**, **Product Name**, **Is Available?**, **Planning Date**. **Export Excel**. **Unread** / **Me Mentioned** show products with remarks for you.
- Columns: **Available in # of VM** (machines carrying the product, with stock; click for the list), **Daily Sold Qty** (average of the last 7 days, plus yesterday), **Last incoming**, **Qty in Warehouse** (from the warehouse system), **Picked Qty** (not yet synced back), **Remaining Qty** (red when it cannot cover **To Pick Qty**), **To Pick Qty** (for jobs on the planning date), **Needed by # of VM**, **Capped Qty per Channel**, **Available?**, **Remarks**.
- Totals at the bottom: **Total Pcs**, **Total Cost$**, **Stock Value**.

**How to cap how much of a product goes into each channel**
1. Find the product, open **Capped Qty per Channel** and pick a number (0–25), or **No** for no cap.
2. It is the most a channel may hold after a refill, for refills on the planning date and the 4 days after. Use it when stock is short. A cap set by the system shows "from Yesterday"; one set by a person shows who set it.

**How to mark a product unavailable**
1. Click the tick or cross in **Available?**. The name and time of the change show under it.
2. Happy Hour campaigns can skip products marked unavailable.

**Good to know**
- Products whose stock is kept in this system (marked **Self-system ledger**) are managed on the self-system ledger page below.

## Product Management ▸ Warehouse Qty (self-system ledger)

This page is called **Warehouse Qty & Planning** when your setup does not use the CMS warehouse system; then it is your only warehouse page.

**What it is for**
For products whose warehouse stock is kept in ConnectVend: you key in stock coming in, and driver picks deduct it.

**What you see**
- The same planning columns as above, but **Qty in Warehouse** is incoming + adjustments − picks, and **Picked Qty** counts jobs on the planning date.
- Buttons at the top: **Warehouse Log** (every stock movement with machine, job number and user), **Incoming History** (each incoming batch), **Stock Incoming to Warehouse**.

**How to record stock coming in**
1. Press **Stock Incoming to Warehouse**.
2. Fill **Detailed Incoming Batch Number**, **Date** and optional **Remarks**, and add **Attachments** (supplier invoice, DO, photos; PDF, image, Excel or Word, up to 20 MB each).
3. Search the products and key in **Qty (Pieces)** for each. Use a negative number to deduct (correction).
4. Press **Confirm & Save Stock**.
5. Check it later in **Incoming History** (press **View** for a batch, **Export Excel** for the list).

**Good to know**
- A product appears here when its **Qty in Warehouse from** is set to **Manual (self-system ledger)** on the product's edit page (smart chiller products always are).

## Product Management ▸ Categories

**What it is for**
The top-level product categories (the page shows **Product Category**). Every product can have one.

**How to add or change a category**
1. Press **Create**, enter **Name** and optional **Desc**, press **Save**.
2. Press **Edit** on a row to change it, **Delete** to remove it (asks to confirm).

## Product Management ▸ SubCategories

**What it is for**
Sub-categories under a category (the page shows **Product SubCategory**).

**How to add a sub-category**
1. Press **Create**, enter **Name**, optional **Desc**, and pick its **Category**.
2. Press **Save**. **Edit** and **Delete** work per row.

**Good to know**
- Set a product's category and sub-category on the product's edit page.

## Product Management ▸ Product Labels

**What it is for**
Labels you put on products (the page title reads "Product Labels"). Campaigns use them to decide which products a promotion applies to (**Labels X** and **Labels Y** in **Campaign Management ▸ Settings**), and sales show them in the campaign label column.

**How to add a label and use it**
1. Press **Create**, enter **Name** and optional **Desc**, press **Save**.
2. Put the label on products from each product's edit page (**Campaign Labels**).
3. Pick the label in a campaign.

**Good to know**
- A label still used by products cannot be deleted. Remove it from those products first.
