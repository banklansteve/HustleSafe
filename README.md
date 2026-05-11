# HustleSafe

Nigerian side-hustle marketplace with escrow (Stage 1 foundation).

## Stack

- **Backend:** Laravel 13, PHP 8.2+
- **Frontend:** Vue 3, Inertia.js, Tailwind CSS (via Laravel Breeze)
- **Real-time (planned):** Laravel Reverb
- **Media / storage (planned):** Cloudinary, S3-compatible object storage

## Requirements

- PHP 8.2+ with extensions: `pdo_sqlite` or `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`
- Composer 2.x
- Node.js 20+ and npm

## Setup

```bash
cd hustlesafe
cp .env.example .env
php artisan key:generate

# Database (SQLite by default — ensure pdo_sqlite is enabled for CLI)
touch database/database.sqlite   # Unix / Git Bash
# On Windows PowerShell: New-Item database/database.sqlite -ItemType File -Force

php artisan migrate

composer install
npm install
npm run dev
```

In another terminal:

```bash
php artisan serve
```

Visit `http://127.0.0.1:8000`.

## Git

This repository was initialized by Composer; run `git init` if your copy has no `.git` history yet, then create your first commit.

## Next steps

- Configure `.env` for MySQL/PostgreSQL if not using SQLite.
- Install and configure **Laravel Reverb**, **Cloudinary**, and **S3-compatible** disks per deployment docs.
- Implement escrow flows, roles (client / freelancer / admin), and tests.
