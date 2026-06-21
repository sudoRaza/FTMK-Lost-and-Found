# 📦 FTMK Lost & Found

A web-based Lost and Found platform for the Faculty of Information and Communication Technology (FTMK), Universiti Teknikal Malaysia Melaka (UTeM). Built to help students, lecturers, and staff report lost items and claim found ones within the faculty.

---

## 🌐 About

FTMK Lost & Found provides a centralised place for the FTMK community to:
- Post lost or found items with photos and descriptions
- Search and filter listings by category or keyword
- Submit claims for items that belong to them
- Contact post owners directly through the platform

Access is restricted to verified UTeM members only (`@student.utem.edu.my` or `@utem.edu.my` email).

---

## 🛠️ Tech Stack

| Layer | Technology |
|-------|-----------|
| Frontend | HTML, CSS, JavaScript |
| Backend | PHP |
| Database | MySQL |
| Local Server | XAMPP (Apache + MySQL) |

---

## ⚙️ Installation & Setup

### Requirements
- [XAMPP](https://www.apachefriends.org/) (Apache + MySQL)
- A modern web browser

### Steps

**1. Clone the repository**
```bash
git clone https://github.com/your-username/ftmk-lost-and-found.git
```

**2. Move to XAMPP's web root**

Copy the project folder into:
```
C:\xampp\htdocs\FTMK-LOST-AND-FOUND
```

**3. Set up the database**
1. Start Apache and MySQL in XAMPP Control Panel
2. Open [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
3. Create a new database named `ftmk_lostfound`
4. Click the **Import** tab and upload `database.sql`

**4. Configure the app**

Open `config.php` and make sure this is set:
```php
define('USE_MOCK_DB', false);
```

Also confirm your database credentials match:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'ftmk_lostfound');
define('DB_USER', 'root');
define('DB_PASS', '');
```

**5. Run the app**

Open your browser and go to:
```
http://localhost/ftmk_v2
```

---

## 👤 How to Use

1. **Register** with your UTeM matric number and UTeM email
2. **Log in** to access all features
3. **Browse** lost and found listings on the home page
4. **Post** a lost or found item using the `+ New Post` button
5. **Submit a claim** if you find your item listed
6. **Check your inbox** for incoming claims and messages

---

## 📁 Project Structure

```
ftmk_v2/
├── api/
│   ├── auth.php        # Login, signup, session
│   ├── posts.php       # Create, list, delete posts
│   ├── claims.php      # Submit and manage claims
│   └── contacts.php    # Send and receive messages
├── config.php          # Database configuration
├── database.sql        # Database schema
├── mock_db.php         # Mock database (development only)
├── app.js              # Shared JS functions and nav
├── styles.css          # Global styles
├── index.html          # Home page
├── login.html          # Login page
├── signup.html         # Sign up page
├── forgot-password.html
├── newpost.html        # Create new post
├── myposts.html        # View your posts
└── inbox.html          # Claims and messages inbox
```

---

## 👥 Team

| Name | Role |
|------|------|
| Raza | Project Lead — Core system, app.js, index, config, styles |
| Ajim | Authentication — login, signup, auth.php, styles|
| Danish | Database & Inbox — database.sql, inbox, contacts.php |
| Rafiq | Posts — posts.php, newpost, myposts |
| Demirel | Claims — claims.php, forgot password, styles|

---

## 📌 Notes

- This system is designed for **local use via XAMPP** and is not deployed to a live server
- Only UTeM email addresses are accepted during registration
- Passwords are securely hashed using PHP's `password_hash()` (bcrypt)

---

*Developed as a group project for SYSTEM DEVELOPMENT WORKSHOP (DITU3934) FTMK, UTeM.*