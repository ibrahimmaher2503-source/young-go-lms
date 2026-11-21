# Instructor guide

Panel: `/instructor` (**Instructor workspace**). Demo accounts: Sara `instructor.academy@demo.younggo.test` (academy), Karim `instructor.indie@demo.younggo.test` (independent). See [README.md](README.md).

Sidebar: **Dashboard**, **New course**, **Courses** (Courses, Content library), **Review and grading** (Assignment review, Essay review), **Support and messages** (Help requests, Announcements), **Finance and earnings** (My earnings, My statements, Payout details, Instructor agreement), **Reports**, **Lesson assistant** (Assistant test console, Lesson insights, Assistant by course).

## 0. Independent instructors: accept the agreement

1. Open **Instructor agreement**.
2. Read it. Press **I accept this agreement**. Your acceptance is recorded with version, time and IP address.
3. Expect "Agreement accepted. You can create courses."

If you see "Accept this version to keep creating courses", accept first. Pending owner input (D-026): the agreement text is a draft.

Also set **Payout details** (method, holder, account). Saved details are never shown again. Required before the first settlement.

## 1. Course wizard

1. Press **New course**. The wizard has seven steps: **Basic**, **Information**, **Pricing**, **Media**, **SEO**, **Custom fields**, **Finish**. A progress block shows "N of 7 steps complete".
2. **Basic**: title, category, format (**Recorded**, **Live**, **Hybrid**), ownership.
3. **Information**: **Short description**, **Description**, **Level**, **Content language**, **Minimum age**, **Maximum age**, **Requirements**, **What learners will achieve**, **Frequently asked questions**, Arabic and English **web address** (leave empty to build from the title).
4. **Pricing**: **Access mode** (**Free**, **Individual purchase**, **Subscription only**, **Individual purchase or subscription**), **Egypt price**, **Access duration (days)** (empty means lifetime). Note "The academy manages course pricing." may apply to you. Paid access needs protected media for published lessons.
5. **Media**: **Cover image (16:9)** (JPG, PNG, WebP, up to 4 MB, at least 640 px wide) and a promo video link (YouTube, Vimeo or direct https link). The video stays on its platform.
6. **SEO**: SEO title and description in both languages.
7. **Custom fields**: fill required fields, if any.
8. **Finish**: **Review before you finish** lists Basic data, At least one lesson, Required custom fields, Pricing, Cover image, SEO text. Each shows **Ready** or **Needs attention** with a link **Go to step N**.
9. Press **Save draft** any time. Expect "Draft saved. You can come back and continue later."
10. When ready press **Submit for review** and confirm "Send this course to the academy for approval?". Expect "Course submitted for review." The course is locked while pending. If it is rejected you see "Reviewer note" and the course returns to draft.

You cannot **Publish** yourself unless your role has `course.publish` (default: no).

Common errors: "Complete these first: ..." means a checklist item is open. "The course is under review and cannot be edited now."

## 2. Curriculum

1. In **Courses**, press **Curriculum** on the course row.
2. Press **Add section**, type **Section title**.
3. In a section press **Add lesson**. Choose **Lesson type**: Recorded video, Audio, Document, Quiz, Assignment, Live session, Text. Set **Lesson title**, **Duration (minutes)** (typed by hand), **Free preview**.
4. Choose a **Media source**: **Upload a video (protected)**, **Vimeo link (restricted)**, **Bunny Stream link (open)**, **YouTube link (open)**, **Direct video link (open)**, **Approved embed (open)**, **Upload audio (protected)**, **Upload a PDF (protected)**, **Write text**, **Upload an image (protected)**. Open sources on a paid course need an administrator exception.
5. Add lesson materials with **Manage lesson resources**. Mark **Students can download it** if needed.
6. Reorder with **Drag to reorder**, **Move up**, **Move down**.
7. Use **Publish**, **Publish selected**, **Publish section**. Drafts are hidden from learners. **Unpublish** moves back to draft.
8. **Archive** removes an item from view and keeps learner progress. Find it under **Archived lessons**.
9. Press **Student preview** to see what a learner sees. Nothing is saved as progress.

Common errors: "The curriculum changed in another window. Reload the page and try again."

## 3. Upload the lesson PDF

For a recorded lesson:
1. Open the lesson **Edit**. Find **Curriculum PDF**.
2. Upload one PDF: up to 25 MB, 120 pages, no password, no scripts. A new file is a new version. The current version stays in use until the new one is approved.
3. Watch the badge **Assistant readiness** in the curriculum:
   - **Uploaded**, then **Reading the PDF** (**Reading**).
   - **Waiting for text approval** (**Awaiting approval**): next section.
   - **Ready for the assistant** (**Approved**).
   - **PDF needs attention** (**Failed** or **Rejected**).

Common errors and fixes
- "Password-protected PDFs are not accepted." Remove the password and upload again.
- "This PDF contains scripts or embedded files and cannot be accepted." Export a clean PDF.
- "No readable text was found. Upload a text-based PDF." Scanned images need OCR first.
- "The text quality is too low." Upload a cleaner file.

## 4. Test the assistant

1. Open **Assistant test console**. The banner says **Test mode**: test questions are not counted and are never visible to learners.
2. Under **Ask as a learner** choose **Lesson**, **Interface language of the question**, **Learner age group** (Kids, Teens, Adults). Type a question. Press **Ask the assistant**.
3. Read the **Answer**, **Cited pages**, **Supporting quote from the lesson** and **Guard results**. Outcomes: Answered, Not in the lesson, Off topic or blocked, Unavailable.
4. If no lesson is listed: "There are no lessons with an approved lesson document that you can test. Approve a lesson document first."

Pending owner input (O-014): no real AI provider is approved. The console shows "The provider returned an error or is not configured" until then. The demo uses a scripted answer.

## 5. Approve the PDF text (AI readiness)

Approval makes the extracted text the only source the assistant uses.

Permission: **Approve text and activate** needs `lesson_document.approve`. By default the `admin` role holds it. The default instructor roles hold only `lesson_document.upload`. If you cannot approve, ask an administrator (or the owner can grant the permission). Admin can follow the same steps.

1. In the curriculum, on a lesson showing **Waiting for text approval**, press **Review text**.
2. The page **Review lesson PDF text** shows the **PDF** on one side and the **Extracted text** on the other, page by page.
3. Check each page against the PDF. Pages marked "Read by OCR: please check this page" need extra care. Fix text in **Text of page N** and press **Save page text** ("Page text saved.").
4. Under **Key terms for the assistant** tick the terms learners see as quick questions (limit applies). Press **Save key terms**.
5. Press **Approve text and activate**. Expect "Text approved. This version is now active."
6. Readiness changes to **Ready for the assistant**.

Common errors: "The text changed during approval. Review the latest text and try again." / "This version failed checks and cannot be approved." / "A newer version of this PDF has already been approved."

7. Turn the assistant on per course: **Assistant by course**. Set the **Course switch** and the **Assistant** switch per lesson. A lesson also needs the approved PDF, and the global switch must be on (administrator).
8. **Lesson insights**: pick a lesson to see Questions asked, Not in the PDF, Still not understood, Most asked topics. Anonymous and aggregated. Questions the PDF did not cover may mean the PDF misses something.

## 6. Quizzes

1. In the curriculum add a lesson of type **Quiz** (or **Add quiz or assignment**), then open it with **Edit** to reach the quiz page.
2. **Quiz settings**: **Time limit (minutes)**, **Pass mark (% of total marks)**, **Maximum attempts**, **What learners see after submitting** (**Score and correct answers**, **Score only**, **Nothing until graded by staff**), **Shuffle questions and choices**, **Moving on** (move on after submit, or must pass).
3. Press **Add a question**. **Question type**: Single choice, Multiple choice, True or false, Short answer, Essay. Write **Question**, **Choices** with **Correct answer**, **Points**, **Explanation shown after the answer**.
4. Press **Preview** to see the learner view. Nothing is saved.
5. Save. Expect "Quiz settings saved." / "Question saved."
6. Grade essays: open **Review and grading > Essay review**. Open an answer, enter **Points**, press **Grade answer**. Expect "Answer graded." When all are graded use **Release result and answers** if the quiz hides results until staff release them.

## 7. Assignments

1. Add an **Assignment** lesson. Open **Assignment settings**: **Instructions**, **Due date**, **What learners may hand in**, **Largest file (MB)**, **Late submissions** (**Not allowed**, **Allowed**, **Allowed with a penalty**), **Resubmissions allowed** (0 means one submission).
2. Learners hand in work. You get a notice "Assignment submitted".
3. Open **Review and grading > Assignment review**. Open a submission. You see the details, files, **Decisions** and **Feedback thread**.
4. Choose an action:
   - **Grade**: enter **Score (0 to 100)** and **Feedback for the learner**. A late penalty is subtracted automatically.
   - **Accept** or **Reject**.
   - **Request revision**: only if the learner has revisions left.
   - **Add feedback**.
5. Expect "Work graded.", "Work accepted.", "Work rejected." or "Revision requested." The learner is notified.

Common errors: "Enter a score to grade the work." / "The score must be between 0 and 100." / "Write feedback for the learner." / "The learner has no revisions left, so you cannot request one."

## 8. Live sessions

1. Open **Curriculum** and use a **Live session** lesson.
2. Press **Schedule a live session**. Set **Starts**, **Ends**, **Timezone**, **Meeting service** (Zoom, Google Meet, BigBlueButton or other, all by link), **Meeting link** (https), **Join window (minutes before start)**, **Cohort** or **Everyone in the course**, **Instructor**.
3. Learners get "New live session". The link shows only when it is time to join.
4. Before the start press **Open for joining**. Add the meeting link first, or you see "Add the meeting link before opening the session."
5. To move it press **Reschedule** (only before it starts). To cancel press **Cancel session** and confirm.
6. After the session press **Mark as finished**, then **Take attendance** (Present, Late, Absent, Excused). A correction needs a **Reason for corrections**.
7. Press **Create replay lesson**. A draft lesson is created. Add the recording and its PDF, then publish.

Pending owner input (O-021): phone reminder provider. Real email provider also pending.

## 9. Help requests

A learner presses **I still don't understand** in the assistant, which opens a help request for you.

1. Open **Support and messages > Help requests**. You see requests for your courses. Summary: Open requests, Waiting for an instructor, Overdue. Columns include **Reply due** and the badge **Overdue**.
2. Open a request. You see **What was asked**: **Learner's question**, **Assistant's answer**, **Cited pages**, **Video position**, **File version**. Use **Open the lesson** to see the context.
3. Press **Start working**. The status becomes **In progress**.
4. Write **Your reply to the learner** and press **Reply**. Expect "Reply sent." Status becomes **Instructor replied**.
5. If you need more from the learner press **I need an answer from the learner** (status **Waiting for student**).
6. Press **Mark resolved** or **Close request**.

If you see "You cannot change this request." you may not manage it. Pending owner input (O-014 and email): on demo, no AI provider is called and email is not delivered.

## 10. Revisions to a published course

A published course cannot be edited directly.
1. In **Courses** press **Course revisions** on the row, then **Start a revision**. Learners keep the published version.
2. **Edit revision**: change course information or curriculum (add item, reorder). Use **Compare with the published version** to see changes.
3. Press **Submit for review** and confirm. You cannot edit while it waits.
4. An administrator approves or rejects. You see "Reviewer note" on rejection. States: Draft, Pending review, Approved, Rejected, Discarded.
5. Press **Discard revision** to drop it. The published course is unaffected.

Common errors: "This course already has an open revision." / "The course changed since this revision started. Discard it and open a new one."

## 11. Earnings and statements

1. **My earnings** shows only your ledger lines.
2. **My statements** lists monthly statements. Press **Download PDF**. The PDF may fail on environments without headless Chromium: "The statement could not be prepared."
