# Admin

Operators, users and their access, Claude (MCP) access, visitor history and screen wording; plus the two Tutorial menus.

## Admin ▸ Operators

**What it is for**
The companies that own and run machines (operators). Each operator has its country, timezone, tax rate, payment setup and the machines it owns.

**What you see**
- Filters: **Name**, **Status**.
- Columns: **Code**, **Name**, **Country**, **Timezone**, status (**Active** / **Inactive**), with **Edit** and **Deactivate** / **Activate**.

**How to add an operator**
1. Press **Create**.
2. Fill in **Code**, **Name**, **Country** and **Timezone**.
3. Type the **GST or VAT Rate (%)**. It is used to work out gross margin in the reports.
4. Add **Remarks** if needed and press **Save**.

**How to edit an operator**
1. Press **Edit** on the operator.
2. Change what you need:
   - **Operator Logo**: upload an image (max 400 kb), keep the existing logo or use the default. A custom logo shows only if logo override is enabled in the system settings.
   - **Code**, **Name**, **Country**, **Timezone**, **GST or VAT Rate (%)**, **Status**, **Remarks**.
   - **Refund Bulk Transfer (CIMB)**: the **CIMB Bank Account No.** and **Bank Account Name** used in the refund bulk-transfer file.
   - **Machine Email Alert User(s)**: users who get email alerts for channel errors, machine offline, power restored and no transactions. Make sure each has a valid email.
   - **Access Product(s)** and **Transaction Access From** (see below).
3. Press **Save**.

**How to set which QR payment options the machines show (Machine Payment Menu)**
1. On the operator's edit page, scroll to **Machine Payment Menu**. It appears once the operator has a QR payment gateway.
2. For each **Payment Option**, choose **Show** or **No Show**. Each change saves at once.
3. The **Machines** list shows each machine's menu: **Up to date**, **Waiting** or **Not checked yet**.
4. If a machine stays on **Waiting**, press **Send to machines again**.

**How to limit what the operator's users can see**
1. **Access Product(s)**: choose whether this operator's users may see all products or only the products you list.
2. **Transaction Access From**: pick a date. Sales before that date are hidden from every user of this operator (transactions, dashboards, reports, exports). Leave blank for no limit.
3. Press **Save**.

**Good to know**
- Deactivate an operator instead of deleting it: inactive operators are hidden across the app.
- Payment gateways (**QR Payment Gateway(s)**), delivery platforms (**Delivery Platform(s)**, for Grab) and **Callbacks** are set up here by admins. Ask your admin to change them.
- Machine Payment Menu: machines on app 321 or later update within a minute. Older apps update at their next restart.
- Creating and deactivating operators is for head-office admins only.

## Admin ▸ Operator Group

**What it is for**
Groups of operators that pay refunds from one shared CIMB bank account.

**What you see**
- Filter: **Code / Name**.
- Columns: **Code**, **Name**, **CIMB Account**, **Operators**, status (**Active** / **Inactive**).

**How to create a group**
1. Press **Create**.
2. Type the **Code** and **Name**.
3. Fill in the shared CIMB account: **Bank Account No.** (10 digits) and **Bank Account Name**.
4. In **Operators in this group**, pick the operators that pay refunds from this account.
5. Tick **Active** and press **Save**.

**Good to know**
- Only admins can create, change or delete groups.

## Admin ▸ Users

**What it is for**
The people who sign in to ConnectVend: their role, operator, and which products and machines they may see.

**What you see**
- Filters: **Name**, **Email**, **Operator**, **Role**, **Is Active?**.
- Columns: name, **Username**, **Email**, **Phone Number**, **Role**, **Belongs to Operator**, status, with **Edit** and **Delete**.

**How to add a user**
1. Press **Create**.
2. Fill in **Name**, **Alias**, **Email**, **Username** and **Password**.
3. Add the **Phone Code** and **Phone Number** if needed.
4. Pick the **Role** and the **Operator**.
5. Press **Save**.

**How to give or change a role**
1. Press **Edit** on the user.
2. Pick the new **Role** and press **Save**. The change applies on the user's next page load; they do not need to log out.

**How to reset a user's password**
1. Press **Edit** on the user.
2. Type a new password in **Password (Override)**. Leave it blank to keep the current password.
3. Press **Save** and tell the user the new password.

**How to stop a user from signing in**
1. Press **Edit** on the user.
2. Press **Deactivate**. Press **Activate** to let them back in.

**How to limit what a user can see**
Access is set in three levels, in this order: operator, products, machines.
1. **Operator**: the user sees only that operator's data.
2. **Access Product(s)**: choose all products the operator can access, or only the products listed. If you choose "only the products listed" and list nothing, the user sees no products at all.
3. **Access Vending Machine(s)**:
   - For a user on all products: add machines to limit them to those machines. No machine listed = all machines.
   - For a user limited to some products: the page lists the machines carrying their products now, with **Machine L30d Sales**, **SKU Start Date** and **SKU L30d Sales**. Switch a machine to **No Show** to hide it. Each switch saves at once.
4. **Transaction Access From**: sales before this date are hidden from this user. It can only be later than the operator's own date, never earlier. Clear it to give full history again.
5. Press **Save**.

**Good to know**
- Users who are not head-office admins can only give operator roles.

## Admin ▸ MCP Access

**What it is for**
Lets a chosen person ask Claude questions about ConnectVend data (read only). Each access is tied to that person's ConnectVend user account.

**What you see**
- Columns: **User**, **Label**, **Token**, **Created**, **Status** (**Active** / **Revoked**), with **Revoke**.

**How to give a person access**
1. Press **Issue token**.
2. Pick the person in **Select a user** and type a **Label** (for example their laptop).
3. Press **Create**.
4. Press **Copy** on the token that appears and give it to the person. It is shown only once.
5. The person adds it in their Claude connector settings.

**How to take access away**
1. Press **Revoke** on the person's row and confirm. Their access stops at once.

**Good to know**
- Issue one token per person. If a token is lost, revoke it and issue a new one.

## Admin ▸ Visitor History

**What it is for**
Who signed in, from where, and which pages they opened.

**What you see**
- Tabs: **Login Sessions** and **Page Views**.
- Filters: **From**, **To**, **User**, **Device**, **IP Address**, **Page**. **Reset** clears them.
- Sessions: **User**, **Login**, **Device**, **Browser**, **IP Address**, **Pages**, **Session Length**, **Ended**. Click a session to see its pages with **Opened** and **Time on Page**.

**How to check what a user did**
1. Pick the **User** and the dates in **From** / **To**.
2. Press **Search**.
3. Open a session to see its page views.

**Good to know**
- History is kept for a limited number of days (shown on the page).
- Time on page marked "measured" comes from the browser; "est." is only the gap until the next page, so it includes idle time.
- **Ended**: **Logged out** = the user pressed Log Out; **Left / closed tab** = they closed it; **Expired (inferred)** = the session went quiet.

## Admin ▸ Translations

**What it is for**
Change the wording of any screen text, in English, Simplified Chinese or Bahasa Indonesia, without waiting for a software update.

**What you see**
- **Language**, **Search** (English or translated text), **Page** (one page's texts at a time), **Show** (**All**, **Not translated yet**, **Changed here**).
- Per text: **English (in the code)**, **Shipped wording**, **Your wording**, and **Shown on** (every place the text appears).

**How to change a label's translation**
1. Pick the **Language**.
2. Search for the English text, or pick the **Page** where it appears.
3. Type the new wording in **Your wording** and press **Save**.
4. Everyone sees it on their next page.

**How to change wording directly on a page**
1. Open the page and press **Edit wording** in the top bar.
2. Click the text you want to change. While edit mode is on, buttons and links do nothing.
3. Type the new wording and press **Save**.
4. Press **Turn off** when you are done.

**How to undo a change**
1. Find the text and press **Reset to default**. The shipped wording comes back.

**Good to know**
- The same English text shares one wording, so a change shows in every place listed under **Shown on**.
- Keep words that start with ":" (such as :count). The system fills them in.
- Tooltips and pop-up boxes cannot be clicked in edit mode. Change them on this page.
- Only admins can change wording.

## Tutorial (with CMS)

**What it is for**
The Resource Center: user guides to download and walkthrough videos.

**What you see**
- A list of topics on the left, for example **Quick User Guide Model-F**, **Vending Machine QAQC**, **Content Management System (CMS)**, **Vending Machine System**, **Machine Explainer**, **TroubleShooting Videos**, **Stock Replenish Guide (Model F)**, **Location Hunter Guide**.
- Each topic has documents (**Download**) and videos you can play on the page.

**How to find a guide**
1. Click the topic on the left.
2. Press **Download** for the document, or play the video.

## Tutorial

**What it is for**
Short videos on how to use ConnectVend, for managers and drivers.

**What you see**
- **Management to know**: Operation Dashboard, Site Management, Machine Management, Data Management, Product Management, job assignment and stock planning, changing to a new SKU and mapping.
- **Drivers to know**: stock refill steps for Model E/F/EG (open the machine, defrost, refill within 15 minutes, clean, update inventory qty to the VMC, clear errors).

**How to watch a video**
1. Find the topic and press **Watch Video**. It opens in a new tab.
