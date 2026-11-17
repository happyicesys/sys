# Data Management

The master lists (serial numbers, models, keys, SIM cards, card terminals, routes, banks and so on) that other screens pick from.

Most screens here work the same way:
- A **Search** area at the top, then a list. Each row has **Edit** and **Delete**.
- **Create** (green button, top right) opens a small form. Fill it in and press **Save**. **Back** closes it without saving.
- **Delete** asks you to confirm. When a row is still used by a machine, **Delete** is greyed out and shows **(Binded)**. Free it on the machine first (Machine Management ▸ Machines Settings), then delete.
- You see the **Create**, **Edit** and **Delete** buttons only if your role allows it. The whole menu needs Data Management access, which is usually admins and senior staff.

## Data Management ▸ Machine Serial No

**What it is for**
The list of machine serial numbers. You add a serial number here first, then pick it on the machine.

**What you see**
- Filters: **Serial Number**, **Machine ID**, **Machine Model**, **Machine Sticker**, **Machine Status**, **Setting Chart**, **Machine Prefix**, **Machine Contract**, **Site name / ID**, **Location Type**, **LCD Monitor**, **Product Mapping**.
- Columns: **Serial Number**, **Remarks**, **Machine Info** (ID, model, status, begin date, prefix, contract) and **Site Info** (site name, postcode, operator).
- **Export Excel** downloads the filtered list.

**How to add a serial number**
1. Press **Create**.
2. Type the **Serial Number** and any **Remarks**.
3. Press **Save**.

**How to put a serial number on a machine**
1. Open **Machine Management ▸ Machines Settings** and open the machine.
2. Pick it in the **Serial Number** field and save.

**Good to know**
- A serial number that is on a machine cannot be deleted. Its **Delete** button shows **(Binded)**.

## Data Management ▸ Machine Models

**What it is for**
The list of machine models, for example a model name for each type of cabinet.

**What you see**
- **Name** and **Desc** for each model.

**How to add a model**
1. Press **Create**.
2. Type the **Name** and any **Remarks**.
3. Press **Save**.

**Good to know**
- You pick the model on the machine in **Machine Management ▸ Machines Settings** (field **Model**). The Sales Report can filter by **Machine Model**.
- A model that is used by any machine cannot be deleted (**(Binded)**).

## Data Management ▸ Machine Key No

**What it is for**
The list of key numbers, so you know which physical key opens which machine.

**What you see**
- Filters: **Key** and **Machine ID**.
- Columns: **Name**, **Remarks**, **Count** (how many machines use this key).

**How to add a key**
1. Press **Create**.
2. Type the **Key** and a **Desc**.
3. Press **Save**.

**Good to know**
- You pick the key on the machine in **Machine Management ▸ Machines Settings** (field **Machine Key**).
- A key that is used by any machine cannot be deleted (**(Binded)**).

## Data Management ▸ Modem Models

**What it is for**
The list of modem models that machines use to go online.

**What you see**
- **Name** and **Alias** for each modem model.

**How to add a modem model**
1. Press **Create**.
2. Type the **Name**, an **Alias (short name)** and a **Desc**.
3. Press **Save**.

**Good to know**
- You pick it on the machine in **Machine Management ▸ Machines Settings** (field **Modem Model**).
- A modem model that is used by any machine cannot be deleted (**(Binded)**).

## Data Management ▸ Card Terminal Company

For setups that take card payments through a card terminal.

**What it is for**
The list of card terminal suppliers (for example Nets or Nayax). Each card terminal belongs to one company.

**What you see**
- **Name** and **Multi-machine** (**Yes** = one terminal can serve several machines, **No** = one terminal, one machine).

**How to add a company**
1. Press **Create**.
2. Type the **Name**.
3. Leave **One terminal can serve several machines** off unless the supplier's terminal really serves more than one machine.
4. Press **Save**.

**Good to know**
- When the switch is off, putting a terminal on a machine takes it off any other machine. When it is on, the terminal stays on every machine it is given.
- Leave it off for NETS terminals. A NETS terminal on two machines cannot be matched to the right machine in the card settlement report.

## Data Management ▸ Card Terminal

For setups that take card payments through a card terminal.

**What it is for**
The list of physical card terminals, each with its Terminal ID and company. You see here which machine each terminal is on now.

**What you see**
- Filters: **Terminal ID**, **Card Terminal Company**, **Machine ID**, **Bound to Machine**, **Auto refund?**, **Batch**, **Remarks**. **Reset** clears them.
- Columns include the terminal, its company, the machine it is on (with when and by whom it was bound), **SIM Card**, **Auto refund?** and **Batch**. Hover to see the last bindings of the terminal.
- **Export Excel** downloads the list.

**How to add a terminal**
1. Press **Create**.
2. Type the **Terminal ID** and pick the **Card Terminal Company**.
3. Optional: pick a **SIM Card**, fill **Batch**, **Auto refund?** and **Remarks**. A Nets-Auresys terminal also has an **Auresys Terminal ID (EZ TID)** field.
4. Press **Save**.

**How to put a terminal on a machine**
1. Open **Machine Management ▸ Machines Settings** and open the machine.
2. Pick the **Card Terminal Company** and **Card Terminal (Terminal ID)**. Set **Card Terminal Bound From** if it went on earlier than now, then save.

**Good to know**
- This screen cannot put a terminal on a machine. That is done on the machine only, so the dates are kept for card settlement.
- **SIM Card** lists only SIM cards that are not in a machine or in another terminal. Clear the field to free the SIM.
- **Auto refund?** says whether this terminal reverses a failed single-item card sale by itself. **Auto** keeps the supplier's answer (or Unknown); **Yes** / **No** override it. It shows as an "Auto refund" badge on sales and refund screens. It does not mean a sale will be refunded.
- A T05 (Payrallel) terminal can only go on a smart freezer. Its extra settings are set up by an admin.
- Deleting a terminal that is on a machine removes it from the machine picker. Its history is kept for card settlement.

## Data Management ▸ Machine Sticker

**What it is for**
Labels you can stick on machines (for example a colour tag for a project or a problem), so staff can spot and filter them.

**What you see**
- **Name**, **Description** and **Colour**.

**How to add a sticker**
1. Press **Create**.
2. Type the **Name** and **Description**, and pick a **Colour**.
3. Press **Save**.

**Good to know**
- You put stickers on a machine in **Machine Management ▸ Machines Settings** (field **Machine Sticker**). Machine Serial No can filter by sticker.

## Data Management ▸ Cashless Terminals

**What it is for**
An older list of cashless terminals, each with a Terminal ID, model and operator.

**What you see**
- Filters: **Terminal ID**, **Cashless Terminal Model**.
- Columns: **Terminal ID**, model, **Provider**.

**How to add a cashless terminal**
1. Press **Create**.
2. Type the **Terminal ID**, pick the model and the **Operator**.
3. Press **Save**.

**Good to know**
- Card terminals used for card settlement are kept under **Data Management ▸ Card Terminal**, not here.

## Data Management ▸ SimCard Package

**What it is for**
The SIM card plans (telco packages) your SIM cards belong to.

**What you see**
- Filters: **Name**, **Status**.
- Columns: **Name**, **Desc**, **Usage API**, **Active / Total** (SIM cards on this package), **Color**, status.

**How to add a package**
1. Press **Create**.
2. Type the **Name** and **Desc**.
3. Pick a **Color (Operation Dashboard badge)**. This colour marks the SIM on the Operation Dashboard.
4. Tick **Default** if new SIM cards should normally use this package.
5. **Usage API**: pick the provider that reports this SIM's live usage, or **None (no live status)**.
6. Press **Save**.

**Good to know**
- Use **Deactivate** instead of deleting a package that is no longer sold. It cannot be deactivated while any of its SIM cards are still in a machine.

## Data Management ▸ SIM Card

**What it is for**
Every SIM card you own, which package it is on, and which machine or card terminal holds it.

**What you see**
- Filters: **Simcard Number**, **Machine ID**, **Site name / ID**, **SimCard Package**, **Status** (the SIM's status as reported by the package's provider).
- Columns include the SIM, its package, machine, **Card Terminal**, **Machine APK**, **Signal Strength** (with the machine **Online** / **Offline**, or **No Link**), activation and expiry dates (**Act** / **Exp**), **Updated By** and **Status**.
- **Excel** downloads the list.

**How to add a SIM card**
1. Press **Create**.
2. Type the **Simcard Number**, pick the **SimCard Package**, and type the **Phone Number** / **MSISDN**.
3. Optional: pick the machine in **Machine ID**.
4. Press **Save**.

**Good to know**
- One SIM, one place: a SIM in a card terminal cannot also be put in a machine, and the other way round.
- You can also pick the SIM on the machine in **Machine Management ▸ Machines Settings** (field **Simcard & Number**).

## Data Management ▸ Machine Contract Type

**What it is for**
The list of contract types a machine can be placed under (for example rental or profit share).

**What you see**
- **Name** and **Desc**.

**How to add a contract type**
1. Press **Create**.
2. Type the **Name** and any **Remarks**.
3. Press **Save**.

**Good to know**
- You pick it on the machine in **Machine Management ▸ Machines Settings** (field **Contract**). The Sales Report and Machine Serial No can filter by it.
- A contract type used by any machine cannot be deleted (**(Binded)**).

## Data Management ▸ Location Types

**What it is for**
The kinds of places where Sites are (for example office, school, condo).

**What you see**
- **Name** of each location type.

**How to add a location type**
1. Press **Create**.
2. Type the **Name**.
3. Press **Save**.

**Good to know**
- You pick it on each Site (**Site Management ▸ Sites**, field **Location Type**).
- Many reports filter by **Location Type**, and **Report ▸ GP by Location Type** groups profit by it.

## Data Management ▸ HID Card

**What it is for**
The list of HID cards (staff or member cards) that can pay at your machines, and which machines each card may be used at.

**What you see**
- Filters: **Card Value**, **Name**, **Email**, **Operator**.
- **Export CSV** downloads the list.

**How to add an HID card**
1. Press **Create**.
2. Type the **Card Value** (the number read from the card), the holder's **Name** and **Email**.
3. Pick the **Operator**.
4. In **Machine(s)**, pick the machines where the card may be used. Leave it empty to allow every machine of that operator.
5. Press **Create**.

**Good to know**
- The machine accepts the card only if it belongs to the machine's operator and, when machines are listed, only at those machines.
- Sales paid with an HID card show the card on Transactions.

## Data Management ▸ Refilling Routes

**What it is for**
The refilling routes (areas) you group Sites into, so drivers and planners can work route by route.

**What you see**
- **Name** of each route.

**How to add a route**
1. Press **Create**.
2. Type the **Name**.
3. Press **Save**.

**Good to know**
- You put a Site on a route on the Site's edit page (**Refilling Routes**). The Operation Dashboard and jobs can filter by route.

## Data Management ▸ Banks

**What it is for**
The list of banks you can choose from, for example in a Site's bank details.

**What you see**
- **Name**, **BIC / SWIFT**, **Country**, and whether it is **Active**.

**How to add a bank**
1. Press **Create**.
2. Type the **Name** and the **BIC / SWIFT Code** (for example DBSSSGSGXXX).
3. Pick the **Country** and tick **Active**.
4. Press **Save**.

**Good to know**
- Banks are chosen on the Site edit page under **Bank Details** (**Bank Name**).
- Untick **Active** to hide a bank without deleting it.
