# Bug fixes in this version

**Blocking**
- Seeded admin password hash was invalid, so nobody could log in. Replaced with a valid temporary password and a forced password change; added an Account page (password/email).
- `database.sql` ran `CREATE DATABASE`/`USE`, which fails on shared hosting; now commented out.
- `never` return type removed so the site runs on PHP 7.4/8.0 too.

**Security**
- Delete actions were plain GET links (CSRF-able, even crawler-triggerable). Now POST + CSRF token.
- Uploads are verified by real content (image/video), not just file extension; PHP execution blocked in `uploads/`.
- Session fixation fixed (`session_regenerate_id` on login); session cookie is HttpOnly/SameSite/Secure; logout fully clears the session.
- Login no longer leaks whether an email exists via timing; failed attempts are slowed.
- `javascript:` URLs are rejected for Project URL and LinkedIn.
- `delete_upload` can only remove files inside `uploads/`.

**Data / logic**
- Replacing a cover image deleted the old file before the new upload succeeded; failed saves could leave a broken project. Saves are now transactional and clean up stray files.
- Blank "Order" field crashed content saves (empty string into INT).
- Invalid status value could break saving; now validated.
- Editing a non-existent project silently did nothing; now redirects with a message.
- Project files are removed only after the database rows are deleted.
- Admin could not remove or caption individual project media; added.
- Content list "Details" column was wrong/empty for Education; "Skills & Tools" was labelled "Skills & Tool".
- Required-field rules on content forms now match the database (optional fields can be blank).

**Public site**
- Hero name, tagline and avatar initials were hard-coded; now come from the Profile.
- Missing profile row caused a fatal error; safe fallback added.
- `substr` cut multibyte text mid-character in the meta description; now `mb_substr`.
- Empty phone/email/LinkedIn no longer render dead "Call/Email/LinkedIn" buttons.
- Navigation vanished on mobile; added a menu button. Admin sidebar also vanished on phones.
- Empty project meta no longer renders blank spans; videos get a proper `type`; admins can preview drafts.
- Dashboard "View Website" opened the Work page instead of the home page.

# New in this version
- Video Links field: paste YouTube/Vimeo links (several per project); they play on the project page (privacy-friendly youtube-nocookie player, lazy loaded, responsive).
- Links typed in project descriptions are now clickable.
- Automatic database upgrade for the new "embed" media type (plus `migration_embed_links.sql`).

# Redesign
- New visual identity: ultramarine, sunflower, blush and mint on a cool paper tone; Bricolage Grotesque headings with Source Serif 4 reading text (loaded from Google Fonts).
- Home page hero is now a "content grid" mosaic built from your featured projects and profile photo (falls back to your service names until projects exist).
- Services as a clean list, work as an editorial grid with a large lead project, experience as a timeline with a "current role" marker, skills as grouped tags.
- Names and titles stored in ALL CAPS are displayed in normal capitalisation; initials come from your first and last name.
- Work and project pages redesigned (project facts, full-width video embeds, uncropped images).
- Admin restyled to match, with an active-page highlight in the sidebar and a better phone layout.
- Shared header/footer now live in `site_parts.php`; page code is shorter and consistent.