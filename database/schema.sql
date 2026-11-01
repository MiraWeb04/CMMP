CREATE DATABASE IF NOT EXISTS cmmp_db
	CHARACTER SET utf8mb4
	COLLATE utf8mb4_unicode_ci;

USE cmmp_db;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Authentication and authorization
CREATE TABLE IF NOT EXISTS users (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	username VARCHAR(100) NULL,
	email VARCHAR(255) NOT NULL,
	role VARCHAR(50) NULL,
	password_hash VARCHAR(255) NOT NULL,
	status ENUM('ACTIVE', 'INACTIVE', 'LOCKED', 'PENDING') NOT NULL DEFAULT 'PENDING',
	last_login_at DATETIME NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_users_username (username),
	UNIQUE KEY uq_users_email (email),
	KEY idx_users_status (status),
	KEY idx_users_role (role),
	KEY idx_users_last_login (last_login_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS roles (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	code VARCHAR(50) NOT NULL,
	name VARCHAR(100) NOT NULL,
	description VARCHAR(255) NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_roles_code (code),
	UNIQUE KEY uq_roles_name (name)
) ENGINE=InnoDB;

INSERT INTO roles (code, name, description)
VALUES
	('SUPER_ADMIN', 'Super Administrator', 'Full system access'),
	('CHURCH_ADMIN', 'Church Administrator', 'Church operations administration'),
	('PASTOR', 'Pastor', 'Pastoral service and appointment access'),
	('COUNSELOR', 'Counselor', 'Counseling service and appointment access'),
	('MINISTRY_LEADER', 'Ministry Leader', 'Assigned ministry management access'),
	('STAFF', 'Staff', 'Authorized staff access'),
	('MEMBER', 'Member', 'Member portal access')
ON DUPLICATE KEY UPDATE
	name = VALUES(name),
	description = VALUES(description),
	updated_at = CURRENT_TIMESTAMP;

CREATE TABLE IF NOT EXISTS user_roles (
	user_id BIGINT UNSIGNED NOT NULL,
	role_id BIGINT UNSIGNED NOT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (user_id, role_id),
	KEY idx_user_roles_role (role_id),
	CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users (id)
		ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles (id)
		ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Members and families
CREATE TABLE IF NOT EXISTS families (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	family_code VARCHAR(50) NOT NULL,
	family_name VARCHAR(150) NOT NULL,
	address_line_1 VARCHAR(255) NULL,
	address_line_2 VARCHAR(255) NULL,
	city VARCHAR(100) NULL,
	state_province VARCHAR(100) NULL,
	postal_code VARCHAR(20) NULL,
	country VARCHAR(100) NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_families_code (family_code),
	KEY idx_families_name (family_name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS members (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	user_id BIGINT UNSIGNED NULL,
	member_number VARCHAR(50) NOT NULL,
	first_name VARCHAR(100) NOT NULL,
	middle_name VARCHAR(100) NULL,
	last_name VARCHAR(100) NOT NULL,
	suffix VARCHAR(20) NULL,
	preferred_name VARCHAR(100) NULL,
	date_of_birth DATE NULL,
	gender VARCHAR(30) NULL,
	phone VARCHAR(30) NULL,
	email VARCHAR(255) NULL,
	address_line_1 VARCHAR(255) NULL,
	address_line_2 VARCHAR(255) NULL,
	city VARCHAR(100) NULL,
	state_province VARCHAR(100) NULL,
	postal_code VARCHAR(20) NULL,
	country VARCHAR(100) NULL,
	baptism_date DATE NULL,
	baptism_church VARCHAR(200) NULL,
	baptism_notes TEXT NULL,
	emergency_contact_name VARCHAR(200) NULL,
	emergency_contact_relationship VARCHAR(100) NULL,
	emergency_contact_phone VARCHAR(30) NULL,
	profile_photo VARCHAR(500) NULL,
	membership_status ENUM('ACTIVE', 'INACTIVE', 'TRANSFERRED', 'DECEASED') NOT NULL DEFAULT 'ACTIVE',
	joined_at DATE NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_members_user (user_id),
	UNIQUE KEY uq_members_number (member_number),
	KEY idx_members_name (last_name, first_name),
	KEY idx_members_status (membership_status),
	KEY idx_members_email (email),
	CONSTRAINT fk_members_user FOREIGN KEY (user_id) REFERENCES users (id)
		ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS family_members (
	family_id BIGINT UNSIGNED NOT NULL,
	member_id BIGINT UNSIGNED NOT NULL,
	relationship_to_head VARCHAR(50) NULL,
	is_primary_contact BOOLEAN NOT NULL DEFAULT FALSE,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (family_id, member_id),
	KEY idx_family_members_member (member_id),
	CONSTRAINT fk_family_members_family FOREIGN KEY (family_id) REFERENCES families (id)
		ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT fk_family_members_member FOREIGN KEY (member_id) REFERENCES members (id)
		ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Church staff and staff specializations
CREATE TABLE IF NOT EXISTS staff (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	user_id BIGINT UNSIGNED NULL,
	member_id BIGINT UNSIGNED NULL,
	employee_number VARCHAR(50) NOT NULL,
	position_title VARCHAR(100) NOT NULL,
	employment_status ENUM('ACTIVE', 'INACTIVE', 'ON_LEAVE') NOT NULL DEFAULT 'ACTIVE',
	hired_at DATE NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_staff_user (user_id),
	UNIQUE KEY uq_staff_member (member_id),
	UNIQUE KEY uq_staff_employee_number (employee_number),
	KEY idx_staff_status (employment_status),
	KEY idx_staff_user (user_id),
	CONSTRAINT fk_staff_user FOREIGN KEY (user_id) REFERENCES users (id)
		ON DELETE SET NULL ON UPDATE CASCADE,
	CONSTRAINT fk_staff_member FOREIGN KEY (member_id) REFERENCES members (id)
		ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pastors (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	staff_id BIGINT UNSIGNED NOT NULL,
	ordination_date DATE NULL,
	biography TEXT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_pastors_staff (staff_id),
	CONSTRAINT fk_pastors_staff FOREIGN KEY (staff_id) REFERENCES staff (id)
		ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS counselors (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	staff_id BIGINT UNSIGNED NOT NULL,
	specialization VARCHAR(150) NULL,
	credentials VARCHAR(255) NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_counselors_staff (staff_id),
	CONSTRAINT fk_counselors_staff FOREIGN KEY (staff_id) REFERENCES staff (id)
		ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Ministries and activities
CREATE TABLE IF NOT EXISTS ministries (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	name VARCHAR(150) NOT NULL,
	code VARCHAR(50) NOT NULL,
	leader_staff_id BIGINT UNSIGNED NULL,
	description TEXT NULL,
	ministry_status ENUM('ACTIVE', 'INACTIVE') NOT NULL DEFAULT 'ACTIVE',
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_ministries_code (code),
	UNIQUE KEY uq_ministries_name (name),
	KEY idx_ministries_status (ministry_status),
	KEY idx_ministries_leader (leader_staff_id),
	CONSTRAINT fk_ministries_leader FOREIGN KEY (leader_staff_id) REFERENCES staff (id)
		ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ministry_members (
	ministry_id BIGINT UNSIGNED NOT NULL,
	member_id BIGINT UNSIGNED NOT NULL,
	membership_role VARCHAR(100) NULL,
	joined_at DATE NULL,
	left_at DATE NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (ministry_id, member_id),
	KEY idx_ministry_members_member (member_id),
	CONSTRAINT fk_ministry_members_ministry FOREIGN KEY (ministry_id) REFERENCES ministries (id)
		ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT fk_ministry_members_member FOREIGN KEY (member_id) REFERENCES members (id)
		ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ministry_activities (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	ministry_id BIGINT UNSIGNED NOT NULL,
	created_by_user_id BIGINT UNSIGNED NULL,
	name VARCHAR(150) NOT NULL,
	description TEXT NULL,
	start_datetime DATETIME NULL,
	end_datetime DATETIME NULL,
	location VARCHAR(255) NULL,
	activity_status ENUM('PLANNED', 'ONGOING', 'COMPLETED', 'CANCELLED') NOT NULL DEFAULT 'PLANNED',
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	KEY idx_ministry_activities_ministry (ministry_id),
	KEY idx_ministry_activities_schedule (start_datetime, end_datetime),
	CONSTRAINT fk_ministry_activities_ministry FOREIGN KEY (ministry_id) REFERENCES ministries (id)
		ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT fk_ministry_activities_creator FOREIGN KEY (created_by_user_id) REFERENCES users (id)
		ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Events and attendance
CREATE TABLE IF NOT EXISTS event_categories (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	name VARCHAR(100) NOT NULL,
	description VARCHAR(255) NULL,
	is_active BOOLEAN NOT NULL DEFAULT TRUE,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_event_categories_name (name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS events (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	created_by_user_id BIGINT UNSIGNED NULL,
	category_id BIGINT UNSIGNED NULL,
	facility_id BIGINT UNSIGNED NULL,
	title VARCHAR(200) NOT NULL,
	description TEXT NULL,
	start_datetime DATETIME NOT NULL,
	end_datetime DATETIME NOT NULL,
	location VARCHAR(255) NULL,
	capacity INT UNSIGNED NULL,
	registration_required BOOLEAN NOT NULL DEFAULT FALSE,
	registration_deadline DATETIME NULL,
	event_image VARCHAR(500) NULL,
	event_status ENUM('DRAFT', 'PUBLISHED', 'CANCELLED', 'COMPLETED') NOT NULL DEFAULT 'DRAFT',
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	KEY idx_events_schedule (start_datetime, end_datetime),
	KEY idx_events_status (event_status),
	KEY idx_events_title (title),
	KEY idx_events_category (category_id),
	KEY idx_events_facility (facility_id),
	CONSTRAINT fk_events_creator FOREIGN KEY (created_by_user_id) REFERENCES users (id)
		ON DELETE SET NULL ON UPDATE CASCADE
	,
	CONSTRAINT fk_events_category FOREIGN KEY (category_id) REFERENCES event_categories (id)
		ON DELETE SET NULL ON UPDATE CASCADE,
	CONSTRAINT chk_events_time CHECK (end_datetime > start_datetime),
	CONSTRAINT chk_events_capacity CHECK (capacity IS NULL OR capacity > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS event_registrations (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	event_id BIGINT UNSIGNED NOT NULL,
	member_id BIGINT UNSIGNED NOT NULL,
	registration_status ENUM('REGISTERED', 'WAITLISTED', 'CANCELLED', 'ATTENDED', 'NO_SHOW') NOT NULL DEFAULT 'REGISTERED',
	registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_event_registrations_event_member (event_id, member_id),
	KEY idx_event_registrations_member (member_id),
	KEY idx_event_registrations_status (registration_status),
	CONSTRAINT fk_event_registrations_event FOREIGN KEY (event_id) REFERENCES events (id)
		ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT fk_event_registrations_member FOREIGN KEY (member_id) REFERENCES members (id)
		ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS event_attendance (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	event_id BIGINT UNSIGNED NOT NULL,
	registration_id BIGINT UNSIGNED NULL,
	member_id BIGINT UNSIGNED NOT NULL,
	attendance_status ENUM('PRESENT', 'ABSENT', 'EXCUSED') NOT NULL DEFAULT 'PRESENT',
	recorded_by_user_id BIGINT UNSIGNED NULL,
	recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_event_attendance_event_member (event_id, member_id),
	KEY idx_event_attendance_registration (registration_id),
	KEY idx_event_attendance_member (member_id),
	CONSTRAINT fk_event_attendance_event FOREIGN KEY (event_id) REFERENCES events (id)
		ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT fk_event_attendance_registration FOREIGN KEY (registration_id) REFERENCES event_registrations (id)
		ON DELETE SET NULL ON UPDATE CASCADE,
	CONSTRAINT fk_event_attendance_member FOREIGN KEY (member_id) REFERENCES members (id)
		ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT fk_event_attendance_recorder FOREIGN KEY (recorded_by_user_id) REFERENCES users (id)
		ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS event_assignments (
	event_id BIGINT UNSIGNED NOT NULL,
	staff_id BIGINT UNSIGNED NOT NULL,
	assignment_role VARCHAR(100) NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (event_id, staff_id),
	KEY idx_event_assignments_staff (staff_id),
	CONSTRAINT fk_event_assignments_event FOREIGN KEY (event_id) REFERENCES events (id)
		ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT fk_event_assignments_staff FOREIGN KEY (staff_id) REFERENCES staff (id)
		ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Centralized church service requests
CREATE TABLE IF NOT EXISTS service_types (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	code VARCHAR(60) NOT NULL,
	name VARCHAR(150) NOT NULL,
	description TEXT NULL,
	is_active BOOLEAN NOT NULL DEFAULT TRUE,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_service_types_code (code),
	UNIQUE KEY uq_service_types_name (name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS service_requests (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	service_type_id BIGINT UNSIGNED NOT NULL,
	requested_by_member_id BIGINT UNSIGNED NOT NULL,
	request_number VARCHAR(50) NOT NULL,
	status ENUM('PENDING', 'PENDING_ASSIGNMENT', 'UNDER_REVIEW', 'DOCUMENTS_REQUIRED', 'ASSIGNED', 'APPROVED', 'SCHEDULED', 'CONFIRMED', 'RESCHEDULE_REQUIRED', 'COMPLETED', 'REJECTED', 'CANCELLED') NOT NULL DEFAULT 'PENDING_ASSIGNMENT',
	subject VARCHAR(200) NOT NULL,
	description TEXT NULL,
	request_data JSON NULL,
	preferred_start_datetime DATETIME NULL,
	preferred_end_datetime DATETIME NULL,
	preferred_facility_id BIGINT UNSIGNED NULL,
	requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	reviewed_by_user_id BIGINT UNSIGNED NULL,
	reviewed_at DATETIME NULL,
	completed_at DATETIME NULL,
	hidden_from_member_at DATETIME NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_service_requests_number (request_number),
	KEY idx_service_requests_type_status (service_type_id, status),
	KEY idx_service_requests_member (requested_by_member_id),
	KEY idx_service_requests_status (status),
	KEY idx_service_requests_requested_at (requested_at),
	KEY idx_service_requests_member_hidden (requested_by_member_id, hidden_from_member_at),
	CONSTRAINT fk_service_requests_type FOREIGN KEY (service_type_id) REFERENCES service_types (id)
		ON DELETE RESTRICT ON UPDATE CASCADE,
	CONSTRAINT fk_service_requests_member FOREIGN KEY (requested_by_member_id) REFERENCES members (id)
		ON DELETE RESTRICT ON UPDATE CASCADE,
	CONSTRAINT fk_service_requests_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users (id)
		ON DELETE SET NULL ON UPDATE CASCADE,
	CONSTRAINT fk_service_requests_preferred_facility FOREIGN KEY (preferred_facility_id) REFERENCES facilities (id)
		ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS service_request_participants (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	service_request_id BIGINT UNSIGNED NOT NULL,
	member_id BIGINT UNSIGNED NULL,
	participant_role VARCHAR(100) NOT NULL,
	participant_name VARCHAR(200) NOT NULL,
	contact_phone VARCHAR(30) NULL,
	contact_email VARCHAR(255) NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	KEY idx_service_request_participants_request (service_request_id),
	KEY idx_service_request_participants_member (member_id),
	CONSTRAINT fk_service_request_participants_request FOREIGN KEY (service_request_id) REFERENCES service_requests (id)
		ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT fk_service_request_participants_member FOREIGN KEY (member_id) REFERENCES members (id)
		ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS service_documents (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	service_request_id BIGINT UNSIGNED NOT NULL,
	uploaded_by_user_id BIGINT UNSIGNED NULL,
	document_type VARCHAR(100) NOT NULL,
	original_filename VARCHAR(255) NOT NULL,
	stored_filename VARCHAR(255) NOT NULL,
	storage_path VARCHAR(500) NOT NULL,
	mime_type VARCHAR(100) NOT NULL,
	file_size_bytes BIGINT UNSIGNED NOT NULL,
	is_private BOOLEAN NOT NULL DEFAULT TRUE,
	document_status ENUM('PENDING', 'VERIFIED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
	rejection_reason VARCHAR(500) NULL,
	reviewed_by_user_id BIGINT UNSIGNED NULL,
	reviewed_at DATETIME NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_service_documents_storage_path (storage_path),
	KEY idx_service_documents_request (service_request_id),
	CONSTRAINT fk_service_documents_request FOREIGN KEY (service_request_id) REFERENCES service_requests (id)
		ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT fk_service_documents_uploader FOREIGN KEY (uploaded_by_user_id) REFERENCES users (id)
		ON DELETE SET NULL ON UPDATE CASCADE,
	CONSTRAINT fk_service_documents_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users (id)
		ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS service_assignments (
	service_request_id BIGINT UNSIGNED NOT NULL,
	staff_id BIGINT UNSIGNED NOT NULL,
	assignment_role VARCHAR(100) NOT NULL,
	assigned_by_user_id BIGINT UNSIGNED NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (service_request_id, staff_id, assignment_role),
	KEY idx_service_assignments_staff (staff_id),
	CONSTRAINT fk_service_assignments_request FOREIGN KEY (service_request_id) REFERENCES service_requests (id)
		ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT fk_service_assignments_staff FOREIGN KEY (staff_id) REFERENCES staff (id)
		ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT fk_service_assignments_assigner FOREIGN KEY (assigned_by_user_id) REFERENCES users (id)
		ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS service_status_history (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	service_request_id BIGINT UNSIGNED NOT NULL,
	old_status VARCHAR(30) NULL,
	new_status VARCHAR(30) NOT NULL,
	changed_by_user_id BIGINT UNSIGNED NULL,
	change_note VARCHAR(500) NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	KEY idx_service_status_history_request (service_request_id, created_at),
	CONSTRAINT fk_service_status_history_request FOREIGN KEY (service_request_id) REFERENCES service_requests (id)
		ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT fk_service_status_history_user FOREIGN KEY (changed_by_user_id) REFERENCES users (id)
		ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Scheduling and facilities
CREATE TABLE IF NOT EXISTS facilities (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	name VARCHAR(150) NOT NULL,
	code VARCHAR(50) NOT NULL,
	description TEXT NULL,
	capacity INT UNSIGNED NULL,
	location VARCHAR(255) NULL,
	facility_type VARCHAR(50) NOT NULL DEFAULT 'OTHER',
	facility_status ENUM('AVAILABLE', 'UNAVAILABLE', 'MAINTENANCE') NOT NULL DEFAULT 'AVAILABLE',
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_facilities_code (code),
	UNIQUE KEY uq_facilities_name (name),
	KEY idx_facilities_status (facility_status)
) ENGINE=InnoDB;

ALTER TABLE events
	ADD CONSTRAINT fk_events_facility FOREIGN KEY (facility_id) REFERENCES facilities (id)
		ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE IF NOT EXISTS appointments (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	service_request_id BIGINT UNSIGNED NULL,
	event_id BIGINT UNSIGNED NULL,
	facility_id BIGINT UNSIGNED NULL,
	appointment_type VARCHAR(100) NOT NULL,
	assigned_staff_id BIGINT UNSIGNED NULL,
	start_datetime DATETIME NOT NULL,
	end_datetime DATETIME NOT NULL,
	status ENUM('PENDING', 'CONFIRMED', 'COMPLETED', 'CANCELLED', 'NO_SHOW') NOT NULL DEFAULT 'PENDING',
	notes TEXT NULL,
	created_by_user_id BIGINT UNSIGNED NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	KEY idx_appointments_service_request (service_request_id),
	KEY idx_appointments_event (event_id),
	KEY idx_appointments_staff_schedule (assigned_staff_id, start_datetime, end_datetime),
	KEY idx_appointments_facility_schedule (facility_id, start_datetime, end_datetime),
	KEY idx_appointments_status_schedule (status, start_datetime, end_datetime),
	CONSTRAINT fk_appointments_service_request FOREIGN KEY (service_request_id) REFERENCES service_requests (id)
		ON DELETE SET NULL ON UPDATE CASCADE,
	CONSTRAINT fk_appointments_event FOREIGN KEY (event_id) REFERENCES events (id)
		ON DELETE SET NULL ON UPDATE CASCADE,
	CONSTRAINT fk_appointments_facility FOREIGN KEY (facility_id) REFERENCES facilities (id)
		ON DELETE SET NULL ON UPDATE CASCADE,
	CONSTRAINT fk_appointments_staff FOREIGN KEY (assigned_staff_id) REFERENCES staff (id)
		ON DELETE SET NULL ON UPDATE CASCADE,
	CONSTRAINT fk_appointments_creator FOREIGN KEY (created_by_user_id) REFERENCES users (id)
		ON DELETE SET NULL ON UPDATE CASCADE,
	CONSTRAINT chk_appointments_time CHECK (end_datetime > start_datetime)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS appointment_staff (
	appointment_id BIGINT UNSIGNED NOT NULL,
	staff_id BIGINT UNSIGNED NOT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (appointment_id, staff_id),
	KEY idx_appointment_staff_staff (staff_id),
	CONSTRAINT fk_appointment_staff_appointment FOREIGN KEY (appointment_id) REFERENCES appointments (id)
		ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT fk_appointment_staff_staff FOREIGN KEY (staff_id) REFERENCES staff (id)
		ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS counseling_cases (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	service_request_id BIGINT UNSIGNED NOT NULL,
	status ENUM('NOT_STARTED', 'IN_PROGRESS', 'COMPLETED', 'NOT_REQUIRED') NOT NULL DEFAULT 'NOT_STARTED',
	required_sessions INT UNSIGNED NOT NULL DEFAULT 3,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_counseling_cases_request (service_request_id),
	CONSTRAINT fk_counseling_cases_request FOREIGN KEY (service_request_id) REFERENCES service_requests (id)
		ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS counseling_sessions (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	counseling_case_id BIGINT UNSIGNED NOT NULL,
	session_number INT UNSIGNED NOT NULL,
	appointment_id BIGINT UNSIGNED NULL,
	status ENUM('PLANNED', 'COMPLETED', 'CANCELLED') NOT NULL DEFAULT 'PLANNED',
	private_notes TEXT NULL,
	completed_at DATETIME NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_counseling_sessions_number (counseling_case_id, session_number),
	UNIQUE KEY uq_counseling_sessions_appointment (appointment_id),
	KEY idx_counseling_sessions_status (status),
	CONSTRAINT fk_counseling_sessions_case FOREIGN KEY (counseling_case_id) REFERENCES counseling_cases (id)
		ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT fk_counseling_sessions_appointment FOREIGN KEY (appointment_id) REFERENCES appointments (id)
		ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS scheduling_resource_locks (
	resource_key VARCHAR(180) NOT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (resource_key)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS facility_reservations (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	facility_id BIGINT UNSIGNED NOT NULL,
	appointment_id BIGINT UNSIGNED NULL,
	reserved_by_user_id BIGINT UNSIGNED NULL,
	start_datetime DATETIME NOT NULL,
	end_datetime DATETIME NOT NULL,
	status ENUM('HELD', 'CONFIRMED', 'CANCELLED', 'COMPLETED') NOT NULL DEFAULT 'HELD',
	purpose VARCHAR(200) NOT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_facility_reservations_appointment (appointment_id),
	KEY idx_facility_reservations_schedule (facility_id, start_datetime, end_datetime),
	KEY idx_facility_reservations_status (status),
	CONSTRAINT fk_facility_reservations_facility FOREIGN KEY (facility_id) REFERENCES facilities (id)
		ON DELETE RESTRICT ON UPDATE CASCADE,
	CONSTRAINT fk_facility_reservations_appointment FOREIGN KEY (appointment_id) REFERENCES appointments (id)
		ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT fk_facility_reservations_user FOREIGN KEY (reserved_by_user_id) REFERENCES users (id)
		ON DELETE SET NULL ON UPDATE CASCADE,
	CONSTRAINT chk_facility_reservations_time CHECK (end_datetime > start_datetime)
) ENGINE=InnoDB;

-- Communication and other portal data
CREATE TABLE IF NOT EXISTS announcements (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	created_by_user_id BIGINT UNSIGNED NULL,
	title VARCHAR(200) NOT NULL,
	body TEXT NOT NULL,
	audience_role VARCHAR(50) NULL,
	published_at DATETIME NULL,
	expires_at DATETIME NULL,
	announcement_status ENUM('DRAFT', 'PUBLISHED', 'ARCHIVED') NOT NULL DEFAULT 'DRAFT',
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	KEY idx_announcements_visibility (announcement_status, published_at, expires_at),
	CONSTRAINT fk_announcements_creator FOREIGN KEY (created_by_user_id) REFERENCES users (id)
		ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	user_id BIGINT UNSIGNED NOT NULL,
	title VARCHAR(200) NOT NULL,
	message TEXT NOT NULL,
	notification_type VARCHAR(50) NULL,
	reference_type VARCHAR(100) NULL,
	reference_id BIGINT UNSIGNED NULL,
	read_at DATETIME NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	KEY idx_notifications_user_read (user_id, read_at, created_at),
	CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id)
		ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS church_resources (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	uploaded_by_user_id BIGINT UNSIGNED NULL,
	title VARCHAR(200) NOT NULL,
	description TEXT NULL,
	resource_type VARCHAR(50) NOT NULL,
	storage_path VARCHAR(500) NOT NULL,
	mime_type VARCHAR(100) NULL,
	file_size_bytes BIGINT UNSIGNED NULL,
	visibility ENUM('MEMBERS', 'STAFF', 'PUBLIC') NOT NULL DEFAULT 'MEMBERS',
	published_at DATETIME NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_church_resources_storage_path (storage_path),
	KEY idx_church_resources_visibility (visibility, published_at),
	CONSTRAINT fk_church_resources_uploader FOREIGN KEY (uploaded_by_user_id) REFERENCES users (id)
		ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_logs (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	user_id BIGINT UNSIGNED NULL,
	action VARCHAR(100) NOT NULL,
	entity_type VARCHAR(100) NULL,
	entity_id BIGINT UNSIGNED NULL,
	ip_address VARCHAR(45) NULL,
	user_agent VARCHAR(500) NULL,
	metadata JSON NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	KEY idx_activity_logs_user (user_id, created_at),
	KEY idx_activity_logs_entity (entity_type, entity_id, created_at),
	KEY idx_activity_logs_action (action, created_at),
	CONSTRAINT fk_activity_logs_user FOREIGN KEY (user_id) REFERENCES users (id)
		ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_attempts (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	identifier VARCHAR(255) NOT NULL,
	ip_address VARCHAR(45) NOT NULL,
	was_successful TINYINT(1) NOT NULL DEFAULT 0,
	attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	KEY idx_login_attempts_lookup (identifier, attempted_at),
	KEY idx_login_attempts_ip (ip_address, attempted_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	user_id BIGINT UNSIGNED NOT NULL,
	token_hash CHAR(64) NOT NULL,
	expires_at DATETIME NOT NULL,
	used_at DATETIME NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_password_reset_hash (token_hash),
	KEY idx_password_reset_user (user_id, expires_at),
	CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users (id)
		ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Initial service catalog. Request-specific data remains in service_requests.
INSERT INTO service_types (code, name, description)
VALUES
	('WEDDING', 'Wedding', 'Wedding service request'),
	('BAPTISM', 'Baptism', 'Baptism service request'),
	('FUNERAL_SERVICE', 'Funeral Service', 'Funeral service request'),
	('FACILITY_RESERVATION', 'Facility Reservation', 'Request the use of a church facility for an approved church event'),
	('PRE_MARRIAGE_COUNSELING', 'Pre-Marriage Counseling', 'Pre-marriage counseling request'),
	('CHILD_DEDICATION', 'Child Dedication', 'Child dedication request'),
	('HOUSE_BLESSING', 'House Blessing', 'House blessing request'),
	('PASTORAL_COUNSELING', 'Pastoral Counseling', 'Pastoral counseling request'),
	('MANANITA', 'Mañanita', 'Birthday surprise worship service (mañanita) request')
ON DUPLICATE KEY UPDATE
	name = VALUES(name),
	description = VALUES(description),
	updated_at = CURRENT_TIMESTAMP;

SET FOREIGN_KEY_CHECKS = 1;
