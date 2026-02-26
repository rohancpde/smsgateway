<p align="center">
  <img src="https://img.icons8.com/3d-fluency/94/chat-message.png" alt="SMS Gateway Logo" width="94" height="94"/>
</p>

<h1 align="center">SMS Gateway</h1>

<p align="center">
  <strong>A self-hosted, open-source SMS gateway that turns Android phones into programmable SMS sending devices.</strong>
</p>

<p align="center">
  <a href="https://opensource.org/licenses/MIT"><img src="https://img.shields.io/badge/License-MIT-blue.svg" alt="License: MIT"/></a>
  <a href="https://www.php.net/"><img src="https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white" alt="PHP 8.0+"/></a>
  <a href="https://flutter.dev/"><img src="https://img.shields.io/badge/Flutter-3.0%2B-02569B?logo=flutter&logoColor=white" alt="Flutter 3.0+"/></a>
  <a href="https://www.mysql.com/"><img src="https://img.shields.io/badge/MySQL-5.7%2B%20%7C%208.0%2B-4479A1?logo=mysql&logoColor=white" alt="MySQL"/></a>
  <a href="https://github.com/eros1sh/smsgateway/issues"><img src="https://img.shields.io/github/issues/eros1sh/smsgateway" alt="Issues"/></a>
  <a href="https://github.com/eros1sh/smsgateway/stargazers"><img src="https://img.shields.io/github/stars/eros1sh/smsgateway?style=social" alt="Stars"/></a>
</p>

<p align="center">
  <a href="#quick-start">Quick Start</a> &bull;
  <a href="#features">Features</a> &bull;
  <a href="#api-documentation">API Docs</a> &bull;
  <a href="#installation">Installation</a> &bull;
  <a href="#contributing">Contributing</a>
</p>

---

## Table of Contents

- [Overview](#overview)
- [Architecture](#architecture)
- [Features](#features)
- [Quick Start](#quick-start)
- [Installation](#installation)
  - [Backend Setup](#backend-setup)
  - [Android App Setup](#android-app-setup)
- [Configuration Reference](#configuration-reference)
- [API Documentation](#api-documentation)
  - [Authentication](#authentication)
  - [Device Endpoints](#device-endpoints)
  - [Admin Endpoints](#admin-endpoints)
  - [Public Endpoints](#public-endpoints)
- [Cron Jobs](#cron-jobs)
- [Database Schema](#database-schema)
- [Project Structure](#project-structure)
- [Security](#security)
- [Contributing](#contributing)
- [License](#license)

---

## Overview

**SMS Gateway** is a fully self-hosted SMS gateway system that allows you to send and receive SMS messages through real Android devices. Instead of relying on expensive third-party SMS APIs, you connect your own Android phones, and the system handles queuing, routing, load balancing, delivery tracking, and much more.

The system is composed of three components:

| Component | Technology | Purpose |
|-----------|-----------|---------|
| **Backend API** | PHP 8.0+ (vanilla) / MySQL | REST API managing message queues, routing, device coordination, and all business logic |
| **Admin Panel** | Vanilla HTML / CSS / JS | Single-page admin dashboard for full system management |
| **Android App** | Flutter / Dart 3.0+ | Runs on Android phones, polls for pending messages, sends them via native SMS |

---

## Architecture

```
                         +---------------------+
                         |    Admin Panel       |
                         |   (admin.html)       |
                         +----------+----------+
                                    |
                                    | HTTPS (Admin Token)
                                    v
+----------------+       +---------------------+       +------------------+
| Your App /     | ----> |   PHP Backend API   | <---- | Android Device 1 |
| External       | HTTPS |   (api.php)         | Poll  | (Flutter App)    |
| Service        |       |                     |       +------------------+
+----------------+       |  +---------------+  |       +------------------+
                         |  |   MySQL DB    |  | <---- | Android Device 2 |
                         |  +---------------+  | Poll  | (Flutter App)    |
                         +---------------------+       +------------------+
                                    |                  +------------------+
                                    +----------------- | Android Device N |
                                              Poll     | (Flutter App)    |
                                                       +------------------+
```

**How it works:**

1. Your application or the admin panel sends an SMS request to the backend API.
2. The backend queues the message and assigns it to an available device based on the configured load balancing strategy.
3. The Android app polls the API at regular intervals, picks up pending messages, and sends them via the phone's native SMS capability.
4. The app reports the delivery status back to the API.
5. The admin panel provides real-time visibility into the entire pipeline via SSE streaming.

---

## Features

### Core SMS Engine
- **Single and Bulk SMS** -- Send to one recipient or thousands at once
- **Priority Queue** -- 10 priority levels (1 = highest, 10 = lowest) with intelligent scheduling
- **Status Tracking** -- Full lifecycle tracking: `pending` > `processing` > `sent` > `delivered` > `failed` > `cancelled`
- **Retry Mechanism** -- Automatic retries with exponential backoff for failed messages
- **Multi-Part SMS** -- GSM-7 and UCS-2 encoding support with accurate part calculation
- **Scheduled Sending** -- Queue messages for future delivery at a specific time
- **SMS Templates** -- Reusable templates with `{{variable}}` substitution
- **Batch Operations** -- Group messages with `batch_id` for tracking bulk sends

### Device Management
- **Multi-Device Support** -- Connect unlimited Android phones as SMS senders
- **Health Monitoring** -- Real-time online/offline status with `last_seen` tracking
- **SIM Slot Selection** -- Choose SIM1 or SIM2 per device or per message
- **Per-Device Rate Limiting** -- Control SMS throughput per device
- **Soft Delete** -- Deactivate devices without losing history
- **Performance Scoring** -- Weighted score (60% success rate + 40% uptime) with trend tracking
- **Remote Commands** -- Send commands to devices: `restart_service`, `sync_config`, `update_app`, `clear_queue`, `send_log`, or any `custom` command
- **Device Info Collection** -- Automatically collects IMEI, Android version, SDK level, device model, brand, manufacturer, hardware ID, board, build fingerprint, display resolution, and app version during registration

### Advanced Load Balancing
- **Weighted Distribution** -- Assign weights (1-100) to each device
- **Performance-Based Routing** -- Route messages to highest-performing devices
- **Cost-Optimized Distribution** -- Minimize costs by routing to cheapest devices first
- **Auto-Scaling** -- Automatically activate/deactivate devices based on queue size, fail rate, response time, or throughput

### Incoming SMS and Auto-Reply
- **Incoming SMS Capture** -- Devices listen for and report incoming SMS to the backend
- **Auto-Reply Rules** -- Create rules with match types: `exact`, `contains`, `starts_with`, `regex`
- **Rule Management** -- Full CRUD operations for auto-reply rules

### OTP System
- **One-Time Password Generation** -- Configurable code length (4-8 digits) and TTL
- **Brute-Force Protection** -- Maximum attempt limits per code
- **Rate Limiting** -- 5 OTP requests per hour per phone number
- **Custom Templates** -- Use `{{code}}` placeholder in custom message templates
- **Verification Endpoint** -- Simple API call to verify submitted codes

### URL Shortener
- **Short Link Creation** -- Generate trackable short URLs
- **Click Tracking** -- Monitor link engagement
- **Configurable TTL** -- Links auto-expire after a set duration
- **Public Redirect** -- Clean `/r/{code}` redirect endpoint

### Contact and Blacklist Management
- **Contact Groups** -- Organize contacts with group-based categorization
- **Contact Metadata** -- Attach arbitrary JSON metadata to contacts
- **CSV Import** -- Import phone numbers from CSV with automatic header detection and variable substitution
- **Blacklist** -- Block specific phone numbers from receiving messages

### Anomaly Detection and Alerts
- **Detection Rules** -- Monitor for: `spike`, `flood`, `night_activity`, `high_fail_rate`, `same_number`
- **Automatic Actions** -- Respond with: `alert`, `block_device`, `block_number`, `throttle`
- **Multi-Channel Alerts** -- Notify via email, SMS, Telegram, Slack, or webhook
- **Event Tracking** -- Full anomaly event log with resolve capability

### Analytics and Monitoring
- **Cost Tracking** -- Per-device `cost_per_sms` configuration with daily aggregation and reporting
- **Heatmap Analytics** -- Hourly SMS activity heatmap with color-coded intensity
- **Uptime Monitoring** -- API and device uptime tracking with 30-day percentage
- **Audit Logging** -- Comprehensive trail with actor, IP, user agent, and action tracking
- **SMS Queue Visualization** -- Real-time queue stats, per-device breakdown, throughput charts, and processing time

### Real-Time Features
- **Server-Sent Events (SSE)** -- Live dashboard updates without polling
- **Live Queue Stream** -- Minute-by-minute throughput and queue depth monitoring

### Webhook System
- **Event Webhooks** -- Subscribe to `sms.sent`, `sms.failed`, `device.offline` events
- **HMAC Verification** -- Signed webhook payloads for secure integrations

### Public Status Page
- **No-Auth Status Page** -- Standalone `status.html` page for public visibility
- **Component Tracking** -- Monitor API, Database, SMS Sending, Device Network
- **Incident Management** -- Create, update, and resolve incidents with severity levels (minor, major, critical)
- **Automatic Status Updates** -- Cron-driven health checks update component status in real time

### API Versioning
- **Version Management** -- Create and manage API versions (v1, v2, etc.)
- **Deprecation Support** -- Set deprecation and sunset dates for older versions
- **Version Changelog** -- Track changes across API versions

### Data Export
- **CSV Export** -- Export SMS data, devices, and audit logs
- **Filtered Exports** -- Filter by date range and device
- **Excel Compatible** -- UTF-8 BOM encoding for proper Excel rendering

### API Key Rotation
- **Key Rotation** -- Rotate device API keys with zero downtime
- **Grace Period** -- 24-hour overlap where both old and new keys are accepted
- **Key History** -- Full audit trail of key rotations

---

## Quick Start

Get up and running in 5 minutes:

```bash
# 1. Clone the repository
git clone https://github.com/eros1sh/smsgateway.git
cd smsgateway

# 2. Set up the database
mysql -u root -p -e "CREATE DATABASE smsgateway CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p smsgateway < web/database.sql

# 3. Configure environment
cp web/.env.example web/.env
# Edit web/.env with your database credentials and secrets

# 4. Set up the cron job
echo "* * * * * php $(pwd)/web/cron.php" | crontab -

# 5. Start a local PHP server (for testing only)
php -S 0.0.0.0:8080 -t web/

# 6. Open admin panel in your browser
# Visit http://localhost:8080/admin.html
```

Then build and install the Android app on your phone, enter the API URL and registration secret, and your gateway is live.

---

## Installation

### Backend Setup

#### Requirements

- PHP 8.0 or higher
- MySQL 5.7+ or 8.0+
- Apache (with `mod_rewrite`) or Nginx
- PHP extensions: `pdo`, `pdo_mysql`, `json`, `mbstring`

#### Step 1: Deploy Files

Upload or clone the repository to your server. Point your web server's document root to the `web/` directory.

```bash
git clone https://github.com/eros1sh/smsgateway.git /var/www/smsgateway
```

#### Step 2: Create the Database

```sql
CREATE DATABASE smsgateway CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'smsgateway'@'localhost' IDENTIFIED BY 'your_strong_password_here';
GRANT ALL PRIVILEGES ON smsgateway.* TO 'smsgateway'@'localhost';
FLUSH PRIVILEGES;
```

Import the schema:

```bash
mysql -u smsgateway -p smsgateway < /var/www/smsgateway/web/database.sql
```

#### Step 3: Configure Environment

```bash
cp web/.env.example web/.env
```

Edit `web/.env` with your values:

```ini
# Database
DB_HOST=localhost
DB_PORT=3306
DB_NAME=smsgateway
DB_USER=smsgateway
DB_PASS=your_strong_password_here

# Security - generate strong random values for these
ADMIN_TOKEN=your_admin_token_here
REGISTRATION_SECRET=your_registration_secret_here
CORS_ALLOWED_ORIGINS=https://yourdomain.com
RATE_LIMIT_REQUESTS=60
RATE_LIMIT_WINDOW=60

# Application
TIMEZONE=UTC
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
```

> **Tip:** Generate strong random tokens with: `openssl rand -hex 32`

#### Step 4: Web Server Configuration

**Apache** -- The included `.htaccess` file handles URL rewriting. Make sure `mod_rewrite` is enabled:

```bash
a2enmod rewrite
systemctl restart apache2
```

**Nginx** -- Add the following to your server block:

```nginx
server {
    listen 443 ssl http2;
    server_name yourdomain.com;
    root /var/www/smsgateway/web;
    index api.php;

    location / {
        try_files $uri $uri/ /api.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~* \.(html|css|js|png|jpg|ico)$ {
        expires 1d;
        add_header Cache-Control "public, immutable";
    }

    # Deny access to sensitive files
    location ~ /\.(env|htaccess) {
        deny all;
    }
}
```

#### Step 5: Set Up Cron

The system requires a cron job running every minute to handle retries, cleanup, anomaly detection, auto-scaling, and 16 other scheduled tasks:

```bash
* * * * * php /var/www/smsgateway/web/cron.php >> /var/log/smsgateway-cron.log 2>&1
```

#### Step 6: Verify Installation

1. Open `https://yourdomain.com/admin.html` in your browser.
2. Enter your `ADMIN_TOKEN` when prompted.
3. The dashboard should load with empty statistics, ready for device connections.

---

### Android App Setup

#### Requirements

- Flutter SDK 3.0+
- Android Studio or VS Code with Flutter plugin
- An Android phone (physical device required for SMS sending)

#### Build the App

```bash
cd app
flutter pub get
flutter build apk --release
```

The release APK will be at:
```
app/build/app/outputs/flutter-apk/app-release.apk
```

#### Install and Configure

1. Transfer the APK to your Android phone and install it.
2. Open the app and enter:
   - **API URL**: `https://yourdomain.com` (your backend URL)
   - **Registration Secret**: The `REGISTRATION_SECRET` value from your `.env` file
3. The app will register itself with the backend and generate an API key.
4. Grant SMS and notification permissions when prompted.
5. The device will appear in the admin panel as a new active device.

The app runs as a persistent background service, continuously polling for pending messages and sending them via the phone's native SMS capability.

---

## Configuration Reference

All configuration is managed through the `web/.env` file.

| Variable | Default | Description |
|----------|---------|-------------|
| `DB_HOST` | `localhost` | MySQL server hostname |
| `DB_PORT` | `3306` | MySQL server port |
| `DB_NAME` | `smsgateway` | Database name |
| `DB_USER` | `smsgateway` | Database username |
| `DB_PASS` | *(empty)* | Database password |
| `ADMIN_TOKEN` | *(empty)* | Admin authentication token for the admin panel and admin API endpoints |
| `REGISTRATION_SECRET` | *(empty)* | Secret key required for new device registration |
| `CORS_ALLOWED_ORIGINS` | *(empty)* | Comma-separated list of allowed CORS origins |
| `RATE_LIMIT_REQUESTS` | `60` | Maximum API requests per window |
| `RATE_LIMIT_WINDOW` | `60` | Rate limit window in seconds |
| `TIMEZONE` | `Europe/Istanbul` | Server timezone (PHP-compatible timezone string) |
| `APP_ENV` | `production` | Environment mode: `production` or `development` |
| `APP_DEBUG` | `false` | Enable debug mode (`true` / `false`) |
| `APP_URL` | *(empty)* | Public application URL (used for short links and redirects) |
| `MAX_SMS_LENGTH` | `918` | Maximum SMS message length in characters |
| `MAX_BULK_SIZE` | `500` | Maximum number of recipients per bulk send operation |
| `SMS_RETRY_MAX` | `3` | Maximum retry attempts for failed messages |
| `DEVICE_OFFLINE_THRESHOLD` | `300` | Seconds of inactivity before a device is marked offline |

---

## API Documentation

### Authentication

The API uses two authentication methods:

| Method | Header | Used By |
|--------|--------|---------|
| **Device API Key** | `Authorization: Bearer <api_key>` | Android devices -- for polling messages, reporting status, and receiving commands |
| **Admin Token** | `Authorization: Bearer <admin_token>` | Admin panel and external integrations -- for all management operations |

API keys are hashed with SHA-256 before storage. They are never stored or logged in plaintext after initial device registration.

---

### Device Endpoints

These endpoints are authenticated with a device API key.

#### Get Pending Messages

```http
GET /pending
Authorization: Bearer <device_api_key>
```

Returns pending SMS messages assigned to the authenticated device.

**Response:**
```json
{
  "success": true,
  "count": 2,
  "messages": [
    {
      "id": 101,
      "phone_number": "+1234567890",
      "message": "Hello from SMS Gateway!",
      "priority": 5,
      "sim_slot": 0,
      "scheduled_at": null
    }
  ]
}
```

#### Update SMS Status

```http
POST /update-status
Authorization: Bearer <device_api_key>
Content-Type: application/json

{
  "request_id": 101,
  "status": "sent"
}
```

Status values: `sent`, `delivered`, `failed`

#### Report Incoming SMS

```http
POST /report-incoming
Authorization: Bearer <device_api_key>
Content-Type: application/json

{
  "from": "+1234567890",
  "message": "Reply text here",
  "received_at": "2025-01-15T10:30:00Z"
}
```

#### Send Manual SMS

```http
POST /send-manual-sms
Authorization: Bearer <device_api_key>
Content-Type: application/json

{
  "phone_number": "+1234567890",
  "message": "Manual SMS from device"
}
```

#### Poll for Remote Commands

```http
GET /poll-commands
Authorization: Bearer <device_api_key>
```

Returns pending commands for the device (polled every 30 seconds).

#### Report Command Result

```http
POST /command-result
Authorization: Bearer <device_api_key>
Content-Type: application/json

{
  "command_id": 45,
  "status": "completed",
  "result": "Service restarted successfully"
}
```

---

### Admin Endpoints

These endpoints are authenticated with the admin token.

#### Send SMS

```http
POST /send-sms
Authorization: Bearer <admin_token>
Content-Type: application/json

{
  "phone_number": "+1234567890",
  "message": "Hello from SMS Gateway!",
  "device_id": "device_001",
  "priority": 5,
  "sim_slot": 0
}
```

#### Send Bulk SMS

```http
POST /send-bulk
Authorization: Bearer <admin_token>
Content-Type: application/json

{
  "phone_numbers": ["+1234567890", "+0987654321", "+1122334455"],
  "message": "Bulk message content",
  "device_id": "device_001"
}
```

#### Distribute SMS Across Devices

```http
POST /distribute-sms
Authorization: Bearer <admin_token>
Content-Type: application/json

{
  "phone_numbers": ["+1...", "+2...", "+3...", "+4...", "+5...", "+6..."],
  "message": "Distributed message",
  "device_ids": ["device_001", "device_002", "device_003"]
}
```

Messages are distributed equally across the selected devices.

#### Send OTP

```http
POST /send-otp
Authorization: Bearer <admin_token>
Content-Type: application/json

{
  "phone_number": "+1234567890",
  "code_length": 6,
  "ttl": 300,
  "max_attempts": 3,
  "message_template": "Your verification code is: {{code}}"
}
```

#### Verify OTP

```http
POST /verify-otp
Authorization: Bearer <admin_token>
Content-Type: application/json

{
  "phone_number": "+1234567890",
  "code": "482916"
}
```

#### Create Short Link

```http
POST /create-short-link
Authorization: Bearer <admin_token>
Content-Type: application/json

{
  "url": "https://example.com/very/long/landing-page-url",
  "ttl": 86400
}
```

#### Check SMS Status

```http
GET /sms-status?sms_id=101
Authorization: Bearer <admin_token>
```

#### Queue Statistics

```http
GET /queue-stats
Authorization: Bearer <admin_token>
```

Returns real-time queue statistics including pending/processing/sent/failed counts, per-device breakdown, and throughput metrics.

#### Real-Time Stream (SSE)

```http
GET /stream
Authorization: Bearer <admin_token>
Accept: text/event-stream
```

Server-Sent Events stream for live dashboard updates.

<details>
<summary><strong>View all admin endpoints</strong></summary>

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/send-sms` | Send a single SMS message |
| `POST` | `/send-bulk` | Send bulk SMS to multiple recipients |
| `GET` | `/sms-status` | Check the status of a specific SMS |
| `GET` | `/sms-history` | Retrieve SMS history with pagination |
| `GET` | `/devices` | List all registered devices |
| `POST` | `/device-toggle` | Toggle a device active or inactive |
| `DELETE` | `/device-delete` | Soft-delete a device |
| `GET` | `/device-health` | Retrieve device health metrics |
| `POST` | `/template-create` | Create a new SMS template |
| `GET` | `/templates` | List all SMS templates |
| `POST` | `/webhook-create` | Create a new webhook subscription |
| `GET` | `/webhooks` | List all webhooks |
| `POST` | `/contact-add` | Add a new contact |
| `GET` | `/contacts` | List all contacts |
| `POST` | `/blacklist-add` | Add a phone number to the blacklist |
| `GET` | `/blacklist` | List all blacklisted numbers |
| `DELETE` | `/blacklist-remove` | Remove a number from the blacklist |
| `GET` | `/stats` | Retrieve dashboard statistics |
| `POST` | `/distribute-sms` | Distribute SMS equally across devices |
| `GET` | `/incoming-sms` | List incoming SMS messages |
| `POST` | `/auto-reply-rule` | Create an auto-reply rule |
| `GET` | `/auto-reply-rules` | List all auto-reply rules |
| `DELETE` | `/auto-reply-rule` | Delete an auto-reply rule |
| `POST` | `/send-otp` | Generate and send an OTP code |
| `POST` | `/verify-otp` | Verify a submitted OTP code |
| `POST` | `/create-short-link` | Create a short URL |
| `GET` | `/short-links` | List all short links |
| `POST` | `/import-csv` | Import contacts from a CSV file |
| `POST` | `/rotate-api-key` | Rotate a device's API key |
| `GET` | `/api-key-history` | View API key rotation history |
| `GET` | `/anomaly-rules` | List anomaly detection rules |
| `POST` | `/anomaly-rule` | Create an anomaly detection rule |
| `GET` | `/anomaly-events` | List detected anomaly events |
| `POST` | `/resolve-anomaly` | Resolve an anomaly event |
| `POST` | `/alert-channel` | Create an alert notification channel |
| `GET` | `/alert-channels` | List all alert channels |
| `DELETE` | `/alert-channel` | Delete an alert channel |
| `GET` | `/cost-report` | Retrieve cost tracking report |
| `POST` | `/update-device-cost` | Update a device's cost per SMS |
| `GET` | `/heatmap-data` | Retrieve heatmap analytics data |
| `GET` | `/export-csv` | Export data as CSV |
| `GET` | `/device-scores` | Retrieve device performance scores |
| `GET` | `/uptime-status` | Retrieve system uptime status |
| `GET` | `/audit-logs` | Browse the audit log |
| `POST` | `/distribute-weighted` | Weighted load-balanced SMS distribution |
| `GET` | `/stream` | SSE real-time event stream |
| `POST` | `/device-command` | Send a remote command to a device |
| `GET` | `/device-commands` | List device command history |
| `GET` | `/queue-stats` | Retrieve SMS queue statistics |
| `GET` | `/queue-live` | SSE live queue monitoring stream |
| `POST` | `/autoscale-rule` | Create an auto-scaling rule |
| `GET` | `/autoscale-rules` | List all auto-scaling rules |
| `DELETE` | `/autoscale-rule` | Delete an auto-scaling rule |
| `GET` | `/autoscale-history` | View auto-scaling event history |
| `POST` | `/autoscale-toggle` | Toggle an auto-scaling rule on/off |
| `POST` | `/update-component-status` | Update a system component's status |
| `POST` | `/create-incident` | Create a status page incident |
| `POST` | `/update-incident` | Update an existing incident |
| `GET` | `/incidents` | List all status page incidents |
| `GET` | `/system-status` | Retrieve full system status (admin view) |
| `GET` | `/api-versions` | List all API versions |
| `POST` | `/api-version` | Create or update an API version |

</details>

---

### Public Endpoints

These endpoints require no authentication.

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/public-status` | Public system status data (JSON) |
| `GET` | `/r/{code}` | Short link redirect |
| `POST` | `/register-device` | Device self-registration (requires `registration_secret` in body) |

The public status page is also available as a standalone HTML file at `/status.html`.

---

## Cron Jobs

The cron system (`web/cron.php`) runs every minute and executes 20 scheduled tasks:

| # | Task | Description |
|---|------|-------------|
| 1 | Clean expired rate limits | Remove stale rate limit records from the database |
| 2 | Retry failed SMS | Re-queue failed messages with exponential backoff |
| 3 | Unstick processing SMS | Reset messages stuck in `processing` state for 5+ minutes |
| 4 | Detect offline devices | Mark devices as offline based on `DEVICE_OFFLINE_THRESHOLD` |
| 5 | Archive old SMS | Archive SMS records older than 90 days |
| 6 | Clean old audit logs | Purge audit logs older than 180 days |
| 7 | Update daily statistics | Aggregate daily SMS statistics |
| 8 | Update hourly statistics | Aggregate hourly stats for heatmap analytics |
| 9 | Anomaly detection engine | Evaluate all anomaly rules against current metrics |
| 10 | Uptime checks | Monitor API and device uptime, record response times |
| 11 | Device performance scoring | Calculate weighted performance scores for all devices |
| 12 | Cost records aggregation | Aggregate daily cost data per device |
| 13 | Clean expired OTPs | Remove OTP codes older than 24 hours |
| 14 | Clean expired short links | Remove short links expired for 30+ days |
| 15 | Clean old uptime records | Purge uptime records older than 30 days |
| 16 | Clean old score history | Purge performance score history older than 90 days |
| 17 | Deactivate expired API keys | Expire rotated API keys past their grace period |
| 18 | Auto-scaling execution | Evaluate auto-scaling rules and scale devices up or down |
| 19 | Clean expired device commands | Remove expired remote device commands |
| 20 | System status auto-update | Update component statuses on the public status page |

---

## Database Schema

The system uses **20+ MySQL tables** with InnoDB engine and `utf8mb4` character set. The complete schema is available in [`web/database.sql`](web/database.sql).

Key tables include:

| Table | Purpose |
|-------|---------|
| `users` | Admin users with role-based access (admin / user / viewer) and optional TOTP 2FA |
| `devices` | Registered Android devices with performance metrics and load balancing weights |
| `sms_requests` | SMS message queue with full lifecycle tracking |
| `sms_templates` | Reusable message templates with variable placeholders |
| `incoming_sms` | Captured incoming SMS from devices |
| `auto_reply_rules` | Auto-reply rule definitions |
| `otp_codes` | OTP codes with attempt tracking |
| `short_links` | URL shortener records with click counts |
| `contacts` | Contact directory with group and metadata support |
| `blacklist` | Blocked phone numbers |
| `anomaly_rules` | Anomaly detection rule configuration |
| `anomaly_events` | Detected anomaly event log |
| `alert_channels` | Alert notification channel configuration |
| `audit_logs` | Comprehensive audit trail |
| `webhooks` | Webhook subscription registry |
| `device_commands` | Remote command queue for devices |
| `autoscale_rules` | Auto-scaling rule configuration |
| `status_components` | System component health status |
| `status_incidents` | Status page incident records |
| `api_versions` | API version registry |

---

## Project Structure

```
smsgateway/
├── web/                          # Backend & Admin Panel
│   ├── api.php                   # Main API handler (all REST endpoints)
│   ├── config.php                # Configuration loader & database connection
│   ├── middleware.php            # Authentication, rate limiting, security headers
│   ├── validation.php           # Input validation helpers
│   ├── cron.php                 # Scheduled tasks runner (20 tasks)
│   ├── database.sql             # Complete MySQL schema with indexes
│   ├── admin.html               # Admin panel (single-page application)
│   ├── status.html              # Public status page
│   ├── .htaccess                # Apache URL rewriting rules
│   └── .env.example             # Environment configuration template
│
├── app/                          # Flutter Android Application
│   ├── lib/
│   │   ├── main.dart            # Application entry point
│   │   ├── models/
│   │   │   └── sms_request.dart # SMS request data model
│   │   ├── screens/
│   │   │   ├── home_screen.dart        # Main dashboard screen
│   │   │   ├── setup_screen.dart       # Initial device setup & registration
│   │   │   ├── sms_history_screen.dart # SMS history viewer
│   │   │   └── manual_sms_screen.dart  # Manual SMS composition
│   │   └── services/
│   │       ├── api_service.dart            # Backend API client
│   │       ├── sms_service.dart            # Native SMS sending service
│   │       └── secure_storage_service.dart # Encrypted credential storage
│   ├── pubspec.yaml             # Flutter dependencies
│   ├── android/                 # Android platform configuration
│   └── assets/
│       └── icon.png             # Application icon
│
└── README.md                    # This file
```

---

## Security

SMS Gateway implements multiple layers of security:

### Authentication and Authorization
- **SHA-256 hashed API keys** -- Device API keys are hashed before storage; plaintext keys are never persisted after registration
- **Admin token authentication** -- Separate strong token for all admin operations
- **Registration secret** -- Devices must present a shared secret to register
- **Role-based access** -- Admin, user, and viewer roles in the user system
- **API key rotation** -- Zero-downtime key rotation with 24-hour grace period

### Transport and Headers
- **CORS** -- Configurable allowed origins (never wildcard in production)
- **OWASP security headers** on all API responses:
  - `X-Content-Type-Options: nosniff`
  - `X-Frame-Options: DENY`
  - `X-XSS-Protection: 1; mode=block`
  - `Strict-Transport-Security` (when served over HTTPS)
  - `Referrer-Policy: strict-origin-when-cross-origin`

### Input Validation and Data Protection
- **Prepared statements** -- All database queries use PDO prepared statements (no SQL injection)
- **Input validation** -- Schema-based validation on all API inputs via dedicated validation layer
- **XSS protection** -- All user-supplied data is escaped in the admin panel
- **Rate limiting** -- Configurable requests-per-window with automatic cleanup of expired records

### Monitoring and Incident Response
- **Audit logging** -- All admin actions are logged with actor, IP address, and user agent
- **Anomaly detection** -- Automatic detection of suspicious patterns with configurable automatic response actions
- **Public status page** -- Transparent system health visibility for stakeholders

---

## Contributing

Contributions are welcome. Here is how to get started:

1. **Fork** the repository on GitHub.
2. **Create a feature branch** from `main`:
   ```bash
   git checkout -b feature/your-feature-name
   ```
3. **Make your changes** and test thoroughly.
4. **Commit** with clear, descriptive messages:
   ```bash
   git commit -m "Add delivery receipt support via webhook"
   ```
5. **Push** to your fork:
   ```bash
   git push origin feature/your-feature-name
   ```
6. **Open a Pull Request** against `main` with a description of your changes.

### Guidelines

- Follow the existing code style and conventions.
- Keep PHP vanilla (no frameworks) to maintain the lightweight, zero-dependency architecture.
- All database queries must use prepared statements with parameter binding.
- All user-facing output must be properly escaped.
- Test on a real Android device before submitting mobile app changes.
- Update `database.sql` if your changes require schema modifications.
- Include new cron tasks in `cron.php` if your feature requires scheduled processing.

### Reporting Issues

Found a bug or have a feature request? [Open an issue](https://github.com/eros1sh/smsgateway/issues) with:
- A clear title and description
- Steps to reproduce (for bugs)
- Your environment details (PHP version, MySQL version, Android version)
- Relevant log output if applicable

---

## Disclaimer

This software is an open-source project developed for building SMS sending infrastructure. All responsibility arising from the use of this software belongs to the user.

- This software is provided "AS IS" without warranty of any kind.
- By using this software, you agree to comply with the telecommunications and data protection laws of your jurisdiction.
- Use for unsolicited bulk messaging, spam, or any illegal activity is strictly prohibited.
- For commercial use, obtaining the necessary permits from relevant telecommunications authorities is the user's responsibility.
- The developer assumes no liability for any legal, financial, or criminal consequences resulting from the use of this software.

**By using this software, you accept these terms. If you do not accept these terms, do not use this software.**

---

## License

This project is licensed under the **MIT License**.

```
MIT License

Copyright (c) 2025 eros1sh

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

---

<p align="center">
  Built with purpose. Send with confidence.
  <br/><br/>
  <a href="https://github.com/eros1sh/smsgateway">GitHub</a> &middot;
  <a href="https://github.com/eros1sh/smsgateway/issues">Issues</a> &middot;
  <a href="https://github.com/eros1sh/smsgateway/pulls">Pull Requests</a>
</p>
