# Support guide

Panel: `/admin`. Role: `support`. There is no demo account for this role. A `super_admin` adds it to a test account with **People > Users > Actions > Edit roles**. See [README.md](README.md).

Your default permissions: help requests (manage and reply), Learner 360 (view), view as learner, user list (view). The sidebar shows **Dashboard**, **Support and messages > Help requests**, **People > Learner 360** and **Users**.

What you cannot do by default
- Open the **Privacy requests** queue (needs `privacy.manage`, held by admin).
- View as a **child** account (needs `learner.record.sensitive`).
- See Contact enquiries (needs `marketing.lead.view`).
- Change any record. Support is a read and reply role.

## 1. Learner 360

1. Open **People > Learner 360** (ملف المتعلم الشامل).
2. In **Find a learner** type a name, exact email, exact username or account number. Staff accounts are not listed. Expect results, or "No learner found."
3. Open the learner. Sections: **Profile**, **Guardians and linked children**, **Course access**, **Progress**, **Orders and payments**, **Refunds**, **Certificates**, **Help requests**, **Notices sent**, **Privacy requests**, **Devices**, **Audit trail**, **View-as sessions**.
4. Read contact details carefully. Some are masked: "Contact details are masked for your role." For a child account, contact details and birth year show "Hidden for a child account" and devices are hidden.
5. **Course access** shows the **Source** (Free enrollment, Purchase, Bundle, Subscription, Manual grant, Offline payment, Legacy migration) and state (Active, Expired, Revoked, Scheduled). Use it to answer "why can or can't this learner open the course".
6. **Help requests** shows status only. Questions and answers are not shown here. **Notices sent** shows kind and time, not content.
7. Press **New search** to look up another learner.

## 2. View as learner (read only, audited)

Use it to see what the learner sees. You stay signed in as yourself.

1. In Learner 360 press **View as learner**.
2. Read the explainer: read-only, nothing can be saved, the assistant is off, every page is logged.
3. Enter **Reason** (at least 10 characters). It is kept in the audit trail. Confirm.
4. A banner shows "Viewing as NAME. Read-only: nothing you do is saved." with "The session ends at TIME. Every page you open is logged." The session lasts 20 minutes (10 for children).
5. Open **Courses of NAME**. Check **Open** or **Locked** per lesson and **Lesson progress**. "The learner can open this course." appears when access is active.
6. In the admin panel, changes are blocked: "Changes in the panel are blocked until you stop."
7. Press **Stop viewing** when done. The banner disappears. The audit log shows `support.view_as_started` and `support.view_as_stopped`.

Refusals (also audited): You cannot view as yourself; staff accounts; non-learners; inactive or anonymized accounts; accounts with an open deletion request or an open safeguarding flag; child accounts without extra permission ("Child accounts need extra permission."); a second session while one is open ("Stop it first."); a parent in child context.

Never use view as to look around without a reason. Every page you open is logged.

## 3. Help requests

Learners press **I still don't understand** in the lesson assistant. That creates a request for the assigned instructor. As support you see all requests.

1. Open **Support and messages > Help requests**. Summary: Open requests, Waiting for an instructor, Overdue.
2. Filter by status: New, In progress, Instructor replied, Waiting for student, Resolved, Closed. Use **Admin queue** for requests with no instructor.
3. Open a request. Read **Learner's question**, **Assistant's answer**, **Cited pages**, **Video position**. Press **Open the lesson** for context.
4. Press **Start working**. Write **Your reply to the learner**. Press **Reply**. Expect "Reply sent."
5. If you need more information press **I need an answer from the learner**.
6. Press **Mark resolved** or **Close request**.
7. A request shows **Overdue** when no reply came within the response time. Reply first to these. Check **History** for the timeline.
8. If **Safeguarding flags** shows an open flag (for example Self-harm), do not discuss it in the thread. Follow the safeguarding procedure and tell an administrator. You also receive the notice "Safeguarding case needs your attention".

Pending owner input (O-028): safeguarding wording is a draft pending legal review and recipients are not final.
Pending owner input (O-014 and email): on demo no AI provider is called and email is not delivered, so check the in-app list.

## 4. Privacy requests

1. Open the learner in Learner 360 and read **Privacy requests**. It lists request type (Download my data, Correct my data, Delete my account) and status (Waiting for review, Approved, Rejected, Completed, Withdrawn).
2. You cannot approve or complete a request unless an owner gives your role `privacy.manage`. Send it to an administrator: **Privacy requests** queue, **Approve**, then **Complete**.
3. Tell the learner what to expect. Export: a private archive that expires. Deletion: the account is anonymized and orders, payments, receipts and consent records are kept as evidence.
4. A learner with an open deletion request cannot be viewed with view as.

Pending owner input (O-025): retention periods.

## Common situations

- "I paid but have no access": open Learner 360, **Orders and payments**. Pending order with a transfer proof means finance has not approved. Send to finance.
- "I cannot open a lesson": check **Course access** state and dates, then view as the learner to see **Locked** reasons.
- "I did not get the email": real email is pending on demo. On production check the **Notices sent** list.
