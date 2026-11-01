# CMMP FR and NFR completion

These statements match the student-template requirement IDs and the software now on `cursor/all-upgrades-d518`.

## Functional requirements

| ID | Statement | Status |
|----|-----------|--------|
| FR-01 | Authorized people can register an account. | Met |
| FR-02 | Users authenticate before protected pages. | Met |
| FR-03 | Users can sign out. | Met |
| FR-04 | Users can view and update their own profile. | Met |
| FR-05 | Authorized users can create records. | Met |
| FR-06 | Authorized users can edit records. | Met |
| FR-07 | Authorized users can archive or delete records. | Met |
| FR-08 | Users can search and filter records. | Met |
| FR-09 | The system generates summary reports. | Met |
| FR-10 | Protected actions follow role permissions. | Met |

FR-04: members use `member/profile`. Administrators, staff, pastors, counselors, and ministry leaders use `account/profile.php` to update email, contact details, and password. Username, employee number, and roles stay admin-managed.

FR-07: people are deactivated (members, staff, pastors, counselors). Ministries use `ACTIVE` / `INACTIVE`. Announcements can be archived. Members can hide their completed service requests. Administrators may hard-delete selected users, service requests, and a user's own notifications.

## Non-functional requirements

| ID | Statement | Status |
|----|-----------|--------|
| NFR-01 | Protected list pages load at most 15 rows per page with parameterized queries and indexes. | Met |
| NFR-02 | Passwords are hashed, sessions are HttpOnly, POSTs use CSRF, login is rate-limited, and users can reset a password by email. | Met |
| NFR-03 | Transient database failures retry three times; `/health.php` reports readiness; email failure does not stop church workflows; operators can back up and restore the database. | Met |
| NFR-04 | Each role has a dashboard, confirmations before delete/hide, and success or error messages. | Met |
| NFR-05 | The portal runs on PHP 8 / MySQL or MariaDB and a responsive Bootstrap layout. | Met |

## Operator steps after pull

1. `git pull origin cursor/all-upgrades-d518`
2. Import `database/migrations/step26_nfr_hardening.sql` if the two new tables are missing. The app also creates them on first login or password reset.
3. Optional daily backup on Windows: `scripts\windows-backup.bat`
4. Restore: `php scripts/restore-database.php backups/cmmp_db-YYYYMMDD-HHMMSS.sql`
5. Health check: open `http://localhost/CMMP/health.php`
