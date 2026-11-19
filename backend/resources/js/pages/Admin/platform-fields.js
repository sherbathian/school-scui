const status = values => ['status', 'Status', values, values[0]];
const active = ['is_active', 'Active', 'checkbox', true];
const sort = ['sort_order', 'Sort order', 'integer', 0];
const slug = ['slug', 'Slug', 'required'];
const name = ['name', 'Name', 'required'];
const description = ['description', 'Description', 'textarea'];
const group = ['school_group_id', 'Organisation', 'school_groups', undefined, true];
const plan = ['plan_id', 'Plan', 'plans', undefined, true];
const module = ['module_id', 'Module', 'modules', undefined, true];
const subscription = ['school_subscription_id', 'Subscription', 'school_subscriptions', undefined, true];
const start = ['starts_at', 'Starts at', 'datetime-local', undefined, true];
const end = ['ends_at', 'Ends at', 'datetime-local'];
const limit = [['limit_key', 'Limit key', 'required'], ['limit_value', 'Limit value (blank means unlimited)', 'integer']];
const types = ['school', 'college', 'university', 'training_institute'];

export const platformResources = {
    'organisation-members': ['Organisation members', [
        group, ['user_id', 'User', 'users', undefined, true],
        ['role', 'Role', ['owner', 'group_admin', 'group_accountant', 'group_viewer'], 'owner'], active,
    ]],
    modules: ['Modules', [
        name, slug, description, ['module_group', 'Module group', 'text', 'management'],
        ['applies_to', 'Applies to (all types when empty)', 'multi', types], ['is_core', 'Core', 'checkbox', false], active, sort,
    ]],
    'module-dependencies': ['Module dependencies', [module, ['requires_module_id', 'Requires module', 'modules', undefined, true]]],
    plans: ['Plans', [name, slug, ['price_monthly', 'Monthly price', 'money', 0], ['is_default', 'Default plan', 'checkbox', false], active, sort, description]],
    'plan-modules': ['Plan modules', [plan, module]],
    'plan-limits': ['Plan limits', [plan, ...limit]],
    subscriptions: ['School subscriptions', [
        group, plan, status(['active', 'trial', 'grace', 'expired', 'cancelled']), start, end,
        ['grace_ends_at', 'Grace ends at', 'datetime-local'], ['payment_reference', 'Payment reference'],
    ]],
    'subscription-modules': ['Subscription modules', [
        subscription, module, ['source', 'Source', ['addon', 'trial', 'manual_grant'], 'addon'],
        start, end, ['note', 'Note'],
    ]],
    'subscription-limits': ['Subscription limits', [subscription, ...limit, ['reason', 'Reason']]],
    'demo-requests': ['Demo requests', [
        name, ['institute_name', 'Institute name'], ['institute_type', 'Institute type', types], ['phone', 'Phone', 'required'],
        ['email', 'Email', 'email'], ['city', 'City'], ['branches', 'Branch count', 'integer'],
        ['students_estimate', 'Estimated students', 'integer'], ['message', 'Message', 'textarea'],
        ['source_site', 'Source site', 'text', 'marketing'], status(['new', 'contacted', 'trial', 'converted', 'lost']),
        ['assigned_to_user_id', 'Assigned to', 'users'], [group[0], group[1], group[2]],
    ]],
    'otp-codes': ['OTP activity', [
        ['channel', 'Channel'], ['purpose', 'Purpose'], ['attempts', 'Attempts'], ['expires_at', 'Expires at'],
        ['consumed_at', 'Consumed or invalidated at'],
    ]],
    notifications: ['Notifications', [
        ['notifiable_id', 'Recipient', 'users', undefined, true], ['title', 'Title', 'required'],
        ['message', 'Message', 'textarea', undefined, true],
    ]],
};
