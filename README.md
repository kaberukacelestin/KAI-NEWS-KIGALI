# KAI News Kigali

A multilingual news media website built with PHP, MySQL, and a lightweight custom admin dashboard.

## Project overview

This project supports:
- multilingual site content (`rw`, `en`, `sw`, `fr`)
- admin-managed news articles, categories, and journalist user roles
- advertisement management with image/video upload
- top banner split layout for photo/video ads
- social media links and site settings via admin panel
- click tracking for ads

## Requirements

- PHP 8+ (recommended)
- MySQL / MariaDB
- Apache / XAMPP or equivalent web server
- `uploads/` directory writable by the web server

## Installation

1. Place the project in your web server root, e.g. `c:\xampp\htdocs\kai_news`.
2. Import the database schema from `database.sql` into MySQL.
3. Update `includes/config.php` if needed:
   - `DB_HOST`
   - `DB_USER`
   - `DB_PASS`
   - `DB_NAME`
   - `SITE_URL`

4. Ensure `uploads/`, `uploads/ads/`, `uploads/ads/videos/`, and related folders are writable.

## Default admin login

The default `admins` record created by `database.sql` is:
- username: `admin`
- password: `admin123`

> Change this password immediately after first login.

## Main site pages

- `index.php` — homepage with headlines, latest news, featured sections, and top ads
- `article.php` — article detail page with gallery and sidebar ads
- `category.php` — news by category
- `contact.php` — contact page
- `advertise.php` — advertising information page

## Admin features

### Access
- Open `admin/login.php`
- After login, access `admin/dashboard.php`

### Manage advertisements
- `admin/ads.php`
- Upload image ads and optional video ads
- Select ad position: `banner_top`, `banner_mid`, `sidebar`, `footer`, `popup`
- Enable animation and track clicks
- Delete ads and remove uploaded files automatically

### Manage settings and social links
- `admin/settings.php`
- Update:
  - `facebook_url`
  - `twitter_url`
  - `instagram_url`
  - `youtube_url`
  - live event settings
  - advertisement video URL or upload video file
- Social links are rendered globally in `includes/header.php` and `includes/footer.php`.

### Manage news and categories
- `admin/news-add.php`, `admin/news-edit.php`, `admin/news-list.php`
- `admin/manage-journalists.php` for journalist users

## How to update social media links

Use the admin settings page:
1. Login to admin panel
2. Visit `admin/settings.php`
3. Scroll to `Social Media Links`
4. Update the desired URLs and save

The site uses these settings keys:
- `facebook_url`
- `twitter_url`
- `instagram_url`
- `youtube_url`

## Advertisement video instructions

- Add a new ad in `admin/ads.php`
- Use the `Ad Video` upload field for video-only top banner ads
- For top banner video placement, set position to `banner_top`
- Ensure the ad is active and the video file exists in `uploads/ads/videos/`

## Database structure highlights

Relevant tables:
- `admins`
- `categories`
- `news`
- `news_gallery`
- `advertisements`
- `journalist_requests`
- `settings`

## Configuration notes

### File paths in `includes/config.php`
- `UPLOAD_PATH` → server folder for uploads
- `UPLOAD_URL` → public URL prefix for uploaded files

### Settings helper
- `getSetting($key)` reads values from `settings` table
- used for social links, video URLs, radio/live event settings, etc.

## Troubleshooting

### Video ad not showing

- Confirm the active `banner_top` ad has a non-empty `video` field
- Confirm the file exists under `uploads/ads/videos/`
- Make sure the ad is active and not overridden by an older `banner_top` image ad

### Undefined variable warnings

- If you see a warning about `$yt_channel`, it should be defined in `article.php` using `getSetting('youtube_url')`.

## Useful paths

- Admin login: `admin/login.php`
- Ads manager: `admin/ads.php`
- Settings: `admin/settings.php`
- Homepage: `index.php`
- Article page: `article.php`
- Main header include: `includes/header.php`
- Main config: `includes/config.php`

## Notes

- The site uses language variants with `title_rw`, `title_en`, `title_sw`, and `title_fr` fields.
- Additional customizations can be made in `assets/css/style.css` and `assets/js/main.js`.
- If you change `SITE_URL`, update both `SITE_URL` and `UPLOAD_URL` in `includes/config.php`.
