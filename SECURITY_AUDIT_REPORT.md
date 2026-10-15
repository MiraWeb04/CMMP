# CMMP Security Audit Report

Date: 2026-09-09
Scope: step 23 audit of the application security posture.

## Executive summary

The CMMP codebase was scanned for route guards, destructive GET endpoints, CSRF usage, authorization patterns, and file handling constraints. The main verified issue identified in the audit was that the resource/document deletion flow in the admin documents area exposed a destructive file deletion action through a GET URL and a direct file unlink call without a CSRF gate.

The route was corrected by requiring POST-only access, enforcing the same role guard as the admin resource area, validating a CSRF token, verifying the deletion target is a file within the protected uploads/resources base directory, and then unlinking the file and removing the associated Resource record only after the checks pass.

The static guard scan found no unprotected non-index PHP endpoint candidates outside the admin route area. The route list and file upload base remained protected by authorization and route guards.

## Findings and corrections

### 1. Destructive resource deletion via GET URL
- Risk: CSRF, accidental deletion via browser or third-party link, and unreviewed destructive action.
- Location: [admin/documents/delete.php](admin/documents/delete.php), associated UI in [admin/documents/index.php](admin/documents/index.php).
- Existing behavior: direct GET to delete.php?id=<id> accepted an ID, located the associated record, resolved the physical file path, and called unlink() before deleting the database record.
- Fix applied: the delete route now checks for POST only, requires the valid CSRF token, keeps the role gate at `require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF'])`, verifies the file is inside the allowed uploads/resources directory before unlink, and deletes the Resource record after those checks.
- UI fix: the list screen no longer renders a delete anchor route. It now renders an HTML form with a CSRF hidden field and a POST delete action.
- Verification: syntax verified with `php -l admin/documents/delete.php` and `php -l admin/documents/index.php`; both returned “No syntax errors detected”.

## Additional route protection observations

- Static route guard scan confirmed protected non-index PHP endpoints in the code base are generally guarded by `require_login`, `require_role`, or `is_logged_in` patterns. No anonymous writable or destructive route candidate was found by static scan.
- Route scanning for uploaded document resources and resource directories remained protected by existing authentication and file access guard patterns.

## Conclusion

The known destructive route issue was corrected without introducing a business feature. The deletion flow now follows the audited security expectation: POST-only, CSRF-protected, route-authorized, and file-path-restricted before any resource removal occurs.
