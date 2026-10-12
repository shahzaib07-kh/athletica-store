# ATHLETICA — Production Deployment Guide & Checklist

This document details the production deployment architecture, step-by-step installation instructions, environment configuration, and verification procedures for **ATHLETICA**.

---

## 1. System Architecture Overview

```
                      [ Client Browser / HTTPS ]
                                  │
                       Cloudflare DNS / SSL (443)
                                  │
                     [ Nginx Unified Web Server ]
                                  │
         ┌────────────────────────┼────────────────────────┐
         │ (/_next/* & HTML)      │ (/api/* & /admin/*)    │ (/uploads/*)
         ▼                        ▼                        ▼
  [ Next.js Node.js ]       [ PHP 8.2-FPM ]        [ Static Files ]
   PM2 Cluster :3000         UNIX Socket            Direct from Disk
         │                        │
         └───────────┬────────────┘
                     │ PDO Connection
                     ▼
          [ MySQL 8.0+ Database ]
           (athletica_prod)
```

---

## 2. Server Requirements

* **Operating System:** Ubuntu 22.04 LTS or Debian 12 (recommended)
* **Web Server:** Nginx 1.18+ (or Apache 2.4+)
* **PHP Engine:** PHP 8.2+ with extensions:
  * `php8.2-fpm`, `php8.2-mysql`, `php8.2-mbstring`, `php8.2-curl`, `php8.2-fileinfo`, `php8.2-xml`, `php8.2-gd`
* **Database:** MySQL 8.0+ or MariaDB 10.5+ (`utf8mb4_unicode_ci` charset)
* **Node.js Engine:** Node.js 20.x or 22.x LTS + `npm`
* **Process Manager:** `pm2` (`npm install -g pm2`)
* **SSL / TLS:** Let's Encrypt Certbot (`certbot python3-certbot-nginx`)

---

## 3. Step-by-Step Deployment Instructions

### Step 1: Database Setup
1. Log in to MySQL as root:
   ```bash
   mysql -u root -p
   ```
2. Create production database and dedicated user:
   ```sql
   CREATE DATABASE athletica_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'athletica_user'@'localhost' IDENTIFIED BY 'STRONG_RANDOM_PASSWORD_HERE';
   GRANT SELECT, INSERT, UPDATE, DELETE ON athletica_prod.* TO 'athletica_user'@'localhost';
   FLUSH PRIVILEGES;
   EXIT;
   ```
3. Import the database schema:
   ```bash
   mysql -u athletica_user -p athletica_prod < database/schema.sql
   ```
4. Configure database connection:
   ```bash
   cp config/database.production.example.php config/database.php
   # Edit config/database.php with your DB credentials
   ```

---

### Step 2: PHP Backend & Permissions Setup
1. Set proper directory permissions:
   ```bash
   chown -R www-data:www-data /var/www/athletica
   chmod -R 755 /var/www/athletica
   chmod -R 775 /var/www/athletica/uploads
   ```
2. Verify PHP syntax and test suites:
   ```bash
   php tests/test_api.php
   php tests/test_standalone.php
   ```

---

### Step 3: Next.js Frontend Build & PM2 Daemon
1. Navigate to the frontend directory:
   ```bash
   cd /var/www/athletica/frontend
   ```
2. Create production environment file:
   ```bash
   cp .env.production.example .env.production
   # Verify NEXT_PUBLIC_SITE_URL=https://athletica.pk
   ```
3. Install production dependencies and build:
   ```bash
   npm ci
   npm run build
   ```
4. Start Next.js with PM2:
   ```bash
   pm2 start ecosystem.config.js --env production
   pm2 save
   pm2 startup
   ```

---

### Step 4: Nginx Unified Web Server Configuration
1. Copy the Nginx template:
   ```bash
   sudo cp deployment/nginx.conf.example /etc/nginx/sites-available/athletica.pk
   sudo ln -s /etc/nginx/sites-available/athletica.pk /etc/nginx/sites-enabled/
   ```
2. Obtain SSL Certificate:
   ```bash
   sudo certbot --nginx -d athletica.pk -d www.athletica.pk
   ```
3. Test and reload Nginx:
   ```bash
   sudo nginx -t
   sudo systemctl reload nginx
   ```

---

## 4. Environment Variables Reference

| Variable | Scope | Purpose | Production Value Example |
| :--- | :--- | :--- | :--- |
| `NEXT_PUBLIC_SITE_URL` | Frontend | Canonical site URL for metadata, sitemaps, robots | `https://athletica.pk` |
| `NEXT_PUBLIC_MEDIA_URL` | Frontend | Media base URL for images stored in `/uploads/*` | `https://athletica.pk` |
| `PHP_API_URL` | Frontend | Internal REST API endpoint for SSR/Reverse-Proxy | `http://127.0.0.1:8080/api` or `/api` |
| `DB_HOST` | Backend | MySQL host | `127.0.0.1` |
| `DB_NAME` | Backend | Database name | `athletica_prod` |
| `DB_USER` | Backend | Database user | `athletica_user` |
| `DB_PASSWORD` | Backend | Secure database password | `[Secret]` |
| `CORS_ALLOWED_ORIGINS` | Backend | Allowed CORS origins for external callers | `https://athletica.pk,https://www.athletica.pk` |

---

## 5. Pre-Flight Verification Checklist

Before opening public traffic, perform the following verification steps:

- [ ] **Database Connection:** `php test_connection.php` passes with 7/7 tables found.
- [ ] **REST API Tests:** `php tests/test_api.php` passes with 14/14 tests.
- [ ] **Monolith Backend Tests:** `php tests/test_standalone.php` passes with 87/87 tests.
- [ ] **Next.js Typecheck & Build:** `npm run typecheck` and `npm run build` succeed with 0 errors.
- [ ] **Uploads Security:** Verify `.htaccess` and Nginx block PHP execution inside `/uploads/`.
- [ ] **Admin Authentication:** Log in at `https://athletica.pk/admin/login.php` and verify session persistence.
- [ ] **Sitemap & Robots:** Verify `https://athletica.pk/sitemap.xml` and `https://athletica.pk/robots.txt` respond with 200 OK.
- [ ] **End-to-End Order:** Submit a test Cash on Delivery order and verify:
  - Atomic claim on unique items (`is_available = 0`).
  - Correct integer-cent pricing.
  - WhatsApp deep link opens with pre-filled order confirmation message.
- [ ] **Rollback Capability:** Ensure PHP monolithic storefront (`index.php`) remains accessible if emergency fallback is ever required.
