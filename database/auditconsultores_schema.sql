-- AuditConsultores.cl · Estructura base MySQL 8+
-- Ejecutar completo en phpMyAdmin sobre una BD vacía.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS activity_log;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS response_answers;
DROP TABLE IF EXISTS responses;
DROP TABLE IF EXISTS invitation_tokens;
DROP TABLE IF EXISTS participants;
DROP TABLE IF EXISTS questions;
DROP TABLE IF EXISTS survey_sections;
DROP TABLE IF EXISTS survey_forms;
DROP TABLE IF EXISTS surveys;
DROP TABLE IF EXISTS projects;
DROP TABLE IF EXISTS institution_contacts;
DROP TABLE IF EXISTS institutions;
DROP TABLE IF EXISTS admin_users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE admin_users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(190) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE institutions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NULL UNIQUE,
  name VARCHAR(220) NOT NULL,
  rbd VARCHAR(30) NULL,
  dependency VARCHAR(80) NULL,
  region VARCHAR(120) NULL,
  commune VARCHAR(120) NULL,
  address_line VARCHAR(220) NULL,
  phone VARCHAR(40) NULL,
  email VARCHAR(190) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_by_admin_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_institution_created_by_admin
    FOREIGN KEY (created_by_admin_id) REFERENCES admin_users(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE institution_contacts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  institution_id BIGINT UNSIGNED NOT NULL,
  full_name VARCHAR(190) NOT NULL,
  role_title VARCHAR(120) NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(40) NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_contact_institution
    FOREIGN KEY (institution_id) REFERENCES institutions(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE projects (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  institution_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(220) NOT NULL,
  description TEXT NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  status ENUM('draft','active','closed','archived') NOT NULL DEFAULT 'draft',
  created_by_admin_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_project_institution
    FOREIGN KEY (institution_id) REFERENCES institutions(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_project_created_by_admin
    FOREIGN KEY (created_by_admin_id) REFERENCES admin_users(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE surveys (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(220) NOT NULL,
  objective TEXT NULL,
  status ENUM('draft','active','closed') NOT NULL DEFAULT 'draft',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_survey_project
    FOREIGN KEY (project_id) REFERENCES projects(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE survey_forms (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  survey_id BIGINT UNSIGNED NOT NULL,
  estate ENUM('Directivos','Docentes','Apoderados','Paradocentes') NOT NULL,
  version_label VARCHAR(60) NOT NULL DEFAULT 'v1',
  status ENUM('draft','published','closed') NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  closed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_form_unique_estate_version (survey_id, estate, version_label),
  CONSTRAINT fk_form_survey
    FOREIGN KEY (survey_id) REFERENCES surveys(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE survey_sections (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  survey_form_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(220) NOT NULL,
  section_order INT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_section_form
    FOREIGN KEY (survey_form_id) REFERENCES survey_forms(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE questions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  survey_section_id BIGINT UNSIGNED NOT NULL,
  question_text TEXT NOT NULL,
  question_type ENUM('likert_1_5') NOT NULL DEFAULT 'likert_1_5',
  is_required TINYINT(1) NOT NULL DEFAULT 1,
  question_order INT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_question_section
    FOREIGN KEY (survey_section_id) REFERENCES survey_sections(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE participants (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  institution_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NOT NULL,
  estate ENUM('Directivos','Docentes','Apoderados','Paradocentes') NOT NULL,
  full_name VARCHAR(190) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(40) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  responded_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_participant_project_email (project_id, email),
  CONSTRAINT fk_participant_institution
    FOREIGN KEY (institution_id) REFERENCES institutions(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_participant_project
    FOREIGN KEY (project_id) REFERENCES projects(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invitation_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  participant_id BIGINT UNSIGNED NOT NULL,
  survey_form_id BIGINT UNSIGNED NOT NULL,
  token VARCHAR(120) NOT NULL UNIQUE,
  sent_at DATETIME NULL,
  expires_at DATETIME NULL,
  used_at DATETIME NULL,
  token_status ENUM('pending','sent','used','expired','revoked') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_token_participant
    FOREIGN KEY (participant_id) REFERENCES participants(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_token_form
    FOREIGN KEY (survey_form_id) REFERENCES survey_forms(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE responses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invitation_token_id BIGINT UNSIGNED NOT NULL,
  participant_id BIGINT UNSIGNED NOT NULL,
  institution_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NOT NULL,
  survey_form_id BIGINT UNSIGNED NOT NULL,
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_response_token
    FOREIGN KEY (invitation_token_id) REFERENCES invitation_tokens(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_response_participant
    FOREIGN KEY (participant_id) REFERENCES participants(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_response_institution
    FOREIGN KEY (institution_id) REFERENCES institutions(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_response_project
    FOREIGN KEY (project_id) REFERENCES projects(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_response_form
    FOREIGN KEY (survey_form_id) REFERENCES survey_forms(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE response_answers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  response_id BIGINT UNSIGNED NOT NULL,
  question_id BIGINT UNSIGNED NOT NULL,
  likert_value TINYINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT ck_likert_range CHECK (likert_value BETWEEN 1 AND 5),
  UNIQUE KEY uq_response_question (response_id, question_id),
  CONSTRAINT fk_answer_response
    FOREIGN KEY (response_id) REFERENCES responses(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_answer_question
    FOREIGN KEY (question_id) REFERENCES questions(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  generated_by_admin_id BIGINT UNSIGNED NULL,
  report_type ENUM('html','pdf','xlsx','dashboard_snapshot') NOT NULL DEFAULT 'html',
  title VARCHAR(220) NOT NULL,
  file_path VARCHAR(255) NULL,
  payload_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_report_project
    FOREIGN KEY (project_id) REFERENCES projects(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_report_admin
    FOREIGN KEY (generated_by_admin_id) REFERENCES admin_users(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activity_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_user_id BIGINT UNSIGNED NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  details_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_log_admin
    FOREIGN KEY (admin_user_id) REFERENCES admin_users(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin inicial (cambiar clave luego del primer ingreso)
INSERT INTO admin_users (email, password_hash, full_name, is_active)
VALUES (
  'admin@auditconsultores.cl',
  '$2y$10$KfL6iMtAqi8QXh8uk9MrQOwQ11LkR4qMPW2N2A5dWvhVxXx2g2g5i',
  'Administrador Principal',
  1
)
ON DUPLICATE KEY UPDATE email = VALUES(email);
