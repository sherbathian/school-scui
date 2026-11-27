const invoice = ['subscription_invoice_id', 'Invoice', 'subscription_invoices', undefined, true];
const school = ['school_id', 'Branch', 'schools', undefined, true];
const date = (name, label) => [name, label, 'date', undefined, true];
const name = ['name', 'Name', 'required'];
const slug = ['slug', 'Slug', 'required'];
const sort = ['sort_order', 'Sort order', 'integer', 0];
const user = ['user_id', 'Account', 'users', undefined, true];

export const serviceResources = {
    'subscription-invoices': ['Subscription invoices', [
        ['invoice_no', 'Invoice number', 'required'], ['school_group_id', 'Organisation', 'school_groups', undefined, true],
        date('period_start', 'Period start'), date('period_end', 'Period end'), date('due_date', 'Due date'),
        ['discount_total', 'Discount (PKR)', 'money', 0], ['status', 'Status', ['draft', 'unpaid', 'paid', 'void'], 'draft'],
        ['subtotal', 'Subtotal (PKR)', 'read-only'], ['total_amount', 'Total (PKR)', 'read-only'], ['issued_at', 'Issued at', 'read-only'],
    ]],
    'subscription-invoice-lines': ['Subscription invoice lines', [
        invoice, school.slice(0, 3), ['module_id', 'Module', 'modules'], ['plan_id', 'Plan', 'plans'],
        ['description', 'Description', 'required'], ['quantity', 'Quantity', 'integer', 1], ['unit_price', 'Unit price (PKR)', 'money', 0],
        ['amount', 'Line total (PKR)', 'read-only'],
    ]],
    'subscription-payments': ['Subscription payments', [
        invoice, ['amount', 'Amount (PKR)', 'money', undefined, true], date('paid_on', 'Paid on'),
        ['method', 'Payment method', ['bank_transfer', 'jazzcash', 'easypaisa', 'cash', 'other'], 'bank_transfer'],
        ['reference', 'Reference'], ['recorded_by_user_id', 'Recorded by (account ID)', 'read-only'],
    ]],
    'school-pages': ['School Pages', [
        school, ['title', 'Title', 'required'], slug, ['page_type', 'Page type', ['custom', 'home', 'about', 'admissions', 'contact'], 'custom'],
        ['content', 'Page content', 'school-page-content'], ['is_published', 'Published', 'checkbox', false],
        ['show_in_menu', 'Show in menu', 'checkbox', false], sort, ['seo_title', 'SEO title'], ['seo_description', 'SEO description', 'textarea'],
    ]],
    'school-site-settings': ['School Setting', [
        school, ['site_theme_id', 'Theme', 'site_themes', undefined, true], ['subdomain', 'Subdomain'],
        ['site_enabled', 'Enable site', 'checkbox', false], ['primary_color', 'Primary color'], ['secondary_color', 'Secondary color'],
        ['font_family', 'Font', ['Instrument Sans', 'Inter', 'DM Sans', 'Source Sans 3', 'system-ui']],
        ['homepage_sections', 'Homepage sections (JSON)', 'textarea'], ['menu_items', 'Menu items (JSON)', 'textarea'],
        ['footer_text', 'Footer text'], ['analytics_id', 'Analytics ID'], ['published_at', 'First enabled at', 'read-only'],
    ]],
    'site-themes': ['Site themes', [
        name, slug, ['description', 'Description', 'textarea'], ['preview_path', 'Preview storage path'], ['version', 'Version', 'text', '1.0.0'],
        ['is_premium', 'Premium', 'checkbox', false], ['is_active', 'Active', 'checkbox', true],
        ['settings_schema', 'Settings schema (JSON)', 'textarea'],
    ]],
    'device-tokens': ['Device registrations', [
        user, ['platform', 'Platform', ['android', 'ios'], 'android'], ['app_version', 'App version'],
        ['last_seen_at', 'Last seen at', 'datetime-local'],
    ]],
    'personal-access-tokens': ['Personal access tokens', [
        name, ['tokenable_id', 'Owner (account ID)', 'read-only'], ['abilities', 'Abilities', 'read-only'],
        ['last_used_at', 'Last used at', 'read-only'], ['expires_at', 'Expires at', 'datetime-local'],
    ]],
};
