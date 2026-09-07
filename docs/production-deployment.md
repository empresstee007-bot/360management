# Production Deployment

Deploy the project folder to:

`/home/managem1/domains/360management.name.ng/public_html`

Copy `.env.production.example` to `.env` on the server and fill in the real database and mail values.

Minimum required production values:

```dotenv
APP_ENV=production
APP_REQUIRE_DATABASE=1
APP_BASE_URL=https://360management.name.ng/public

DB_HOST=localhost
DB_PORT=3306
DB_NAME=
DB_USER=
DB_PASS=
```

Import `database/schema.sql` into the production database before first login.

Create real users in the `users` table with `password_hash()` hashes. Do not use demo passwords in production.

Optional mail settings:

```dotenv
MAIL_ENABLED=1
MAIL_FROM=no-reply@360management.name.ng
MAIL_SMTP_HOST=mail.360management.name.ng
MAIL_SMTP_PORT=465
MAIL_SMTP_ENCRYPTION=ssl
MAIL_SMTP_USER=no-reply@360management.name.ng
MAIL_SMTP_PASS=
PAYMENT_RECON_IMAP_USER=
PAYMENT_RECON_IMAP_SECRET=
```

Google product image search requires both values below. Without them, manual URL/upload images still work and the system uses local beverage image fallbacks.

```dotenv
GOOGLE_IMAGE_SEARCH_API_KEY=
GOOGLE_IMAGE_SEARCH_CX=
```

Security notes:

- Keep `.env`, `app/`, `database/`, and `docs/` blocked from browser access.
- Keep `APP_ALLOW_PUBLIC_UTILITIES=0`.
- Only expose the application through the `public/` entrypoints.
