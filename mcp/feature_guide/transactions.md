# Transactions

The Transactions menu shows every sale, which ones counted, the money behind them, customer refund requests, and the smart-freezer AI check.

## How ConnectVend decides what counts as a sale

Read this first. Many questions about these screens come from it.

- A sale has **two separate results**: did the money arrive (**Payment Status**) and did the product come out (**Dispense Status**). The machine reports only whether the product came out. Only the payment provider can confirm the money.
- **Counted as a sale:** a single item that dispensed, and every multiple purchase (a basket counts as a whole, even when one item in it failed).
- **Not counted:** a single item that failed to dispense, a QR payment the provider refunded, a QR payment still waiting for the machine to report the sale, and a smart-freezer card sale still waiting for the AI check (shown as **Pending AI**).
- A failed item in a basket keeps its price. A basket is never partly refunded automatically. If the customer wants money back for that item, they send a refund request.
- **Payment Status left blank does not mean unpaid.** It means no payment provider has confirmed the money yet. Cash is always blank. A card sale stays blank until that day's card settlement report has been uploaded and synced.
- **"Auto-refunded"** means the money already went back to the customer by itself (the payment provider or card terminal returned it). Never pay these customers again.

## Transactions ▸ All Transactions

**What it is for**
The full list of sales from every machine you can see, with totals, payment and dispense results, refunds and campaign labels. The page title reads **Sales Transactions**.

**What you see**
- Boxes at the top:
  - **Total Sales**, split into **Cash**, **Cashless Terminal**, **QR Payment Gateway** and **Delivery Platform (Grab)**, each with its share in %.
  - **Transaction Count (Successful)**: sales that counted, out of all payments.
  - **Multiple Purchases Transaction Count**: split into **From Machine** and **From Delivery Platform (Grab)**.
  - **Total Qty**: **Dispensed**, **Purchased** and the **Rate**.
- **Your products only** (amber box): appears only for users limited to certain products. A basket can contain another owner's items. The list shows the full basket amount, and this box shows the part that belongs to you.
- Main columns:
  - **Order ID** and **Transaction DateTime** (sortable).
  - **Machine ID** (opens the machine on the Operation Dashboard), **Machine Prefix**, **Site** (with any Happy Hour or campaign running on the machine now) and **Operator**.
  - **Channel**, **Product ID**, **Product Name**, **Amount** (sortable) and **Payment Method**.
  - **Payment Status**, **Dispense Status**, **Settle Sync** and **Settlement Date**.
  - **AI Recognition**, **Channels Error**, **Auto-refunded?** and **Refund Request**.
  - **TXN_SRC**, **Campaign Label(s)**, **HID Card ID** and **Voucher**.
- **Multiple purchases** show as a grey header row with one item row under it per product. Each item row has its own channel, product, amount and Dispense Status. The header row's Dispense Status is blank.

**Filters**
- Always shown: **Machine ID** and **Channel ID** (type several separated by commas), **From** and **To**.
- Shown to admin-level staff: **Site**, **Product ID**, **Product Name** and **Operator**.
- Press **Show All Filters** for the rest:
  - **Order ID**, **Channel Errors** (including **Errors Only**) and **Payment Method**. Card sales can be filtered by terminal supplier, for example "Credit Card (Nets)".
  - **Payment Status**: **Paid**, **Settled**, **Refunded**, **Unpaid (goods taken, not paid)**, **Unconfirmed (blank)**, **Retained credit** or **Re-vended**.
  - **Dispense Status**: **Dispensed**, **Failed**, **No TRADE yet (payment pending)** (the machine has not reported the sale yet) or **No TRADE (Error Code NA)** (the machine never reported it).
  - **Auto Refunded?**: **Refunded (any)**, **Auto**, **Manual (RF)** or **Not refunded**.
  - **Settle Sync?**, **Refund Request**, **Is Multiple?**, **Is Member?**, **Member ID**, **HID Card ID** and **Is Voucher?**.
  - **Site Binded?**, **Location Type**, **Machine Prefix**, **Machine Contract**, **Machine Model**, **APK Ver** and **TXN_SRC**.
  - **Campaign Label(s)**: product labels, **★ Happy Hour (all)** or one Happy Hour campaign.

**What the status columns mean**
- **Payment Status**
  - **Paid** (green tick): a QR payment provider confirmed the money.
  - **Settled** (green tick): a card sale was matched in the card settlement report.
  - **Refunded** (red cross): the provider returned the money.
  - **Blank**: not confirmed yet. Cash is always blank.
  - **Retained credit**: the sale was paid from credit the card reader kept after an earlier failed sale. No new money came in.
  - **Re-vended**: the earlier failed sale whose kept credit a later sale used up. The customer got the product, not money.
  - **Unpaid** (smart chiller only): the customer took the goods but the provider has not collected the money. It is not counted as a sale.
- **Dispense Status**
  - **Dispensed** (green tick) or **Failed** (red cross).
  - **Blank**: a multiple purchase (look at its item rows), or the machine never reported the sale. In that case **Channels Error** reads "Machine transaction not found (NA)".
- **Settle Sync**
  - Card sales: a tick once the card settlement report confirmed the sale.
  - QR sales: a tick once the payment provider approved the payment. Hover the tick for the time.
  - Cash: always blank.
- **Settlement Date**: the bank day the card money is paid out and the channel it comes through. It stays blank until a settlement report line covers the sale.
- **Auto-refunded?**
  - A green tick means the money already went back automatically. Hover it to see why.
  - **NETS reversal**: the card report shows the charge was reversed.
  - **NA in NETS**: a failed card sale with no line in the card report. No money was taken, so the sale counts as already refunded.
  - **Server**, **By admin** or **By user**: who triggered a QR refund (the system, a staff member, or the customer through a dispute).
- **Auto refund** (green pill under Payment Method): this card terminal model voids a failed single-item sale by itself. It describes the terminal, not this sale. Whether this sale was refunded is shown in **Auto-refunded?**.
- **Refund Request**: the customer's refund reference (RF-…) with its status, for example **Received**, **Approved**, **Rejected** or **Completed**. Click it to open the request. For a basket, the reference sits on the item the customer claimed.
- **Amount** may carry a label:
  - **Pending AI**: a smart-freezer card sale waiting for the camera check. It is not in the totals yet.
  - **AI**: the camera AI changed the charge from the cart the customer agreed to. Hover it for the details.
  - **TEST**: a smart-freezer Testing Mode sale. It is booked as a sale but takes no stock.
- **AI Recognition** (smart freezer only): the camera-check result.
  - **Correct**, **Took extra**, **Took less**, **Items differ**, **Can't tell** or **Can't check**.
  - While it is running: **Not checked yet**, **Checking…**, **Checked** or **Check failed**.
  - After a person dealt with it: **Adjusted**, **Confirmed**, **Keyed in** or **Hold released**, with how long the check took.
  - Click the badge to open the session on AI Recognition. **▶ Cam 5** plays our own door recording.

**How to find one customer's sale**
1. Type the **Machine ID**, then set **From** and **To** to the day of the purchase.
2. Press **Search**.
3. To narrow further, press **Show All Filters** and set **Payment Method** or **Dispense Status**, or type the **Order ID** if you have it.
4. Look at **Dispense Status** and **Auto-refunded?** before you promise a refund.

**How to see only failed sales**
1. Press **Show All Filters**.
2. Set **Dispense Status** to **Failed**, or set **Channel Errors** to **Errors Only**.
3. Press **Search**.

**How to export to CSV**
1. Set your filters and press **Search**.
2. Press **Export CSV**. The file is built in the background.
3. Wait, then refresh the page. The file appears under **Recent CSV Exports**. Press **Download**.
4. Press the **X** beside an old export to remove it from the list.

**Good to know**
- The dates open on today. Choose **50**, **100**, **200**, **500** or **All** rows per page beside the result count.
- **Reset** clears every filter.
- **Show All Filters** is hidden for product-owner users. **Export CSV** needs the export permission.
- A card sale's Payment Status and Settle Sync change only after the card settlement report for that day is uploaded and synced (see **Transactions ▸ Card Settlement**). Until then, blank is normal.
- A smart-freezer card sale can change amount after the AI check or a staff adjustment. That later change is normal.

## Transactions ▸ Daily Summary

**What it is for**
Sales totals per day and per payment method, for quick daily checks. The page title reads **Transactions Daily Summary**.

**What you see**
- Boxes:
  - **Total Sales**: counted sales, with **Gross** (every payment) under it.
  - **Transaction Count**, with how many were **Successful**.
  - **Refunded**: the count and the **Amount**.
  - **Success Rate**.
- Columns: **Date**, **Payment Method**, **Total Txns**, **Successful**, **Success Rate**, **Total Amount**, **Successful Amount**, **Refunded** and **Refunded Amount**.
- Filters: **Machine ID**, **Channel ID**, **From**, **To**, **Payment Method** and **Payment Received** (**Successful** / **Unsuccessful**).

**How to check one day's sales by payment method**
1. Set **From** and **To** to the day.
2. Press **Search**. You get one row for each payment method used that day.
3. Press **Export CSV** to download the table.

**Good to know**
- Admins and supervisors only.
- "Successful" follows the same rule as All Transactions: dispensed single items plus every multiple purchase.
- Pending and provider-refunded QR payments are left out, as on All Transactions.

## Transactions ▸ Payment Gateway Txn

**What it is for**
Every QR payment as the payment provider recorded it, so you can check whether the machine served a paid customer. The page title reads **Payment Gateway Transactions**.

**What you see**
- Boxes: **Paid Count**, **Paid Amount**, **Dispense Count**, **Refund Count** and **Refund Amount**.
- Columns:
  - **Ref ID**, **Paid At** and **Order ID**.
  - **Dispense Attempted?**: the machine was told to dispense. This does not prove the product came out.
  - **Found in Transactions?**: the machine reported the sale back.
  - **Machine ID**, **Machine Prefix**, **Site**, **Operator**, **Amount**, **Payment Method**, **Error(s)**, **Refunded?** and **QR Ref ID**.
- Filters:
  - **Ref ID**, **Order ID**, **Machine ID**, **QR Ref ID**, **From**, **To**, **Site** and **Payment Gateway**.
  - **Is Dispense?**, **Is Found in Transaction?** and **Is Refunded?**.
- **Paid, not served** (red bar): the provider was paid but the machine confirmed no sale. It appears only when such payments exist.

**How to check a customer who paid by QR and got nothing**
1. Type the **Machine ID**, or the **QR Ref ID** from the customer's payment app.
2. Set the dates, then press **Search**.
3. Look at **Dispense Attempted?**, **Found in Transactions?** and **Refunded?**. If **Refunded?** has a tick, the money already went back.

**How to work the "Paid, not served" list**
1. Press **Show list** on the red bar. The dates widen to the bar's window.
2. Each row says why it is listed: **Machine sent no report** or **Confirmed too late to dispense**.
3. Check with the customer. If they got nothing, refund them at the payment provider. ConnectVend does not send this refund.
4. Press **Show all payments** to go back.

**Good to know**
- Admins, supervisors and technicians only.
- These payments are not counted as revenue.
- **Export Excel** downloads the filtered list.

## Transactions ▸ Txn, Revenue & Settlement

**What it is for**
Follows each sale from the machine, to the money the payment provider confirmed, to what the bank pays in after fees.

**What you see**
- Three boxes:
  - **1 · Vending Transaction**: what the machines reported, split into Card terminal, QR gateway, Cash and Other.
  - **2 · Revenue**: money a payment provider confirmed, before fees. It is split into NETS report, Payrallel (T05) for smart-freezer cards, and QR. It also shows the amounts still **Pending (txn amt)**, **No line in NETS**, **Not verifiable** and **Refunded by rail**.
  - **3 · Settlement Info**: **MDR total** (NETS is an estimate, Omise is the real fee), **Bank-in amount**, and **No rate on file** for revenue with no fee rate.
- Columns, in three groups:
  - The sale: **Order ID / Date Time**, **Machine ID / Site**, **Channel / Product**, **Txn Amt**, **Payment Method** and **Dispense**.
  - The revenue: **Status / Source**, **Revenue Amt** and **Rail Line** (opens the card report).
  - The settlement: **Settlement Date**, **Settlement Amt (bank-in)** and **MDR / Rate %**.
- **Revenue Status** values:
  - **Matched**: the provider confirms the money.
  - **Pending**: the provider has not decided yet.
  - **No line in NETS**: the card report is final and has no line, so no money came in.
  - **Not verifiable**: no report covers this terminal.
  - **Refunded**: the money went back.
  - **Retained credit**: no new money came in.
- Filters: **Date From**, **Date To**, **Machine ID**, **Order ID**, **Operator**, **Payment Rail** and **Revenue Status**.

**How to see how much card money was actually received**
1. Set the dates. Set **Payment Rail** to **Card terminal (NETS / T05)**.
2. Press **Search**. Read box **2 · Revenue** and box **3 · Settlement Info**.
3. Press **Export CSV** for the line-by-line list.

**Good to know**
- Admins and supervisors only.
- Cash, HID and Grab sales have no provider to confirm them, so their revenue column stays blank.
- A refund request paid out by staff is not taken off the revenue here.
- An amber difference under a revenue figure means the provider confirmed a different amount than the machine reported. This is normal for a top-up on retained credit, or a smart-freezer charge decided by the AI.
- Fees marked with * are estimates from the card fee schedule.

## Transactions ▸ Refund Requests

**What it is for**
Every refund request customers send, the system's checks on each one, and the steps to approve, reject and pay them.

### How a refund request arrives

1. The customer opens the refund page. They usually scan the QR or press the refund button on the machine. They can also type the **Machine ID** printed on the machine.
2. They enter their name. Then they choose when they bought (**Today**, **Yesterday** or **Another date**) and how much they paid.
3. ConnectVend lists that machine's sales at that amount on that day. The customer taps their purchase and the problem item(s).
   - A purchase that was already refunded is marked **Already refunded**.
4. They choose what happened, for example "Product did not dispense", "Only part of my order dispensed", "Wrong item dispensed", "Quality issue", "Charged twice" or "Other".
5. They add at least one photo or short video and their email.
6. They choose how to be paid back:
   - Singapore: PayNow mobile number or PayPal email.
   - Indonesia: e-wallet or bank (provider, account number and name), or PayPal.
7. If their purchase is not found, they can send it for **manual review** instead. They then type the time, amount, payment method and items by hand.
8. The request appears on this page as **Received**, with a reference like RF-…. The customer is emailed at each step.

**What you see**
- Status chips at the top with counts: **All**, **Received**, **Pending**, **Rejected**, **Dropped**, **Approved**, **Insufficient Info** and **Completed**. Click one to show only that status.
- Filters:
  - **Search** (reference, machine or email), **Status** (shows all except Completed by default), **Date From** and **Date To**.
  - **Refund Method**, **Payment Method**, **Site Name**, **Channel**, **Product** and **Paid Amount**.
  - **New / Repeat**, **Prod Exit Sensor**, **Error Code**, **Settlement Ref**, **Refund Done** and **Is Sent to Settlement?**.
- Columns, in three groups:
  - **Refund Request**:
    - **Refund ID**, **Machine ID / Site Name** and **Name**.
    - **RF Submitted**, with the transaction time and the delay between them. "pending match" means it is not linked to a sale yet.
    - **Channel / Product Name**, **Paid Amt / Pay Method**, **Refund Amt** and **Refund Method**.
  - **System self-checking**:
    - **Machine L24h # of RF**: how many requests that machine had in the last 24 hours.
    - **New / Repeat?**: Repeat means the same order and channel were claimed before.
    - **Multiple / Single?**.
    - **Auto Refunded?**: Yes means do NOT pay again.
    - **Prod Exit Sensor**: whether the product drop sensor was on.
    - **Error Code**.
  - **Refund Progress**: **Validation** (the status), **Send to Settlement** and **Refund Done?**.
- Card sales can also show **NETS report** badges, for example "Matched in NETS" (the customer was charged) or "NA in NETS" (no money was taken).

**How to check and approve a request**
1. Click the **Refund ID** to open the request.
2. Read the top box, **System validation**:
   - **✓ Channel error detected**: the machine logged a fault, which supports the claim. **⚠ No channel error**: the machine reported a clean drop, so check carefully. **⚠ No machine report**: the machine never reported the sale. This is not a reason to reject.
   - **🔗 Auto-matched** (linked to the exact sale) or **✍ Manual claim** (typed in by the customer).
   - **✓ Not yet refunded** or **↩ Already refunded**.
   - **⚡ Nayax auto-refund**: the terminal refunds by itself, so no payout is needed.
3. Check **System self-checking**, the customer's photos, **Items flagged** and **Related transactions**.
4. For a manual claim, find the sale on All Transactions. Type its **Order ID** under **Match transaction**, then press **🔗 Match Order ID**. Use **✕ Clear match** to undo a wrong match.
5. If needed, set the **Final Refund Amount** under **Overwritten**, then press **Save final amount**.
6. Press **✓ Approve**. The request moves to **Approved** and the customer is emailed that the refund is approved. Use **✉ Preview approval email** first to see the email.

**How to reject or close a request**
- **✕ Reject (No charge / auto-refund)**: the money was already returned, or never taken. The status becomes Rejected and the customer is emailed. No payout is made.
- **✕ Drop (or Ignore)**: for example a double submission. Type a reason, then press **Confirm drop (no email)**. The request stays in the list, struck through. No email is sent.
- **◷ Pending**: only on a Received request. It parks the request while you email the customer yourself for more information. The system sends no email.

**How to pay approved refunds**
1. On the list, tick the **Approved** requests. Only approved requests with an amount above zero can be ticked.
2. For PayNow (Singapore): press **➡ Send to Settlement**. The requests go into a Refund Settlement batch, and you pay them from **Transactions ▸ Refund Settlement**.
3. For PayPal, or e-wallet / bank: pay the customer yourself first. Then press **✓ Mark as Done**. The status becomes **Completed** and the customer gets the completion email.
4. If the payout details are wrong (for example a bad PayPal email), press **⚠ Mark Insufficient Info** instead. No email is sent. Contact the customer, then mark the request done later.

**How to refund a smart-freezer card sale (T05 terminal)**
1. Open the request. The **T05 card refund** box appears for these sales.
2. Use it as on AI Recognition (see below). The money goes straight back to the card.
3. Then reject or drop the claim, so the customer is not paid twice.

**Good to know**
- Admins and supervisors only.
- The sidebar shows how many new requests arrived since you last opened the page.
- **Approve** is hidden when the sale was already auto-refunded. It is also refused while the amount is zero, while the same sale is already being refunded under another request, or after the T05 card was already refunded. A manual claim needs a Final Refund Amount above zero first.
- **Approve**, **Reject** and **Mark as Done** email the customer and cannot be undone.
- **Drop** is the only action you can reverse: open the request and press **↩ Un-drop (restore to queue)**.
- **Overwritten status** forces a status (Received, Approved, Rejected or Dropped) without emailing anyone. It is not possible once the request is in a settlement or completed. The Final Refund Amount is also locked at that point.
- **🗑 Delete ticket (permanent)** removes the request, its photos and its history for good. It is meant for test clean-up only.
- **Retained credit** advice beside Approve: "same card" means the customer already got the item on a retry. "Another card" means a different customer used the stuck credit, so this claim stands. It is advice only. Approve still works.
- Every action is listed in the **Audit trail** at the bottom. Press **✉ View email** there to see what the customer received.
- Use the **Next · older** and **Previous · newer** links to move between requests.
- **Export Excel** downloads the filtered list.

## Transactions ▸ Refund Settlement

For Singapore PayNow refunds (paid by CIMB bulk transfer). PayPal refunds may also appear here as a worklist.

**What it is for**
Batches of approved refunds paid out together, and the record of which were paid.

**What you see**
- Status chips: **Open** and **Closed**. Search by **RST reference** and date.
- List columns: **Settlement**, **Date**, **Payout Group / Operator**, **Status**, **PayNow**, **PayPal**, **Total** and **Done**. **stale** means an old batch still open that should be closed.
- Inside a batch:
  - **PayNow — CIMB bulk transfer**: **Refund ID**, **Machine / Site**, **Amount**, **PayNow** and **Done?**. A red mark means the PayNow number looks invalid.
  - **PayPal — Excel worklist (pay manually)**.
  - **Exported files**, **Actions** and **Audit trail**.

**How to pay a batch**
1. Send approved PayNow requests here from **Transactions ▸ Refund Requests** (**➡ Send to Settlement**).
2. Open the batch. Press **Export CIMB text (PayNow)**, then upload the file at CIMB under Bulk Payment. For PayPal, press **Export Excel (PayPal)** and pay each one on PayPal.
3. After the bank pays: set the **Paid date**, tick the rows that were paid, and press **✓ Mark selected as Completed**. Each customer gets a completion email.
4. If the bank could not pay some rows: tick them and press **Mark selected as Insufficient Info**. No email is sent. They drop out of the payout file. Pay them by hand later, then mark them done on Refund Requests or here.
5. Press **Close settlement** when the batch is final. Approvals after that go into a new batch.

**Good to know**
- Admins and supervisors only.
- **Remove** takes a request out of an open batch and puts it back to Approved.
- **Undo close settlement** reopens a closed batch.
- **Void (empty)** deletes a batch with no requests.
- Marking Completed emails customers and cannot be undone.

## Transactions ▸ Card Settlement

For NETS card terminals.

**What it is for**
You upload the card acquirer's daily report. ConnectVend matches each card line to a machine sale, so card sales can be confirmed, reversals marked as refunded, and missing sales found.

**What you see**
- Upload area: **Provider** and **Settlement Report File**, then the **Upload & Match** button.
- Filters: **Status**, **Cutover From** and **Cutover To**.
- List columns: **Cutover**, **File**, **Status**, **Purchases**, **Matched**, **Queries**, **Duplicates**, **Synced** and **Uploaded**.
- Status values: **Uploaded**, **Matching…**, **Review**, **Synced** and **Failed**.
- "⚠ no hour on N": the file was re-saved in Excel, so those lines were matched less precisely.

**How to upload the daily NETS report**
1. Download the raw daily CSV from NETS MerchantConnect. Do not open and save it in Excel first, because that removes the hour from each time.
2. Choose the **Provider**, pick the file, and press **Upload & Match**. Matching runs in the background.
3. Normally the report then syncs by itself. Open it with **View** to check anything listed under **Queries**.

**How to work a report**
- The row tabs are **Queries**, **Matched**, **Unmatched**, **Ambiguous**, **Reversals**, **Duplicates**, **Machine transaction not found (NA)** and **All rows**.
- For a query line, press **Pick** on the right sale, or type a **Txn ID / Order ID** and press **Assign**. A line with no fitting sale may be a double charge or an offline machine. Read its note.
- Panels at the top may suggest terminals to create, bind or move to another machine. Tick them and confirm. This changes the terminal's machine for every report, not just this one.
- **Rematch** runs the matching again after you fix a binding.
- **Sync N Matched** confirms the matched sales, if the report did not sync by itself.
- **Download original file** or **Download converted time file** gets the file back.

**What a sync changes**
- Matched card sales get the **Settle Sync** tick and **Settled** on All Transactions.
- A reversal line marks its sale **Auto-refunded** with "NETS reversal".
- A charged line with no machine sale becomes a sale with the error "Machine transaction not found (NA)".
- Once the files for a day and the next day are both synced, a failed single card sale with no line is marked **NA in NETS**, meaning already refunded.

**Good to know**
- Admins and supervisors only.
- A synced report cannot be deleted.
- **Undo Sync** puts a report back in review. It removes the confirmations and refund ticks it set, and the NA sales it created that the machine never reported. You can then **Delete** it or sync it again.
- Matching and terminal moves are not undone by Undo Sync.

## Transactions ▸ AI Recognition

For smart freezers only.

**What it is for**
Every freezer door visit is filmed. The supplier's AI (Zijia) counts what the customer took, and this page compares that count with what was paid.

For a card payment on the freezer's T05 terminal, the customer's card is only on hold at first. The final charge follows this check:
- When the AI count matches what was paid, the customer is charged automatically.
- When the AI saw nothing taken, the hold is released automatically and nothing is charged.
- Anything else waits for a person on this page.

QR and NETS sales on the freezer were charged at the machine. The check is information only for them.

**What you see**
- **AI Dashboard** boxes:
  - **Total Sales $**: **Charged $** and **Refund $**.
  - **Transaction Count**: **Successful**, **Void** (hold released), **Machine error** and **Other**.
  - **AI Accuracy**: **Correct qty**, **Adjusted Qty** and **Not checked yet**.
  - **Invoice QR**: how many customers scanned the receipt QR (**Scanned**) and how many saw the final amount (**Saw final amount**).
- **AI's product list**: for each freezer, how many of its products the AI can recognise. Open **SKU Links** to fix gaps.
- **T05 card holds with no AI session**: holds where the AI never got the videos. Nothing is charged until someone presses **Key in charge**.
- **AI status and door error codes**: press **Show** to see what the codes mean.
- Filters: **Search** (order number, device or machine), **Machine ID**, **Status**, **Verdict**, **Processed By**, **Date From** and **Date To**.
- Columns:
  - **Transaction DateTime** and **Order ID** (opens the sale on All Transactions).
  - **Machine / Site / RP**.
  - **Videos Received**: with **▶ Door · Cam** video links and **▶ Ours · Cam 5**, our own recording.
  - **Status**, **Verification**, **Sale / Card charge** and **Customer's copy** (Invoice QR scanned or not).
- Light-blue rows at the top are sales waiting for their video (**Waiting for video**). The check starts when the video arrives.

**What the Status column means**
- **Waiting**, **Sending…**, **Sent to AI**, **Result in**, **Failed**, or **Not sent to AI** (for example a door try that never opened).
- **AI** or **Human** under it says whether the AI answered on its own or a person at the supplier settled it.
- **AI: …** gives the AI's own status. Any code other than 0 means a person must key in a T05 charge.

**What the Verification column means**
There is one line per product: **Item**, **Price**, **Paid**, **AI saw**, **Verdict** and **Adjusted**.
- **✓**: paid and taken.
- **+1** (red): taken but not paid.
- **−1** (amber): paid but not taken.
- **?**: the AI could not check.
- **N/A**: the sale failed, so there is nothing to check.

Other notes in this column:
- **Cannot judge**, **Unknown item** or **No verdict from Zijia**: the AI gave no usable count.
- **AI result not in yet**: the paid items show, and the AI columns fill in later.
- **Door error** and **Door did not complete**: the door failed. A timeline shows each try and how long the customer waited.
- **Adjusted by** (blue): a person changed the count. **Confirmed by** (white): a person agreed with the AI.

**What the Sale / Card charge column means**
- The badge says where the T05 card stands:
  - **T05 hold … · awaiting AI**: still waiting for the AI.
  - **T05 hold … · AI count differs — key in the charge**, or **AI could not judge**, or **AI never answered**: waiting for a person.
  - **T05 charged …**: the customer was charged.
  - **T05 hold … released**: nothing was charged.
  - **T05 charge … unconfirmed — check Payrallel**: the outcome is unknown. Check the card company's portal.
- **Not charged yet**: the count came to more than the hold, and the extra could not be charged.
- **To refund**: an adjusted count shows the customer paid too much.
- **Refunded …**: a refund already sent, with who sent it and when.

**How to adjust the quantity taken (Adjust Qty)**
1. Watch the door videos (**▶** links).
2. Press **Adjust Qty** on the row. It is red when the row needs a person.
3. Set how many of each product really left the freezer with **−** and **+**. Use **Add another product** for an item the AI missed.
4. Read the blue note. It says what will happen to the money:
   - **Card still on hold**: the count you set is charged to the card now. 0 releases the hold.
   - **Card already charged and you counted less**: the difference should go back. The Refund window opens next, and nothing is sent until you press it.
   - **Card already charged and you counted more**: the extra is recorded as owed. A T05 charge cannot be topped up.
   - **Paid at the machine (QR or NETS)**: only the items and stock change. A refund goes through Refund Requests.
5. Press **Next**, then **Confirm**.

**How to key in a charge**
1. Press **Key in charge** on a row or on a hold with no AI session.
2. Type the **Final chargeable amount**. You can also press **Use the hold** or **Use the AI count**. 0 releases the hold.
3. Press **Next**, check the summary, then press **Charge …** or **Release hold**.

**How to refund a T05 card sale**
1. Press **Refund** on a charged row. The **T05 card refund** window shows **Charged**, **Refunded**, **Can still refund** and the **Card**.
2. Choose one of these:
   - Press **Refund** on one product line, choosing the quantity.
   - **Refund the difference** after an Adjust Qty.
   - **Full refund**, or **Refund the rest**.
3. Confirm with **Yes, refund**. The money goes back to the card. Stock does not change.
4. If a refund shows **No answer**, check the card company's portal. Then press **It went through** or **It did not**.

**Good to know**
- Admins and supervisors can view the page and use **Adjust Qty**, **Key in charge** and **Refund**.
- The sidebar shows how many new problems (counts that differ from the payment, and door failures) arrived since you last opened the page.
- A charge or refund is sent to the card company at once and cannot be undone here.
- A keyed-in amount above the hold asks the card company to raise it. If they refuse, only the hold is charged.
- A T05 refund is not possible:
  - on the day of the charge (available from the next day);
  - while an earlier refund has no answer;
  - while the sale still waits for the AI;
  - while a refund request on the same sale is being paid another way. Reject that request first, or the customer is paid twice.
- **Adjust Qty** is closed once the charge has a refund on it, and while a refund request on the sale is open.
- The stock follows a count only when a person accepts it, or when the AI releases the hold. The AI's own verdict is never overwritten. A person's count sits beside it.
- **Claude trial** (purple, where shown): a second AI counts the same videos only for comparison. It never charges, refunds or moves stock. Only admins can change its model.
- **SKU Links** (link at the top) lists freezer products the AI cannot recognise yet. Press **Link this** to connect a product to the supplier's registered item. Linking needs product-edit rights. A wrong link makes the AI name the wrong product and charge the wrong amount.
