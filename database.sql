CREATE DATABASE IF NOT EXISTS ftmk_lostfound
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE ftmk_lostfound;


-- TABLE: users
CREATE TABLE IF NOT EXISTS users (
  user_id      VARCHAR(20)  NOT NULL PRIMARY KEY,
  name         VARCHAR(120) NOT NULL,
  email        VARCHAR(150) NOT NULL UNIQUE,
  password     VARCHAR(255) NOT NULL,
  role         ENUM('Student','Lecturer','Staff') NOT NULL DEFAULT 'Student',
  initials     VARCHAR(4)   NOT NULL,
  avatar_color VARCHAR(20)  NOT NULL DEFAULT 'blue',
  created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- TABLE: item_post
 
CREATE TABLE IF NOT EXISTS item_post (
  post_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       VARCHAR(20)  NOT NULL,
  title         VARCHAR(200) NOT NULL,
  description   TEXT         NOT NULL,
  category      VARCHAR(50)  NOT NULL,
  type          ENUM('lost','found') NOT NULL,
  status        ENUM('active','resolved') NOT NULL DEFAULT 'active',
  location      VARCHAR(200) NOT NULL,
  date_posted   DATE         NOT NULL,
  poster_name   VARCHAR(120) NOT NULL,
  poster_role   VARCHAR(30)  NOT NULL DEFAULT 'Student',
  contact_email VARCHAR(150) NOT NULL,
  avatar        VARCHAR(4)   NOT NULL,
  avatar_color  VARCHAR(20)  NOT NULL DEFAULT 'blue',
  created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_type   (type),
  INDEX idx_status (status),
  INDEX idx_date   (date_posted)
) ENGINE=InnoDB;


-- TABLE: item_image
 
CREATE TABLE IF NOT EXISTS item_image (
  image_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id   INT UNSIGNED NOT NULL,
  image_url MEDIUMTEXT   NOT NULL,
  FOREIGN KEY (post_id) REFERENCES item_post(post_id) ON DELETE CASCADE,
  INDEX idx_post (post_id)
) ENGINE=InnoDB;


-- TABLE: claim_request

CREATE TABLE IF NOT EXISTS claim_request (
  claim_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id      INT UNSIGNED NOT NULL,
  user_id      VARCHAR(20)  NOT NULL,
  message      TEXT         NOT NULL,
  claim_status ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  date_request DATETIME     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id) REFERENCES item_post(post_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(user_id)     ON DELETE CASCADE,
  UNIQUE KEY uq_user_post (user_id, post_id),
  INDEX idx_post_id  (post_id),
  INDEX idx_user_id  (user_id),
  INDEX idx_status   (claim_status)
) ENGINE=InnoDB;

 
-- TABLE: contact

CREATE TABLE IF NOT EXISTS contact (
  contact_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        VARCHAR(20)  NOT NULL,
  post_id        INT UNSIGNED NOT NULL,
  contact_info   VARCHAR(200) NOT NULL DEFAULT '',
  message_note   TEXT         NOT NULL,
  date_contacted DATETIME     DEFAULT CURRENT_TIMESTAMP,
  is_read        TINYINT(1)   NOT NULL DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users(user_id)     ON DELETE CASCADE,
  FOREIGN KEY (post_id) REFERENCES item_post(post_id) ON DELETE CASCADE,
  INDEX idx_post   (post_id),
  INDEX idx_sender (user_id)
) ENGINE=InnoDB;

 
-- VIEWS

CREATE OR REPLACE VIEW v_claims_with_details AS
SELECT
  cr.claim_id, cr.post_id, cr.user_id, cr.message,
  cr.claim_status, cr.date_request,
  ip.title   AS post_title, ip.type AS post_type, ip.user_id AS post_owner,
  u.name     AS claimant_name, u.email AS claimant_email
FROM claim_request cr
JOIN item_post ip ON cr.post_id = ip.post_id
JOIN users     u  ON cr.user_id = u.user_id;

CREATE OR REPLACE VIEW v_contacts_with_details AS
SELECT
  c.contact_id, c.user_id, c.post_id, c.contact_info,
  c.message_note, c.date_contacted, c.is_read,
  ip.title   AS post_title, ip.user_id AS post_owner,
  u.name     AS sender_name, u.email   AS sender_email
FROM contact c
JOIN item_post ip ON c.post_id = ip.post_id
JOIN users     u  ON c.user_id = u.user_id;
