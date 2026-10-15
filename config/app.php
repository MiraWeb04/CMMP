<?php
declare(strict_types=1);

const APP_NAME = 'CMMP';
const APP_TITLE = 'Church Management & Member Portal';
const APP_ENV = 'development';
const APP_TIMEZONE = 'Asia/Manila';
const APP_BASE_URL = 'http://localhost/CMMP';
const APP_UPLOAD_MAX_BYTES = 10485760;
const APP_UPLOAD_DIRECTORIES = [
	'profiles' => __DIR__ . '/../uploads/profiles',
	'service_documents' => __DIR__ . '/../uploads/service-documents',
	'event_images' => __DIR__ . '/../uploads/event-images',
	'resources' => __DIR__ . '/../uploads/resources',
];

date_default_timezone_set(getenv('APP_TIMEZONE') ?: APP_TIMEZONE);
