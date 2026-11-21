# Finance guide

Panel: `/finance` (**Finance workspace**). Demo account: `finance@demo.younggo.test`. See [README.md](README.md).

Sidebar: **Dashboard**; **Sales and enrolment** (Transfer proofs, Refunds, Orders, Payment reconciliation, Subscriptions, Subscription plans, Bundles, Coupons, Manual grants); **Finance and earnings** (Settlement batches, Instructor ledger, Instructor statements, Instructor payout profiles, Commission rules); **Reports**.

Facts to know first
- The app records money. It never moves money. Refunds through a gateway are the only exception, and they need a live gateway.
- **Pending owner input (B-07):** real Paymob credentials and endpoint checks are pending. Live card and wallet payment is not available. On `staging` there is no fake gateway, so a learner who picks card or wallet sees "Online payment is not available yet." Use manual transfer on staging. The fake gateway exists in `local` and `testing` only.
- **Pending owner input (O-007):** legal seller and VAT details. **Pending owner input (O-012):** settlement rules and minimum payouts.
- Receipt and statement PDFs can fail without headless Chromium ("The receipt could not be prepared").
- No real email provider yet: notices to payers are not delivered on demo.

## 1. Dashboard

1. Open **Dashboard**. Tiles: Manual payments to review (with Past deadline), Refund requests to approve, Instructor balances owed, Instructor balances on hold, Settlements not yet paid, Open payment mismatches.
2. Click a tile to open its queue.

## 2. Orders

1. Open **Sales and enrolment > Orders**. Columns: **Reference**, **Learner**, **Amount**, **Payment method** (Bank transfer, Paymob, Test gateway), **Placed**, **Status**.
2. Filter by **Status**. Press **View** to see **Items**.
3. Status values: Awaiting payment, Paid, Failed, Cancelled, Refunded, Partly refunded, Under dispute.

## 3. Paymob payments

1. Orders paid online show **Payment method** Paymob.
2. When a payment succeeds the order becomes **Paid** and access is granted.
3. Check mismatches with **Payment reconciliation** (section 8).
4. Until B-07 closes, treat any Paymob data on demo as test data. Do not claim provider reconciliation or refund through Paymob works.

Common errors seen by learners: "Online payment is not available yet." / "We could not start the payment. Nothing was charged."

## 4. Manual payment proofs

1. Open **Transfer proofs**. Summary: Waiting for review, Overdue, Amount differs. Columns: **Order**, **Payer**, **Sender**, **Reference**, **Amount sent**, **Order total**, **Review due**, **Status**.
2. Press **Open file** to see the screenshot. It is a private link. The sender account is partly masked.
3. Compare **Amount sent** with **Order total**. A difference shows **Amount differs**.
4. Press **Approve**. In "Confirm approval" add a **Note (optional)**. If the amount differs you must write a note. Expect "Payment approved and access granted."
5. Or press **Reject**. Write a **Rejection reason**. The learner sees it and can send a new proof. Expect "Proof rejected."
6. Check the order is **Paid** in **Orders**.

Common errors: "This proof was already reviewed." / "The amount sent differs from the order total. Write a note to approve anyway." / "This order has items that cannot be approved manually yet."

Set the transfer details learners see at **General settings** > **Manual payment details** (super_admin only).

## 5. Coupons, bundles, plans

1. Open **Coupons** and press **New coupon**. Fields: **Code** (3 to 32 letters, digits, dashes), **Name**, **Type** (**Percent** or **Fixed amount**), **Percent off** or **Amount off** with **Currency**, **Starts**, **Ends**, **Total uses**, **Uses per payer**, **Courses** (or **All courses**), **Active**.
2. List columns: Used, Reserved, Discount given, Status (Active, Inactive, Scheduled, Expired, Used up).
3. A used coupon cannot change code, type or value. Make it **Inactive** instead.
4. **Bundles**: **New bundle** with Arabic and English names, a price and 2 to 20 eligible courses. **Weight** is optional (empty splits by course price).
5. **Subscription plans**: **New plan** with **Term** (**Monthly** or **Annual**), price, **Included courses**. **Subscriptions** lists subscribers with **Paid until** and state (Active, Grace period, Cancelled, Expired). **Cancel subscription** keeps access to the end of the period and refunds nothing. Renewal is manual.
6. **Manual grants**: **Grant access** to a free course or **Revoke access**. A reason is required.

Demo coupons: `DEMO-WELCOME10`, `DEMO-FLAT50`.

## 6. Refunds, receipts and credit notes

Learners cannot request a refund. Staff do.
1. Open **Refunds**. Summary: Waiting for review, Approved not yet refunded, Money returned.
2. Press **Request refund**. Fill **Order reference**, **Amount to refund**, **Reason** (at least 10 characters). Expect "Refund requested." Status **Requested**.
3. Approve with **Approve** (needs `payment.refund`). Or **Reject** with a reason. Expect "Refund approved."
4. Press **Refund now**. In "Execute refund":
   - **Through gateway**: money returns through the gateway. It cannot be undone. Needs a live gateway (B-07).
   - **Manual transfer**: send the money by transfer first, then enter **Reference of the money returned** (3 to 64 characters).
5. Expect "Refund executed." Status **Refunded**. The order shows **Refunded** or **Partly refunded**. The instructor ledger gets a reversal.
6. The learner downloads the **Payment receipt** and the **Refund receipt** (credit note) from their payment history.

Common errors: "Only a paid or partly refunded order can be refunded." / "The amount is more than what is left to refund on this order." / "The payment gateway cannot refund this payment right now."

## 7. Ledger, statements and settlements

### Commission rules
1. Open **Commission rules**. Press **Add rule**. Fields: **Sale type** (Individual course, Bundle, Subscription), **Instructor**, **Course**, **Commission type** (**Percentage of the sale** or **Fixed amount**), **Starts on**, **Ends on**, **Note**.
2. A course rule beats an instructor rule, which beats the default (leave instructor and course empty).
3. Rules cannot be edited. Press **End rule** and add a new one.

### Instructor ledger
1. Open **Instructor ledger**. Each paid line makes an **Earning**. Refunds make a **Refund reversal**. Statuses: Accrued, Pending, Approved, Held, Paid, Reversed.
2. **Add adjustment**: amount (negative to deduct) and a reason. It is permanent. Correct a mistake with another adjustment.
3. **Hold entries** leaves entries out of settlement. **Release held entries** puts them back.
4. Select accrued entries (one currency) and press **Add to settlement batch**.

### Statements
1. Open **Instructor statements**. Press **Generate statements**, choose a month that has ended. Leave instructor empty for all. Expect "N new statements were generated."
2. Each statement shows Earnings, Refund reversals, Adjustments, Net for the period, Closing balance. Press **Download PDF**.
3. A negative balance carries forward.

### Settlement batches
1. Open **Settlement batches**. Press **New settlement batch**. Choose "Entries earned up to" (optional) and an instructor (optional). Expect "Settlement REF was created." Status **Draft**.
2. Lines may be left out: "No payout profile on file", "Below the minimum payout, carried forward", "Net is zero or negative, carried forward". Check **Instructor payout profiles** if a line is missing one.
3. Press **Approve**. A different finance user must approve a batch you created (four-eyes): "You created this batch: a different finance user must approve it." The demo has one finance account, so approval needs a second user with `settlement.manage`. Pending owner input (O-012): a super_admin exception may apply.
4. Pay the instructor outside the system. Then press **Record payout**: **Payout method** (Egypt: **Bank transfer**, **Mobile wallet**, **Instapay**; Saudi Arabia: bank transfer only), **Payout reference**, **Paid on**. Instapay references need 6 or more characters. Expect "The payout was recorded." Status **Paid**.
5. **Cancel batch** (before any payout) returns entries to accrued. Give a **Cancellation reason**.

The system sends settlement notices to instructors. Real email is pending.

## 8. Payment reconciliation

1. Open **Payment reconciliation**. It compares our payments with the gateway each day and only lists differences. It never changes an order.
2. See **Last run**, **Day reconciled**, **Gateway transactions**, **Matched**, **Open mismatches**.
3. Types: Transaction we do not know, Amount differs, Paid status differs, Missing at the gateway, Refunded at the gateway.
4. Investigate, then press **Mark reviewed**. Expect "Marked as reviewed."
5. A developer can run `php artisan payments:reconcile --date=YYYY-MM-DD`. On demo it calls no real provider.

Pending owner input (B-07): reconciliation against real Paymob is unverified.

## 9. Reports

1. Open **Reports**. Pick **Report**, **From**, **To**, and filters (Course, Instructor, Market).
2. Finance reports: Sales and revenue, Course and bundle sales by course, Refunds, Instructor earnings and settlements, Payment reconciliation.
3. Press **Export CSV**. Totals are per currency. Over the row limit you see "This report has N rows, more than the export limit". Narrow the dates.
4. Compare the dashboard sales tile with the Sales report for the same period. They should match.

## Daily checklist

1. Transfer proofs: clear Overdue first.
2. Refunds: requests waiting.
3. Payment reconciliation: open mismatches.
4. Monthly: Generate statements, New settlement batch, Approve, Record payout.
