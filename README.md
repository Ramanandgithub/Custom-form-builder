# FormBuilder — PHP Form Builder

A dynamic PHP Form Builder that allows users to create, manage, and submit forms without writing code.

## Tech Stack
- **Backend**: Core PHP (No Frameworks)
- **Database**: MySQL
- **Frontend**: HTML, CSS, JavaScript (Vanilla)
- **Auth**: JWT (HS256)

## Features
- ✅ Admin panel with JWT authentication
- ✅ Create/edit/delete forms
- ✅ 8 field types: Text, Email, Number, Textarea, Dropdown, Radio, Checkbox, File Upload
- ✅ Drag & drop field reordering
- ✅ Public form URLs
- ✅ Client & server-side validation
- ✅ Submission management with date/time
- ✅ CSV export
- ✅ REST API (CRUD)
- ✅ Prepared statements & XSS protection
- ✅ Database migrations

## Requirements
- PHP 8.1+
- MySQL 5.7+ or MariaDB 10.4+
- Apache with `mod_rewrite` enabled (or Nginx)

## Setup Instructions

### 1. Clone / Extract
```bash
# Place the form-builder/ folder in your web root
# e.g. /var/www/html/form-builder/  or  htdocs/form-builder/
```

### 2. Configure Database
Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');     // your DB host
define('DB_USER', 'root');          // your DB username
define('DB_PASS', '');              // your DB password
define('DB_NAME', 'form_builder');  // database name (auto-created)
```

Also update `JWT_SECRET` with a strong random string:
```php
define('JWT_SECRET', 'change-this-to-a-random-secret-key');
```

Update `BASE_URL` to match your server:
```php
define('BASE_URL', 'http://localhost/form-builder');
```

### 3. Run Migration
```bash
php migrations/migrate.php
```

This creates all tables and seeds a default admin user.

**Default credentials:**
- Username: `admin`
- Password: `Admin@123`

> ⚠️ Change the password after first login!

### 4. Apache Configuration
Ensure `mod_rewrite` is enabled and `AllowOverride All` is set in your Apache config:
```apache
<Directory /var/www/html/form-builder>
    AllowOverride All
</Directory>
```

### 5. File Uploads (optional)
Create the uploads directory and set permissions:
```bash
mkdir uploads
chmod 755 uploads
```

### 6. Access the App
- **Admin Panel**: `http://localhost/form-builder/`
- **Public Form**: `http://localhost/form-builder/public/form.php?id={uuid}`

---

## API Reference

All API endpoints are under `/api/`. Protected endpoints require:
```
Authorization: Bearer <token>
```

### Auth
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/login` | Login → returns JWT token |
| GET  | `/api/auth/me` | Get current admin info |

### Forms
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET    | `/api/forms` | List all forms |
| POST   | `/api/forms` | Create form |
| GET    | `/api/forms/{uuid}` | Get form with fields |
| PUT    | `/api/forms/{uuid}` | Update form |
| DELETE | `/api/forms/{uuid}` | Delete form |

### Fields
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET    | `/api/forms/{uuid}/fields` | List fields |
| POST   | `/api/forms/{uuid}/fields` | Add field |
| PUT    | `/api/forms/{uuid}/fields/{id}` | Update field |
| DELETE | `/api/forms/{uuid}/fields/{id}` | Delete field |
| PUT    | `/api/forms/{uuid}/fields/reorder` | Reorder fields |

### Submissions
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET    | `/api/forms/{uuid}/submissions` | List submissions (auth) |
| POST   | `/api/submit/{uuid}` | Submit form (public) |
| GET    | `/api/forms/{uuid}/export` | Export CSV (auth) |

### Public
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET    | `/api/public/{uuid}` | Get public form data |

---

## Database Schema

```
admins            - id, username, email, password, created_at
forms             - id, uuid, name, description, is_active, created_by, created_at
fields            - id, form_id, field_type, label, placeholder, is_required, sort_order, options (JSON), validation_rules (JSON)
submissions       - id, form_id, ip_address, user_agent, submitted_at
submission_values - id, submission_id, field_id, value
migrations        - id, migration, ran_at
```

## Security
- All DB queries use PDO prepared statements
- Passwords hashed with Argon2id
- JWT HS256 for API auth
- HTML output escaped with `htmlspecialchars`
- File upload MIME-type validation
- CSRF-ready structure
- Directory traversal prevention via `.htaccess`

## Folder Structure
```
form-builder/
├── api/
│   ├── index.php              # API router
│   └── controllers/
│       ├── AuthController.php
│       ├── FormController.php
│       ├── FieldController.php
│       └── SubmissionController.php
├── assets/
│   ├── css/app.css
│   └── js/app.js
├── config/
│   └── database.php
├── includes/
│   ├── JWT.php
│   └── Security.php
├── migrations/
│   ├── 001_initial_schema.sql
│   └── migrate.php
├── public/
│   └── form.php               # Public form renderer
├── uploads/                   # File uploads (create manually)
├── index.php                  # Admin SPA
├── .htaccess
└── README.md
```# Custom-form-builder
