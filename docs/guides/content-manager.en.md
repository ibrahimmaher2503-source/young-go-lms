# Content manager guide

Panel: `/admin`. Role: `content_manager`. There is no demo account for this role. A `super_admin` adds it to a test account with **People > Users > Actions > Edit roles**. See [README.md](README.md).

Your role can: manage custom pages, homepage blocks, blog, categories, SEO tags, create and update courses and curriculum, and use the content library. It cannot publish courses (no `course.publish`), see orders, or open Contact enquiries and Newsletter subscribers.

Menu you will see: **Dashboard**, **New course**, **Courses** group (Courses, Content library, Categories), **Website** group (Homepage blocks, Custom pages, Blog posts, Blog categories, Tags), and **Marketing tags** where listed.

## 1. Custom pages (CMS)

1. Open **Website > Custom pages**.
2. Press the create button. Fill: **Title**, **URL slug**, **Body**, **Template** (**Standard** or **Policy page**), **SEO title**, **SEO description**. Fill both Arabic and English.
3. Leave **Status** as **Draft**. Use **Preview** ("Editor preview") to check the page.
4. Replace placeholder text in both languages. Placeholder pages say "Placeholder draft. Replace with owner-approved copy before publishing."
5. Set **Status** to **Published** and save.

Expect: the page appears at its public address. Policy pages (terms, privacy) wait for owner and counsel text.

Common errors: a slug already in use. Choose another.

## 2. Homepage blocks

1. Open **Website > Homepage blocks**.
2. Each block has **Block type**, **Display order**, **Visible on homepage**. Types: Hero, Categories, Featured courses, How it works, Instructors, Testimonials, For parents, Frequently asked questions, Call to action, Free courses, Latest articles, Newsletter.
3. To reorder, change **Display order** and save. To hide, untick **Visible on homepage**.
4. Open the public home page in both languages to check.

## 3. Blog

1. Open **Website > Blog categories** and **Tags** first. Fields: **Name**, **Description**, **Display order**, **Active**.
2. Open **Website > Blog posts** and create a post: **Title**, **Summary**, **Article text**, **URL slug**, **Category**, **Tags**, **Featured image** with **Image description** (alt text), **SEO title**, **SEO description**.
3. Set **Status**:
   - **Draft**: not public.
   - **Scheduled**: choose **Publish time** in the future. The post goes public by itself.
   - **Published**: public now.
4. Check the badge **Public** or **Not public** in the list.

Common errors: "Choose a publish time in the future to schedule this post."

## 4. Categories

1. Open **Courses > Categories**.
2. Create a category: **Name**, **Description**, **URL slug**, **Parent category** (or **Top level**), **Display order**, **Icon**, **Image**, **Status** (**Active** or **Inactive**).
3. Make a category **Inactive** to hide it without deleting.

## 5. Catalog SEO

SEO text lives in several places:
1. **Courses**: in the course wizard step **SEO** (تحسين البحث). The finish checklist item **SEO text** must be ready before a course can be submitted.
2. **Custom pages** and **Blog posts**: **SEO title** and **SEO description**.
3. **Instructor profiles** (needs `instructor.manage`, not in your role by default).
4. **Marketing tags**: Google Analytics 4 and Meta Pixel IDs. Open it, press **Edit**, save. Leave empty on demo. Pending owner input (O-027): consent wording is a draft.

Tip: write a short title (about 60 characters) and a description (about 155 characters) in both languages.

## 6. Courses and curriculum

1. Press **New course** to start the wizard. Steps: Basic, Information, Pricing, Media, SEO, Custom fields, Finish. Press **Save draft** at any point.
2. On **Finish** check the list: Basic data, At least one lesson, Required custom fields, Pricing, Cover image, SEO text.
3. Press **Submit for review**, then confirm. An administrator approves. You cannot **Publish** yourself.
4. In **Courses**, use row actions **Curriculum**, **Course revisions**, **Duplicate**. Curriculum steps are in the instructor guide.
5. A published course is changed through **Start a revision**. Learners keep seeing the published version until it is approved.

## 7. Content library

1. Open **Courses > Content library**.
2. Items are video, audio, PDF, image or file. **Shared with** is **Whole academy** or **Only me**. You see academy items.
3. In a lesson, use "Lesson media from the library", "Add a material from the library" or "Use a library PDF as the curriculum PDF".
4. **Delete** works only for items not used in a lesson. Otherwise: "This item is used by N lesson(s) and cannot be deleted."

## Pending owner input

- Page copy, privacy and terms wording, cookie wording (O-027).
- Real email provider: newsletter and notices are not delivered on demo.
