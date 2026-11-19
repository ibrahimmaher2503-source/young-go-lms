-- Synthetic SQLite schema shaped like the legacy Academy LMS installer (docs/migration/INVENTORY.md).
-- Used ONLY for offline pre-work testing until real O-003 legacy DB access arrives.

CREATE TABLE IF NOT EXISTS role (
    id INTEGER PRIMARY KEY,
    name TEXT,
    date_added INTEGER,
    last_modified INTEGER
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY,
    first_name TEXT,
    last_name TEXT,
    email TEXT,
    phone TEXT,
    address TEXT,
    password TEXT,
    skills TEXT NOT NULL DEFAULT '',
    social_links TEXT,
    biography TEXT,
    role_id INTEGER,
    date_added INTEGER,
    last_modified INTEGER,
    wishlist TEXT,
    title TEXT,
    payment_keys TEXT NOT NULL DEFAULT '',
    verification_code TEXT,
    status INTEGER,
    is_instructor INTEGER DEFAULT 0,
    image TEXT,
    temp TEXT,
    sessions TEXT NOT NULL DEFAULT '',
    birth_date TEXT
);

CREATE TABLE IF NOT EXISTS category (
    id INTEGER PRIMARY KEY,
    code TEXT,
    name TEXT,
    parent INTEGER DEFAULT 0,
    slug TEXT,
    date_added INTEGER,
    last_modified INTEGER,
    font_awesome_class TEXT,
    thumbnail TEXT,
    sub_category_thumbnail TEXT
);

CREATE TABLE IF NOT EXISTS course (
    id INTEGER PRIMARY KEY,
    title TEXT,
    short_description TEXT,
    description TEXT,
    outcomes TEXT,
    faqs TEXT NOT NULL DEFAULT '[]',
    language TEXT,
    category_id INTEGER,
    sub_category_id INTEGER,
    section TEXT,
    requirements TEXT,
    price REAL,
    discount_flag INTEGER DEFAULT 0,
    discounted_price REAL,
    level TEXT,
    user_id TEXT,
    thumbnail TEXT,
    video_url TEXT,
    date_added INTEGER,
    last_modified INTEGER,
    course_type TEXT,
    is_top_course INTEGER DEFAULT 0,
    is_admin INTEGER,
    status TEXT,
    course_overview_provider TEXT,
    meta_keywords TEXT,
    meta_description TEXT,
    is_free_course INTEGER,
    multi_instructor INTEGER NOT NULL DEFAULT 0,
    enable_drip_content INTEGER NOT NULL DEFAULT 0,
    creator INTEGER,
    expiry_period INTEGER,
    upcoming_image_thumbnail TEXT,
    publish_date TEXT
);

CREATE TABLE IF NOT EXISTS section (
    id INTEGER PRIMARY KEY,
    title TEXT,
    course_id INTEGER,
    start_date TEXT,
    end_date TEXT,
    restricted_by TEXT,
    "order" INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS lesson (
    id INTEGER PRIMARY KEY,
    title TEXT,
    duration TEXT,
    course_id INTEGER,
    section_id INTEGER,
    video_type TEXT,
    cloud_video_id INTEGER,
    video_url TEXT,
    audio_url TEXT,
    date_added INTEGER,
    last_modified INTEGER,
    lesson_type TEXT,
    attachment TEXT,
    attachment_type TEXT,
    caption TEXT,
    summary TEXT,
    is_free INTEGER NOT NULL DEFAULT 0,
    "order" INTEGER NOT NULL DEFAULT 0,
    quiz_attempt INTEGER NOT NULL DEFAULT 0,
    video_type_for_mobile_application TEXT,
    video_url_for_mobile_application TEXT,
    duration_for_mobile_application TEXT
);

CREATE TABLE IF NOT EXISTS resource_files (
    id INTEGER PRIMARY KEY,
    lesson_id INTEGER,
    title TEXT,
    file_name TEXT,
    created_at TEXT,
    updated_at TEXT
);

CREATE TABLE IF NOT EXISTS question (
    id INTEGER PRIMARY KEY,
    quiz_id INTEGER,
    title TEXT,
    type TEXT,
    number_of_options INTEGER,
    options TEXT,
    correct_answers TEXT,
    "order" INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS quiz_results (
    quiz_result_id INTEGER PRIMARY KEY,
    quiz_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    user_answers TEXT NOT NULL DEFAULT '[]',
    correct_answers TEXT NOT NULL DEFAULT '[]',
    total_obtained_marks REAL NOT NULL DEFAULT 0,
    date_added TEXT NOT NULL,
    date_updated TEXT NOT NULL,
    is_submitted INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS bbb_meetings (
    id INTEGER PRIMARY KEY,
    course_id INTEGER NOT NULL,
    meeting_id TEXT,
    moderator_pw TEXT,
    viewer_pw TEXT,
    instructions TEXT,
    created_at TEXT,
    updated_at TEXT
);

CREATE TABLE IF NOT EXISTS enrol (
    id INTEGER PRIMARY KEY,
    user_id INTEGER,
    course_id INTEGER,
    gifted_by INTEGER NOT NULL DEFAULT 0,
    expiry_date TEXT,
    date_added INTEGER,
    last_modified INTEGER
);

CREATE TABLE IF NOT EXISTS watch_histories (
    watch_history_id INTEGER PRIMARY KEY,
    course_id INTEGER NOT NULL,
    student_id INTEGER NOT NULL,
    completed_lesson TEXT NOT NULL DEFAULT '[]',
    course_progress INTEGER NOT NULL DEFAULT 0,
    watching_lesson_id INTEGER NOT NULL DEFAULT 0,
    quiz_result TEXT NOT NULL DEFAULT '[]',
    completed_date TEXT,
    date_added TEXT,
    date_updated TEXT
);

CREATE TABLE IF NOT EXISTS watched_duration (
    watched_id INTEGER PRIMARY KEY,
    watched_student_id INTEGER,
    watched_course_id INTEGER,
    watched_lesson_id INTEGER,
    current_duration INTEGER,
    watched_counter TEXT
);

CREATE TABLE IF NOT EXISTS payment (
    id INTEGER PRIMARY KEY,
    user_id INTEGER,
    payment_type TEXT,
    course_id INTEGER,
    amount REAL,
    date_added INTEGER,
    last_modified INTEGER,
    admin_revenue TEXT,
    instructor_revenue TEXT,
    tax REAL,
    instructor_payment_status INTEGER DEFAULT 0,
    transaction_id TEXT,
    session_id TEXT,
    coupon TEXT
);

CREATE TABLE IF NOT EXISTS rating (
    id INTEGER PRIMARY KEY,
    rating REAL,
    user_id INTEGER,
    ratable_id INTEGER,
    ratable_type TEXT,
    date_added INTEGER,
    last_modified INTEGER,
    review TEXT
);

CREATE TABLE IF NOT EXISTS certificates (
    id INTEGER PRIMARY KEY,
    student_id INTEGER NOT NULL,
    course_id INTEGER NOT NULL,
    shareable_url TEXT NOT NULL,
    issued_at TEXT
);
