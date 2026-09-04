# AniStream - Anime & Movies Streaming Platform

A lightweight, standalone PHP, MySQL, HTML5, CSS3, and JavaScript streaming web application and content management system.

---

## 🚀 Features

- **Frontend Streaming Platform**:
  - Browse Anime Series, Movies, and Episodes.
  - Multi-server video player with resolution/server selection.
  - Customizable homepage layout with modular sections (Trending, Latest, Popular, Blog).
  - Search, genre filtering, and sorting (Newest, Oldest, Most Viewed).
  - User Authentication (Login, Sign-up, Watchlist, Favorites, and Continue Watching).
  - Multilingual & RTL support (English & Arabic with in-browser language switcher).

- **Full-Featured Admin Panel (`/admin/`)**:
  - **Dashboard**: Visitor analytics, media statistics, quick counters.
  - **Series & Movies Management**: Full metadata support (Aired dates, seasons, studio, country, scores, age rating, quality badges).
  - **MAL (MyAnimeList) & TMDB Auto-Fetch**: Fetch anime/movie synopsis, posters, release dates, and scores automatically using TMDB or MAL/Jikan API.
  - **Seasons & Episodes**: Inline video source management with multi-server playback options.
  - **Bulk Episode Adder**: Quickly add sequential episodes in batches with server URLs and resolutions.
  - **Homepage & Layout Settings**: Toggle sidebar, hero slider, synopses, and customize media card fields (genres, type, duration, views, score) independently for homepage vs other pages.
  - **Translations Manager**: Manage translation strings directly from the admin interface.
  - **Database Backups**: Download Content-Only or Full-Site SQL dumps anytime.

---

## 🛠 Tech Stack & Requirements

- **Backend**: Pure PHP 7.4+ / PHP 8.0+ (PDO, cURL, JSON extensions enabled).
- **Database**: MySQL 5.7+ / 8.0+ or MariaDB 10.3+.
- **Frontend**: HTML5, CSS3, JavaScript (Bootstrap 4, jQuery, OwlCarousel, FontAwesome, NiceSelect).
- **Web Server**: Apache, Nginx, LiteSpeed, or PHP Built-in Server. Works directly on XAMPP, WAMP, MAMP, Laragon, or cPanel.

---

## 📦 Installation & Setup

### 1. Database Setup
1. Open phpMyAdmin or your MySQL CLI:
   ```bash
   mysql -u root -p
   ```
2. Import the database schema from `database/schema.sql`:
   ```bash
   mysql -u root -p < database/schema.sql
   ```
   *(Or create a database named `anistream` in phpMyAdmin and import `database/schema.sql` under the Import tab).*

### 2. Configure Database Connection
Edit `config/db.php` with your database credentials:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'anistream');
define('DB_USER', 'root');
define('DB_PASS', '');
```
*(Alternatively, set the environment variables `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS`).*

### 3. Run Locally
- **Using PHP Built-in Server**:
  ```bash
  php -S localhost:8000
  ```
  Visit `http://localhost:8000` in your web browser.
- **Using XAMPP/Laragon**:
  Place the folder in your `htdocs` or `www` directory and navigate to `http://localhost/anistream`.

---

## 🔐 Default Admin Account

- **Admin URL**: `http://localhost:8000/admin/`
- **Email**: `admin@anistream.test`
- **Password**: `admin123`

*(Make sure to change the admin password in the Admin Panel after your first login).*

---

## 🌐 Pushing to GitHub

To push this project to your GitHub repository:

```bash
# 1. Initialize git (if not already initialized)
git init

# 2. Stage all project files
git add .

# 3. Commit your changes
git commit -m "feat: complete AniStream anime and movie streaming platform"

# 4. Set default branch to main
git branch -M main

# 5. Connect your remote GitHub repository
git remote add origin https://github.com/YOUR_USERNAME/YOUR_REPOSITORY.git

# 6. Push code to GitHub
git push -u origin main
```

---

## 📂 Project Structure

```
├── admin/                 # Admin panel controllers & views
│   ├── api_fetch.php      # TMDB / MAL metadata auto-importer
│   ├── backup.php         # Database backup & export manager
│   ├── bulk-episodes.php  # Dedicated batch episode creator
│   ├── episodes.php       # Episode management & inline video sources
│   ├── movies.php         # Movies list & CRUD
│   ├── series.php         # Series list & CRUD
│   ├── settings.php       # General, API Keys, Layout, & Media Cards
│   └── translations.php   # Multilingual string dictionary manager
├── config/
│   └── db.php             # PDO database connection configuration
├── database/
│   ├── schema.sql         # Full complete database schema & sample data
│   └── migration.sql      # Upgrade / migration script for existing DBs
├── includes/
│   ├── api_clients.php    # TMDB and MAL API integrations
│   ├── bootstrap.php      # Global session & bootstrapping
│   ├── functions.php      # Helper functions, queries, & renderers
│   ├── header.php         # Global HTML header & navigation
│   ├── footer.php         # Global HTML footer & modals
│   └── i18n.php           # Translation & RTL helper
├── css/                   # Stylesheets (Bootstrap, OwlCarousel, FontAwesome)
├── js/                    # Client JavaScript libraries & scripts
├── index.php              # Homepage with customizable hero slider & sections
├── movies.php             # All movies catalog with sorting
├── series.php             # All series catalog with sorting
├── genrs.php              # Genre directory & filtering
├── search.php             # Live search for titles & episodes
├── tv-details.php         # Series detail page & episode guide
├── movie-details.php      # Movie detail page & metadata
├── watching.php           # Episode video streaming player
├── movie-watching.php     # Movie video streaming player
└── my-list.php            # User Watchlist, Favorites, & Continue Watching
```
