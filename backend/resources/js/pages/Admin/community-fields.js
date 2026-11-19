const school = ['school_id', 'Branch', 'schools', undefined, true];
const user = ['user_id', 'Account', 'users', undefined, true];
const title = ['title', 'Title', 'required'];
const slug = ['slug', 'Slug', 'required'];
const status = values => ['status', 'Status', values, values[0]];
const description = ['description', 'Description', 'textarea'];
const body = ['body', 'Body', 'textarea', undefined, true];
const start = ['starts_at', 'Starts at', 'datetime-local', undefined, true];
const end = ['ends_at', 'Ends at', 'datetime-local'];

export const communityResources = {
    books: ['Books', [
        title, ['user_id', 'Book owner', 'users', undefined, true], ['author', 'Author'], ['subject', 'Subject'],
        ['city_id', 'City', 'cities', undefined, true], ['area_id', 'Area', 'areas'],
        ['education_level_id', 'Education level', 'education_levels'], ['board_id', 'Board or curriculum', 'boards'],
        ['condition', 'Condition', ['good', 'new', 'fair', 'worn'], 'good'],
        ['listing_type', 'Listing type', ['sell', 'swap', 'donate'], 'sell'], ['price', 'Asking price (sale only)', 'money'],
        description, ['photo_path', 'Photo storage path'], status(['available', 'reserved', 'given', 'removed']),
        ['expires_at', 'Expires at', 'datetime-local'],
    ]],
    'book-inquiries': ['Book inquiries', [
        ['book_id', 'Book', 'books', undefined, true], ['user_id', 'Enquirer', 'users', undefined, true],
        ['message', 'Message', 'textarea', undefined, true], status(['open', 'accepted', 'declined']),
    ]],
    'study-resources': ['Study resources', [
        title, ['user_id', 'Shared by', 'users', undefined, true], school.slice(0, 3), ['subject', 'Subject'],
        ['education_level_id', 'Education level', 'education_levels'], description, ['file_path', 'File storage path'],
        ['download_count', 'Download count', 'integer', 0], status(['pending', 'approved', 'rejected']),
    ]],
    events: ['Events', [
        title, slug, school.slice(0, 3), ['event_type', 'Event type', ['other', 'open_house', 'entry_test', 'admission_drive', 'seminar'], 'other'],
        start, end, ['venue', 'Venue'], ['city_id', 'City', 'cities'], ['registration_url', 'Registration URL', 'url'],
        description, status(['draft', 'published', 'cancelled']),
    ]],
    'listing-leads': ['Listing leads', [
        school, user.slice(0, 3), ['parent_name', 'Parent name', 'required'], ['phone', 'Phone', 'required'],
        ['email', 'Email', 'email'], ['grade_interested', 'Grade or programme'], ['message', 'Message', 'textarea'],
        ['source', 'Source', 'text', 'profile_page'], status(['new', 'forwarded', 'contacted', 'closed', 'spam']),
        ['consent_given', 'Consent recorded', 'checkbox', false],
        ['student_inquiry_id', 'Linked inquiry', 'student_inquiries'],
    ]],
    reviews: ['Reviews', [
        school, user, ['rating', 'Rating (1–5)', 'integer', undefined, true], ['title', 'Title'], body,
        ['relationship', 'Reviewer relationship', ['parent', 'student', 'alumni'], 'parent'],
        status(['pending', 'approved', 'rejected']), ['school_reply', 'School reply', 'textarea'],
    ]],
    'saved-schools': ['Saved schools', [user, school]],
    'school-stats': ['Daily school statistics', [
        school, ['stat_date', 'Date', 'date', undefined, true], ['profile_views', 'Profile views', 'integer', 0],
        ['saves', 'Saves', 'integer', 0], ['compare_adds', 'Comparison adds', 'integer', 0], ['leads', 'Leads', 'integer', 0],
    ]],
    articles: ['Articles', [
        title, slug, ['excerpt', 'Excerpt', 'textarea'], body, ['cover_path', 'Cover storage path'],
        status(['draft', 'published']), ['published_at', 'Published at', 'datetime-local'],
        ['site', 'Site', ['finder', 'marketing'], 'finder'], ['seo_title', 'SEO title'],
        ['seo_description', 'SEO description', 'textarea'],
    ]],
    'student-transfers': ['Student transfers', [
        ['from_school_id', 'Origin branch', 'schools', undefined, true], ['from_student_id', 'Origin student', 'students', undefined, true],
        ['to_school_id', 'Destination branch', 'schools', undefined, true], ['to_student_id', 'Destination student', 'students', undefined, true],
        ['transfer_date', 'Transfer date', 'date', undefined, true], ['reason', 'Reason', 'textarea'],
    ]],
    'student-fee-discounts': ['Student fee discounts', [
        school, ['student_id', 'Student', 'students', undefined, true], ['fee_discount_id', 'Discount rule', 'fee_discounts', undefined, true],
        ['school_session_id', 'Academic year', 'school_sessions'], ['starts_on', 'Starts on', 'date'],
        ['ends_on', 'Ends on', 'date'], ['note', 'Note', 'textarea'],
    ]],
};
