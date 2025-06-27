<?php
// INSTANCE
define("INSTANCE_NAME", $_SERVER['HTTP_HOST']); // Instance name.
define("INSTANCE_MIRRORS", []); // Instance mirrors. Array should be like [ 'url' => 'name' ].
define("INSTANCE_ORIGINAL_WEBSITE", $_SERVER['HTTP_HOST']); // The original URL of the instance.

// DATABASE
define("DB_URL", "sqlite:{$_SERVER['DOCUMENT_ROOT']}/../database.db"); // The path to the database.

// UPLOADS
define("FILE_UPLOAD_DIRECTORY", $_SERVER['DOCUMENT_ROOT'] . "/userdata"); // The path to the main directory of user content.
define("FILE_ID_LENGTH", 5); // The length of the file ID.
define("FILE_ID_PREFIX", ""); // The prefix of the file ID.
define("FILE_ID_CHARPOOL", str_split("ABCDEFabcdef0123456789")); // A character pool used to generate file IDs.
define("FILE_ID_GENERATION_TIMEOUT_SEC", 20); // Seconds in which the file ID should be generated.
define("FILE_EXPIRATION", [
    '14d' => '2 weeks',
    '7d' => 'a week',
    '3d' => '3 days',
    '1d' => 'a day',
    '12h' => '12 hours',
    '3h' => '3 hours',
    '5m' => '5 minutes',
    're' => 'Burn after seeing',
    'ne' => 'Never'
]); // File expiration list.
define("FILE_DEFAULT_EXPIRATION", '14d'); // Default setting for file expiration. Maps to the key of FILE_EXPIRATION.
define("FILE_AUTHORIZED_UPLOAD", false); // Make file uploading only for authorized users.
define("FILE_AUTHORIZED_TAGS", false); // Make tag usage only for authorized users.
define("FILE_AUTHORIZED_PUBLIC", false); // Make public file uploading only for authorized users.
define("FILE_DEFAULT_VISIBILITY", 0); // Default setting for file visibility. 0 - Unlisted, 1 - Public.
define("FILE_EXTSRC", true); // Enable downloading from external sources (YouTube, Instagram, etc.) (Requires yt-dlp)
define("FILE_EXTSRC_DURATION", 60 * 5); // Maximum file duration in seconds. (External source files only)

// CATALOG
define("FILES_LIST_ENABLED", true); // Enable public file listing.
define("FILES_MAX_ITEMS", 50); // Max items pet requests.

// USERS
define("USER_REGISTRATION", true); // Enable user registration.
define("USER_NAME_LENGTH", [3, 20]); // The range of the username length. The first element is the minimum, the second element is the maximum.
define("USER_PASSWORD_LENGTH", 8); // Minimum length of user password.
define("USER_COOKIE_TIME", value: 86400 * 365); // The lifetime of user's cookie.