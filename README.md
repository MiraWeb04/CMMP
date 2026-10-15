# CMMP

CMMP is a Church Management & Member Portal for centralized church operations and member interaction.

## Technology Stack

- PHP 8+
- MySQL 8+
- PDO
- Bootstrap 5
- HTML5 and CSS3
- JavaScript and Fetch API
- Font Awesome

## Project Structure

- `admin/`, `member/`, `pastor/`, `counselor/`, and `ministry/`: role-oriented page areas
- `auth/`: authentication route placeholders
- `api/`: future JSON endpoints
- `config/`: application and database configuration
- `controllers/`, `models/`, `services/`, `middleware/`, and `helpers/`: application layers
- `includes/`: reusable presentation components
- `assets/`: CSS, JavaScript, images, and icons
- `database/`: schema, seed, and migration files
- `uploads/`: categorized upload storage
- `logs/`: application logs

## Local Requirements

- PHP 8.0 or newer with PDO MySQL enabled
- MySQL 8.0 or newer
- Apache or another PHP-capable web server
- Bootstrap 5 and Font Awesome access, or local equivalents

## Basic Setup

1. Place the `CMMP` directory in the web server document root.
2. Create a MySQL database named `cmmp`.
3. Configure `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` in the server environment.
4. Import `database/schema.sql` when the initial schema is defined.
5. Open the project through the configured local web server.

Authentication, database schema, church modules, and scheduling are intentionally not implemented in this step.
