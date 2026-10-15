# CMMP Production Setup Checklist

This checklist is for deploying the CMMP PHP/MySQL application to a managed PHP hosting environment. It does not deploy automatically.

## 1. PHP compatibility

- Confirm the host runs PHP 8.0+ or a compatible PHP 8.x release. The current workspace evidence reports PHP 8.4.23, which is compatible with the repository’s PHP 8 syntax and strict type usage.
- Confirm that the host enables the PHP PDO extension, the PDO MySQL driver, and the `openssl`/`random_bytes` functions used by CSRF and session security helpers.
- Confirm `php.ini` has `display_errors = Off` and `log_errors = On` in production.
- Confirm `short_open_tag`, `allow_url_include`, and `register_globals` are disabled or not used.
- Confirm the host can execute the same PHP version used in development.

## 2. MySQL compatibility

- Confirm the host provides MySQL 8+ or MariaDB that is compatible with MySQL 8 syntax.
- Confirm the database schema file in `database/schema.sql` is imported into the production database.
- Confirm the database uses `utf8mb4` and `utf8mb4_unicode_ci` or an equivalent production-safe collation.
- Confirm the DB user has least privilege access: only the privileges required to run CRUD, migration, and reporting queries; do not use the root account in production.
- Confirm foreign keys and indexes are created and that `ENGINE=InnoDB` is used.

## 3. Hosting server database configuration

- Create a production database name, production DB user, and database password on the server.
- Store database credentials only in the host’s environment or a local secrets vault; never commit them to the repository.
- Configure the app’s database environment variables or equivalent host config, using placeholders such as `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD`.
- Make sure the DSN uses `mysql:host=<host>;port=<port>;dbname=<name>;charset=utf8mb4` and PDO error mode is exceptions.
- Confirm the database can accept the application’s connection timeout and SSL options where the host permits them.

## 4. File permissions

- Set file and directory ownership to the PHP/FTP web user or the host’s application user, and remove group/world write access where not required.
- Recommended directory permissions:
  - `directories` such as `uploads/`, `uploads/resources/`, `uploads/profiles/`, `uploads/service-documents/`, `uploads/event-images/`: writable only by the application runtime, typically `755` or `775` depending on host ownership.
  - `logs/`: an application-writable read/write directory only if logging is intentionally enabled. Keep it protected from web access.
  - `config/`, `database/`, `helpers/`, `models/`, `services/`, and `includes/`: executable PHP files only; no public write access.
- Ensure PHP execution is allowed only for the PHP files and not for uploaded content.
- Set `chmod` or equivalent host permissions securely and avoid giving `777` access to any production directory.

## 5. Upload directories

- Confirm the production upload directories exist and are writable by the PHP process:
  - `uploads/profiles`
  - `uploads/service-documents`
  - `uploads/event-images`
  - `uploads/resources`
- Confirm uploaded files are stored outside the repository public root where possible, or are protected by `.htaccess` and route checks.
- Confirm upload size limits align with `APP_UPLOAD_MAX_BYTES` and the host’s PHP upload limits.
- Confirm file mime validation and image size validation are enforced before accepted uploads.
- Confirm file naming is normalized and path traversal is blocked.

## 6. `.htaccess` and Apache rewrite rules

- Confirm `mod_rewrite` is enabled or the host uses equivalent Nginx rewrite configuration.
- Confirm the current `.htaccess` rules are preserved in production:
  - `Options -Indexes` to prevent directory listing.
  - deny access to sensitive folders such as `config`, `database`, `logs`, and `uploads` via rewrite rule.
  - deny access to `.env`, `.sql`, and `.log` files at the directory level.
- Confirm `AllowOverride All` is enabled on the host if `.htaccess` is used.
- Confirm that web server rewriting does not expose sensitive documents or route files.

## 7. Error handling

- Set `APP_ENV` to production and remove development-only display behavior.
- Turn off PHP `display_errors` and ensure exceptions do not reveal the database or file system path.
- Send production errors to the host log service or the app’s `logs/` directory, not to the browser.
- Avoid showing `PDOException`, SQL errors, stack traces, or file path details to end users.
- Confirm the application catches general runtime errors and returns a generic error page.

## 8. Production configuration

- Set `APP_BASE_URL` to the production HTTPS origin, for example `https://example.com/CMMP`.
- Set `APP_ENV` to `production`.
- Set `APP_TIMEZONE` to the correct application timezone, such as `Asia/Manila` only if that remains the correct hosting timezone.
- Ensure `APP_UPLOAD_DIRECTORIES` points to directories that exist on the host and are not publicly browsable.
- Confirm the final route and asset URLs use the production host root, not `http://localhost/CMMP`.

## 9. Session configuration

- Configure PHP sessions to use cookie-only storage and to regenerate session IDs after login.
- Ensure secure cookie settings use HTTPS (`secure = true`) and HTTP-only cookies (`httponly = true`), and set `SameSite=Lax`.
- Do not allow session IDs to be transmitted through query strings.
- Confirm the host has session save paths that are writable and not shared with the public web root.
- Confirm `session.use_strict_mode = 1` and `session.use_only_cookies = 1` are enabled, either in `php.ini` or by the host.

## 10. HTTPS readiness

- Configure an SSL certificate for the production domain.
- Force all traffic to `https://` by redirecting HTTP requests to HTTPS in the host web server or `.htaccess` rules.
- Ensure all generated absolute URLs and redirects use the secure scheme.
- Set `secure` cookie parameters in the PHP session code path and verify the host supports that on HTTPS only.
- Confirm no resource or upload route is downloadable over plain HTTP.

## 11. Sensitive file protection

- Restrict access to:
  - `config/app.php`
  - `config/database.php`
  - `database/schema.sql`
  - `database/seeds.sql`
  - `logs/`
  - `uploads/`
  - any `.env` or `.env.*` files
  - `.htaccess`
- Keep the production `.htaccess` file in place, and verify the host honors it.
- Confirm the host blocks direct access to private upload directories and logs.
- Confirm that a web user cannot browse the repository, the database folder, or the configuration folder.

## 12. Database backup procedure

- Before deployment, create a full database backup from the production source database.
- Export the database as a `.sql` file and store it outside the web root, encrypted if possible.
- Keep a backup rotation policy of at least daily or weekly backups depending on the church’s operational needs.
- Store a backup and restore command template in the host operations documentation.
- Do not store private documents or production SQL dumps within public folders or repositories.

## 13. Before deployment actions

- Run syntax validation on all PHP files.
- Run the existing route and smoke tests in a non-production environment.
- Confirm there is no accidental `localhost` or development URL in production configuration.
- Confirm the app does not expose stack traces, SQL errors, or debug diagnostics through error pages.
- Confirm uploaded file directories are not publicly readable and are protected by route checks.
- Confirm the application’s admin and resource routes use the correct role guard patterns and CSRF token checks.

## 14. Explicit hosting server requirements

The server must be configured before deployment:

1. PHP 8.x runtime with PDO, PDO MySQL, OpenSSL, and session support.
2. MySQL 8+ database with a production database, a production database user, and a secure password.
3. Web server rewrite support (`mod_rewrite`, or equivalent Nginx configuration) with `AllowOverride All` or equivalent.
4. HTTPS certificate and HTTP-to-HTTPS redirect.
5. Directory and file permissions for upload directories and logs that keep private files from public access.
6. `php.ini` production values showing `display_errors = Off`, `log_errors = On`, and no debug output.
7. A backup procedure and storage area outside the public web root.

Do not deploy automatically. The final deployment decision must only happen after the host environment is configured according to the listed production checklist and after all credentials and private operational data are kept out of the repository and public output.
