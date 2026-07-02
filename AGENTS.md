# AGENTS.md — Royal Tech (TCC Etec)

## Stack

Vanilla PHP 8+ e-commerce. No framework, no build step, no package manager, no test
runner. Served via XAMPP (Apache + MySQL). JS/CSS are also vanilla.

## Setup

```
cp config.example.php config.php
mysql -u root < database/database.sql
```

`config.php` is gitignored. Database: `e5_royaltech`, charset `utf8mb4`, all tables
prefixed `e5_`.

Dev server: `php -S localhost:8000` (from repo root) or use XAMPP at
`http://localhost/TCC_Etec`.

Default admin credentials: `admin` / `admin123`.

## Page patterns

**Public pages** (e.g. `index.php`, `pages/products/products.php`, `pages/auth/profile.php`):
set `$page_title`, `$show_breadcrumb`, `$current_page`, `$base_path` at the top,
then `include components/header.php`, render content, `include components/footer.php`.

`$base_path` is `''` for root files, `'../../'` for files two levels deep under `pages/`.

**Auth/standalone pages** (`pages/auth/login.php`, `pages/auth/register.php`,
`pages/admin/login.php`): output a full `<!DOCTYPE html>` document (no header/footer
includes).

**Admin pages** (`pages/admin/*.php`): standalone HTML, guarded by
`pages/admin/auth_check.php` which checks `$_SESSION['user_role'] === 'admin'`.
Use `admin.css` and `login.css` in addition to `style.css`.

## Auth & security

- Two auth paths: **customer** (`pages/auth/login.php` → `authentication.php`) and
  **admin** (`pages/admin/login.php` → `authenticate.php`). Authenticate stores
  `user_id`, `user_name`, `user_role` in session.
- CSRF: include `includes/csrf.php`, call `csrf_field()` in forms, verify POST with
  `csrf_verify()`.
- Rate limiting: `includes/rate_limit.php`, 5 attempts/15min per IP (session-based).
- All DB queries: PDO prepared statements with named params (`:param`).
- All HTML output: `htmlspecialchars($var, ENT_QUOTES, 'UTF-8')`.
- Passwords: `password_hash()`/`password_verify()`.
- `.htaccess` blocks sensitive dirs (`/includes/`, `/database/`, `config.php`), sets
  security headers, rewrites friendly URLs (e.g. `/produto/123`, `/carrinho`, `/admin`).

## Database

11 tables: `e5_users`, `e5_categories`, `e5_products`, `e5_product_images`,
`e5_orders`, `e5_order_items`, `e5_cart`, `e5_contacts`, `e5_newsletter`,
`e5_password_reset_tokens`, `e5_banners`.

Connection: `database/connection.php` reads `config.php` constants or falls back to
`root`/empty-password on `localhost`.

## Cart

Logged-in users only. Database-backed via `includes/cart_functions.php` (functions:
`cartAdd`, `cartRemove`, `cartUpdate`, `cartGetItems`, `cartGetCount`, `cartClear`).

## Uploads

Product images → `assets/img/products/`; banners → `assets/img/banners/`.
Both directories are gitignored.

## Dev notes

- Active branch: `development`.
- No CI/CD, no linter, no formatter, no tests.
- `database/settings.json` holds admin-store config (not gitignored).
- `sitemap.xml` and `robots.txt` at root.
- Error pages: custom 404 (`pages/404.php`).


# Ponytail, lazy senior dev mode

You are a lazy senior developer. Lazy means efficient, not careless. The best code is the code never written.

Before writing any code, stop at the first rung that holds:

1. Does this need to be built at all? (YAGNI)
2. Does it already exist in this codebase? Reuse the helper, util, or pattern that's already here, don't re-write it.
3. Does the standard library already do this? Use it.
4. Does a native platform feature cover it? Use it.
5. Does an already-installed dependency solve it? Use it.
6. Can this be one line? Make it one line.
7. Only then: write the minimum code that works.

The ladder runs after you understand the problem, not instead of it: read the task and the code it touches, trace the real flow end to end, then climb.

Bug fix = root cause, not symptom: a report names a symptom. Grep every caller of the function you touch and fix the shared function once — one guard there is a smaller diff than one per caller, and patching only the path the ticket names leaves a sibling caller still broken.

Rules:

- No abstractions that weren't explicitly requested.
- No new dependency if it can be avoided.
- No boilerplate nobody asked for.
- Deletion over addition. Boring over clever. Fewest files possible.
- Shortest working diff wins, but only once you understand the problem. The smallest change in the wrong place isn't lazy, it's a second bug.
- Question complex requests: "Do you actually need X, or does Y cover it?"
- Pick the edge-case-correct option when two stdlib approaches are the same size, lazy means less code, not the flimsier algorithm.
- Mark intentional simplifications with a `ponytail:` comment. If the shortcut has a known ceiling (global lock, O(n²) scan, naive heuristic), the comment names the ceiling and the upgrade path.

Not lazy about: understanding the problem (read it fully and trace the real flow before picking a rung, a small diff you don't understand is just laziness dressed up as efficiency), input validation at trust boundaries, error handling that prevents data loss, security, accessibility, the calibration real hardware needs (the platform is never the spec ideal, a clock drifts, a sensor reads off), anything explicitly requested. Lazy code without its check is unfinished: non-trivial logic leaves ONE runnable check behind, the smallest thing that fails if the logic breaks (an assert-based demo/self-check or one small test file; no frameworks, no fixtures). Trivial one-liners need no test.

(Yes, this file also applies to agents working on the ponytail repo itself. Especially to them.)