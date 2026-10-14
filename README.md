# Beulah Toluwalase Portfolio CMS

A self-managed PHP/MySQL portfolio website for Balogun Beulah Toluwalase.

## Main features

- Premium responsive public portfolio
- Admin login
- Editable profile
- Editable services
- Editable experience
- Editable education
- Editable certifications
- Editable leadership
- Editable skills and tools
- Portfolio/project CRUD
- Draft or published projects
- Featured projects
- Cover images
- Multiple images per project
- Multiple videos per project
- HTML5 video playback
- Project categories and filters
- Project URLs
- PDO prepared statements
- CSRF protection
- Password hashing
- Upload validation

## Installation

Requires PHP 7.4+ (8.x recommended) with PDO MySQL, fileinfo and mbstring, plus MySQL/MariaDB.

1. Create a MySQL database in your hosting control panel, select it in phpMyAdmin and import `database.sql`.
2. Put your database details in `config.php` (or, better, create `config.local.php` containing `$dbHost`, `$dbName`, `$dbUser`, `$dbPass` so updates never overwrite them).
3. Upload the entire folder to your PHP hosting account.
4. Make sure PHP can write to `uploads/images`, `uploads/videos` and `uploads/profile`.
5. Open `/admin/login.php`.

## First login (IMPORTANT)

The seeded account is:

- Email: `admin@example.com`
- Temporary password: `ChangeMe@2026`

You are **forced to set a new password** (min. 10 characters) and can change the login email on first sign-in. Later you can change them anytime from Admin -> Account.

Forgot the password? Generate a hash with `php -r "echo password_hash('NEW_PASSWORD', PASSWORD_DEFAULT);"`, update `admins.password_hash` in phpMyAdmin and set `must_change_password` to 1.

## Videos: upload a clip, link the full video

- **Upload:** short clips (MP4 recommended). On InfinityFree each file must be under 10 MB.
- **Video Links:** in the project form, paste YouTube or Vimeo links, one per line. They play directly on the project page. Unlisted videos work too.
- **Description:** any http(s) link you type in a project description is clickable.
- **Existing databases:** the admin upgrades the database automatically the first time you open a project form. If your host blocks that, run `migration_embed_links.sql` in phpMyAdmin.

## Video uploads

Large video uploads depend on the server's PHP configuration. For example:

```ini
upload_max_filesize = 512M
post_max_size = 550M
max_execution_time = 300
max_input_time = 300
```

If your hosting provider does not permit large uploads, use hosted video storage such as a dedicated object/video service and store the external URL instead. The current version is designed for direct server uploads.

## Recommended production security

- Use HTTPS.
- Use a strong admin password.
- Rename or protect the admin area if desired.
- `uploads/.htaccess` already blocks PHP execution on Apache. On Nginx, add an equivalent rule.
- Keep regular database and upload backups.
- Add rate limiting to login.
- Keep PHP and MySQL updated.

## Adding new work

Admin → Portfolio → Add Project.

You can add:

- title
- category
- client
- platform
- tools
- description
- project URL
- cover image
- multiple images
- multiple videos
- featured status
- published/draft status

The public website automatically displays published projects. No code changes are needed for normal content updates."# beulah-portfolio" 
