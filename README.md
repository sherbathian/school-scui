# School Finder

Target site `https://sf.total-infotech.com`. This checkout contains the school directory and student community. WordPress and MySQL or MariaDB run on the host. The plugin owns registration, school metadata and search queries. The GeneratePress child theme owns the archive markup and styling.

```text
school-directory-core/
  school-directory-core.php
  includes/
    archive.php
    schools.php
generatepress-sdir/
  archive-school.php
  functions.php
  style.css
README.md
prompt.md
```

## Install on WordPress

1. Confirm the subdomain resolves to the intended host and that the web server virtual host serves WordPress over HTTPS. On an existing install, check that WordPress Address and Site Address use the intended URL before changing settings.
2. Install the free GeneratePress parent theme. Copy `school-directory-core` into `wp-content/plugins/` and `generatepress-sdir` into `wp-content/themes/`.
3. Activate School Directory Core, then activate the School Finder child theme. Activate bbPress for the study forums.
4. In **Settings → Permalinks**, use **Post name** for a new install. On an existing site with pretty permalinks, save the current structure to refresh rewrite rules. Open `/schools/` and add it to the primary navigation. WordPress creates this archive from the school post type.
5. In **Schools**, create Islamabad and Rawalpindi as parent areas and add their sectors as child terms. Create relevant level, board, gender type, medium and facility terms, then add and publish school records. Editors can edit schools using the normal WordPress post permissions. Administrators manage the verified and featured flags.

## Verify on a test installation

- Publish schools in different cities with different fees and facilities. Check that choosing a city includes schools assigned to its child sectors. Select two facilities and verify that both are required.
- Check that a fee filter matches schools whose published fee range overlaps the requested budget. Confirm a school with an unknown bound is excluded when that bound is needed.
- Check fee and date ordering, including schools with no minimum fee. Publish at least 13 records and check that filters persist on page two. Open a copied filter URL in a fresh session.
- Check that an editor cannot change the administrator flags. Confirm that the public REST response does not include the school metadata and the archive does not render phone numbers.
- Check narrow layouts and RTL rendering in an Urdu WordPress test installation.

## Current limits

WordPress runtime and browser checks require an installed instance. Approved reviews populate school ratings. Full Urdu translation, campus coordinate coverage, and production signup email delivery require further work. The CSV importer is available at `scripts/import-schools.php`.

## Maps, comparison, and school claims

- Add campus latitude and longitude in the school editor. The directory map shows the current results page, and a school profile shows its own pin when coordinates exist. Opening a map loads OpenStreetMap tiles. The Near me button requests browser location on demand.
- Select Compare on up to three school cards or profiles, then open Compare in the navigation. Selections persist in the current browser. The comparison URL can be shared.
- Signed-in representatives can submit one claim per school. Administrators review requests under **School claims**. A claim does not change ownership or verification automatically.
- The two initial map pins use campus coordinates from [OpenStreetMap data for Froebel’s F-7](https://www.openstreetmap.org/node/256825235) and [Saint Mary’s campus coordinates](https://en.wikipedia.org/wiki/St_Mary%27s_Cambridge_Higher_Secondary_School). Other locations need confirmation.

## Student notes and book exchange

The navigation links to `/notes/` and `/books/`. Seed class, subject, board and area terms in WordPress before accepting submissions. On upgrades, run `wp eval 'sdir_community_install();'` and `wp rewrite flush`.

Set `SDIR_NOTE_STORAGE` to a directory outside the public web root, writable only by PHP. Back up this directory with the database. The PHP upload limit must be at least 5 MB and the POST limit must allow multipart form overhead.

Moderators review pending posts from **Student notes** and **Book exchange**. Use **Submission details** to inspect an uploaded file or see report counts. Check that submitted content contains no personal contact details before publishing. Book contact email is visible to signed-in visitors while a listing is available.

## Study forums and student dashboard

Install and activate the free bbPress plugin, disable anonymous posting in **Settings → Forums**, and create subject forums and class study groups. Create a published page with slug `student-dashboard`, then flush permalinks. The dashboard displays the signed-in member's recent notes, book listings, reviews, and contribution points.

An approved note earns 10 points and an accepted answer earns 5 points. The topic author or a moderator can accept another member's answer. Demo notes earn no points. The Contributor badge starts with the first award, and Study mentor starts at 25 points. The forum index displays the five highest totals using member numbers.

To seed the original demo PDFs and fictional book listings on another installation, run `wp eval-file scripts/seed-community.php` as the site PHP user with access to the private note storage.

## Local development

Open `http://127.0.0.1:8080/`. Sign in at `/wp-admin/` as `localadmin`. The generated password is in `.local-wordpress/admin-password`.

From the project root, run `./scripts/start-local.sh` to restart the site. This script uses the project MySQL data directory and a private Unix socket. Press Ctrl+C to stop the PHP server. To stop the project database too, run `/usr/local/mysql/bin/mysqladmin --no-defaults --socket="$PWD/.local-wordpress/mysql.sock" shutdown`.

The downloaded WordPress core lives in `wordpress/`. Its custom theme and plugin are linked to the workspace source directories, so edits appear locally without copying files. The local database contains the school CSV and demo community content. To reseed the demos and forums, run `wp --path=wordpress eval-file scripts/seed-local.php` from the project root.

Local self-registration requires the WordPress password setup link before sign-in. With `SDIR_LOCAL_MAIL_LOG` set, WordPress captures mail in the private local log instead of sending it. The editable policy pages can be recreated with `wp --path=wordpress eval-file scripts/seed-policies.php`. Review their wording and add the operator's contact channel before publishing to production.

## School profiles and directory caching

Use a WordPress Gallery block in the school editor to publish authorized campus photos. Enter fee values in the school fields to show the fee table. Public directory queries with one taxonomy filter or fewer use a two-minute transient. School and relevant metadata or taxonomy changes invalidate it.

## Expanded directory setup

Install the free GeneratePress parent and activate the School Finder child theme. Activate School Directory Core and bbPress. Flush permalinks after upgrading.

| Feature | Free components | Setup and code |
| --- | --- | --- |
| Institute listings | WordPress + School Directory Core | Edit existing records under Schools. Assign School, College, University or Training institute in Institute type. Set area, board (curriculum), level, facilities, fee band, fees, admission dates and map coordinates. Legacy unclassified records appear under Schools. |
| Search | School Directory Core | The homepage type tabs submit one term, city and budget to the appropriate archive. The directory retains shareable advanced filters. |
| Short courses | School Directory Core | Use Short courses → Add New. Set the institute post ID, duration, fee, mode and next batch date. Publish after confirming the information with the provider. |
| Reviews and comparison | School Directory Core | Moderate reviews in Comments and set reviewer verification from the comment editor. Select up to three institute cards using Compare. |
| Student community | School Directory Core + bbPress | Seed the class and subject terms. Review notes and books before publishing. Use the existing forum groups and student dashboard. |
| Self-service | School Directory Core | Review claims in School claims. Assign the authorized account in School review representative. It can request profile edits and see enquiries in its dashboard. Staff apply approved edits and can review requests in Institute requests. Replies use the member’s email address. |
| CSV and verification | WP-CLI + School Directory Core | Use the existing CSV format, or append `institute_type,fee_band` to the header. Valid type slugs are `school`, `college`, `university`, and `institute`. Staff can select Confirm verification today in the record editor to save the verification date. |
| SEO, spam and privacy | WordPress + School Directory Core + bbPress | WordPress supplies canonical URLs and sitemaps. The core plugin adds profile schema, limits public requests, protects uploads and filters disallowed public text. Review policy copy and configure production mail before opening public registration. |

New colleges, universities, institutes and courses require source-backed records. The HTML sample data is illustrative.

## Claims, enquiries and signup

WordPress core supplies accounts, password setup, roles, storage, email and WP-Cron. School Directory Core implements the claim review and school-specific authorization, private uploads, enquiry inbox and reminders, signup controls and free Cloudflare Turnstile integration. The child theme renders the forms and inbox. No additional WordPress plugin is required.

- Enable **Settings → General → Anyone can register** to show Sign up. WordPress emails a one-time password setup link, and the account stays locked until a password is set.
- Set `SDIR_TURNSTILE_SITE_KEY` and `SDIR_TURNSTILE_SECRET_KEY` in the server's private WordPress configuration. Allow the site's hostname in the Turnstile dashboard. If these keys are absent, the plugin accepts submissions only when WordPress explicitly reports the `local` environment.
- Point `SDIR_NOTE_STORAGE` at a PHP-writable directory outside the document root for claim proofs and notes. Use HTTPS for the public forms. Update the site's privacy text to explain the collection and handling of proof, enquiry details, and Turnstile.
- Verify WordPress mail delivery and WP-Cron. To test the reminder without waiting, submit an enquiry and run `wp cron event run sdir_enquiry_reminder` on a test site before replying.
- To test claims, submit one as a subscriber, then review it under **School claims** as an administrator. Approve with a reason, verify the badge and test a second claim for the same school. Revoke the approved claim and confirm that the former manager loses access. Existing verification by an administrator is independent.
- To test enquiries, submit as a guest and as a signed-in parent, then reply from **Student dashboard → School enquiries** as the assigned manager or an administrator. Test that a second manager cannot access the first manager's enquiries and that parents see only their own messages.

### Manager directory moderation

School managers use **Student dashboard → Manage directory listings** to propose new schools, colleges, universities, institutes, and short courses, or edits to listings and courses they manage. A course must link to a published listing managed by the submitter. An approved new directory listing is assigned to the submitting manager. Administrator-only verification and featured fields are excluded from proposals.

Administrators use **Directory requests** in WordPress admin. The menu shows the pending count, and new directory requests also trigger an admin email. Approve to publish or apply the edit, or reject with a reason. Managers can see decisions under **My directory requests**. Pending notes and books use the existing WordPress moderation lists, linked under **Directory requests**. Publish to accept or move to Trash to decline.
# school-scui
