# Campaign Management

Promotions that machines apply by themselves (campaigns built on product labels) and voucher codes that customers key in on the machine.

## Campaign Management ▸ Settings

**What it is for**
Create label promotions: a discount that the machine applies automatically to products carrying certain product labels, for a set period. A campaign runs on a machine only after it is attached to that machine's UI Setting.

**What you see**
- Filter: **Name**. Press **Search**.
- Columns: **Name**, **Active**, **Promotion Type**, **Discount Basis**, **Bundle Qty**, **Value**, **Operator**, **Start**, **End**, **Binded Machines**, **Labels X**, **Labels Y**, **Remarks**, and **Edit** / **Delete**.

**How to create a campaign**
1. Make sure the products carry a product label (**Product Management ▸ Product Labels**, then **Campaign Labels** on each product's edit page).
2. Press **+ Create**.
3. Fill **Name**, **Operator** and **Slug (Appear in Machine Display)** (the short text customers see on the machine; required), and optional **Description**.
4. Pick the **Promotion Type**:
   - **Percentage** – a % off; enter the **Discount Percent Value (%)**.
   - **Amount** – an amount off; enter the **Discount Amount Value**.
   - **Absolute** – a fixed promo price; enter the **Absolute Price Value**.
   - **Free Item** – a free product; no value needed.
   - **Qty Tier Discount (Buy 2/3/4 → %)** – a % off when the cart holds 2, 3 or 4 items or more; **Bundle Quantity X** must be 2, 3 or 4 and the value a whole % from 1 to 100.
5. Pick **Labels X** and **Labels Y** (the product labels the promotion works on).
6. Pick the **Discount Basis**: **By Qty**, **By Amount** or **Both**. With By Qty or Both, enter **Bundle Quantity X**.
7. Optionally set **Min Basket Value** and **Max Discount Value**.
8. Set **Start At** and **End At** (End cannot be before Start), then press **Save**.

**How to run a campaign on machines**
1. Open **Machine Management ▸ UI Setting (Marketing & Campaign Remote Setting)** and edit the UI Setting used by the machines.
2. In **Campaign Bindings**, press **Add Campaign** and pick the campaign.
3. The machines bound to that UI Setting are told to reload their settings. The campaign's **Binded Machines** column then lists them.

**How to change or remove a campaign**
1. Press **Edit** on the row, change the fields, press **Save**.
2. To delete, press **Delete**. It is greyed out and marked **(In Use)** while the campaign is attached to a UI Setting; remove it from the UI Setting first.

**Good to know**
- Each operator sees and edits only its own campaigns.
- Happy Hour discounts are set elsewhere (**Machine Management ▸ Happy Hour Campaign**), not here.

## Campaign Management ▸ Voucher

**What it is for**
Create voucher codes that customers key in on the machine to get a free item or a discount. Two kinds: one code shared by many customers (same code), or a batch of different single-use codes (unique code).

**What you see**
- Buttons: **Batch Create (Same Code)** and **Batch Create (Unique Code)**.
- Filters: **Name**, **Code**, **Status**, **Is Same Voucher Code?**. Press **Search**.
- Columns: **Name**, **Desc**, **Code**, **Is Active?**, **Is Same Voucher Code?**, **Qty**, **Date From**, **Date To**, **Type**, **Settings** (**Value**, **Min Basket Value**, **Max Promo Value**), **Edit**. The voucher's **Edit** page also shows **Used Qty**.

**How to create a same-code voucher (one code for many customers)**
1. Press **Batch Create (Same Code)**.
2. Enter the **Voucher Code** (letters and numbers; must not exist yet) and **Max Claimable Qty** (how many times the code can be used in total).
3. Fill **Name**, optional **Desc**, and **Operator**.
4. **Machine(s)**: pick the machines where it works, or leave empty for all machines of that operator.
5. Set **Begin Date** and **End Date**.
6. Pick the **Type**: **Free Item**, **Percent Discount** or **Absolute Amount Discount**. For a discount, enter the **Value** (a % or an amount). Optionally set **Mininum Basket Value** (empty = no minimum) and, for a percent discount, **Maximum Promo Value** (empty = no limit).
7. Under **Product(s)**, pick each product the voucher is for and press **Add**. Tick **Randomize Channel Sequence** if a free item should come from a random channel among those carrying it.
8. Press **Create**.

**How to create unique codes (a batch of single-use codes)**
1. Press **Batch Create (Unique Code)**.
2. Enter **Max Claimable Qty**: this is how many codes are made. Each code works once.
3. Fill the rest as for a same-code voucher (no code to type; the codes are generated).
4. Press **Create**.
5. Open the voucher with **Edit** to see every code with its **Status** (**Active**, **Redeemed**, **Expired**) and **Redeemed At**. Press **Export Excel** to download the codes and hand them out.

**How to make a Free Item voucher**
1. Choose **Type** **Free Item** (no Value needed).
2. Add every product the customer may get free under **Product(s)**.
3. Pick the **Machine(s)** that carry those products, or leave empty for all.

**How a customer uses a voucher on the machine**
1. On the touchscreen, the customer presses the **Voucher** button and keys in the code.
2. Free Item: if the cart is empty, one of the voucher's products is added free. If the customer already picked one of the voucher's products, that product becomes free (the dearest one when there are several).
3. Percent or amount discount: the discount is taken off the basket, within the minimum basket value and maximum promo value.
4. The customer pays the rest as usual (nothing to pay when it is all free). The use is counted when the sale reaches ConnectVend.
5. The machine refuses a code that is not found, already used up, expired, or not valid for that machine or operator.

**Good to know**
- After the **End Date** passes, the voucher and its unused codes are marked **Expired** overnight.
- On **Edit**, only **Name**, **Desc** and **Randomize Channel Sequence** are saved. Code, quantity, dates, type, value, machines and products cannot be changed; create a new voucher instead.
- There is no on/off switch. To stop a voucher before its End Date, open it and press **Delete** (asks to confirm). Its codes stop working.
- A voucher only works on machines that carry at least one of its products in an active channel.
- Vouchers work on touchscreen machines with a recent app. Machines on old apps have no voucher box.
- **Is DCVend?** (under **Audiences**) makes the voucher for members of the DCVend app, by **DCVend Member Type**, with a **Qty** per member and a **Voucher Valid Duration**. Leave it at No for ordinary codes.
