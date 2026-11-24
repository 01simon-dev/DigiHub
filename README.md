## DigiHub

DigiHub is a digital marketplace for creators to publish and share e-books, comics, artwork, and other digital work. The interface uses a white and burgundy theme and includes creator and admin dashboards.

## Features

- Browse and search published digital products
- Creator registration, product uploads, and creator dashboards
- Product previews, comments, likes, and reporting
- Checkout and download access
- Admin tools for managing products, users, and support
- Responsive layout with light and dark themes

## Requirements

- PHP with PDO and the PDO MySQL driver
- MySQL or MariaDB
- Apache with `mod_rewrite` and `.htaccess` support (for the included routes)

## Setup

1. Place the project in your web server's document root.
2. Create a MySQL database and configure a matching DigiHub schema. This repository does not currently include a schema or migration script.
3. For local development, set up the database connection in `includes/config.php` using the `DIGIHUB_DB_HOST`, `DIGIHUB_DB_NAME`, `DIGIHUB_DB_USER`, and `DIGIHUB_DB_PASS` environment variables, or use the local defaults in that file.
4. On hosting that requires file-based credentials, copy `includes/hosting.example.php` to `includes/hosting.php` and enter the hosting database details there. Do not commit `includes/hosting.php`.
5. Ensure the web server can write to the required directories under `uploads/`. Uploaded files are intentionally excluded from Git; the `.gitkeep` files only preserve the directory structure.
6. Open the site through the configured web server. The root route uses the included `.htaccess` to serve the public landing page.

## Project layout

- `includes/` — configuration, database access, authentication, and shared functions
- `public/` — public pages, admin and creator areas, styles, scripts, and vendored Font Awesome assets
- `uploads/` — runtime storage for product files, artwork, previews, and receipts; content is not tracked

## Security notes

- Keep database credentials out of version control.
- The `uploads/` directory is protected from direct access; downloads are served through the application.
- Use HTTPS and secure, unique credentials in production.

## License

See [LICENSE](LICENSE).
