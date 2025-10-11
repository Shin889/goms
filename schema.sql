CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  email VARCHAR(100),
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','counselor','adviser','student','guardian') NOT NULL,
  full_name VARCHAR(100),
  phone VARCHAR(20),
  is_active BOOLEAN DEFAULT FALSE,
  approved_by INT DEFAULT NULL,
  approved_at DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  action VARCHAR(255),
  target_table VARCHAR(100),
  target_id INT NULL,
  details TEXT,
  ip_address VARCHAR(50),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  student_id VARCHAR(50) UNIQUE,
  first_name VARCHAR(50),
  middle_name VARCHAR(50),
  last_name VARCHAR(50),
  grade_level VARCHAR(20),
  section VARCHAR(50),
  contact_number VARCHAR(20),
  profile_photo VARCHAR(255) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE complaints (
  id INT AUTO_INCREMENT PRIMARY KEY,
  complaint_code VARCHAR(50) UNIQUE,
  student_id INT,
  created_by_user_id INT,
  content TEXT,
  attachments TEXT,
  status ENUM('new','under_review','referred','closed') DEFAULT 'new',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id),
  FOREIGN KEY (created_by_user_id) REFERENCES users(id)
);

CREATE TABLE appointments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  appointment_code VARCHAR(50) UNIQUE,
  requested_by_user_id INT,
  student_id INT,
  counselor_id INT NULL,
  start_time DATETIME,
  end_time DATETIME,
  mode ENUM('in-person','online','phone') DEFAULT 'in-person',
  status ENUM('requested','confirmed','rescheduled','cancelled','completed') DEFAULT 'requested',
  notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  FOREIGN KEY (requested_by_user_id) REFERENCES users(id),
  FOREIGN KEY (student_id) REFERENCES students(id)
);

CREATE TABLE referrals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  complaint_id INT NOT NULL,
  adviser_id INT NOT NULL,
  referral_reason TEXT,
  priority ENUM('low','medium','high') DEFAULT 'medium',
  status ENUM('open','in_progress','resolved','closed') DEFAULT 'open',
  notes TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
  FOREIGN KEY (adviser_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  appointment_id INT NULL,
  counselor_id INT NOT NULL,
  student_id INT NOT NULL,
  start_time DATETIME,
  end_time DATETIME,
  location VARCHAR(100),
  notes_draft TEXT,
  status ENUM('ongoing','completed') DEFAULT 'ongoing',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  FOREIGN KEY (appointment_id) REFERENCES appointments(id),
  FOREIGN KEY (counselor_id) REFERENCES users(id),
  FOREIGN KEY (student_id) REFERENCES students(id)
);

CREATE TABLE reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_id INT NOT NULL,
  counselor_id INT NOT NULL,
  title VARCHAR(255),
  summary TEXT,
  content TEXT,
  submission_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  locked BOOLEAN DEFAULT TRUE,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (session_id) REFERENCES sessions(id),
  FOREIGN KEY (counselor_id) REFERENCES users(id)
);

CREATE TABLE guardians (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  name VARCHAR(100),
  phone VARCHAR(20) NOT NULL,
  email VARCHAR(100),
  relationship VARCHAR(50),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE student_guardians (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  guardian_id INT NOT NULL,
  primary_guardian BOOLEAN DEFAULT FALSE,
  linked_by INT DEFAULT NULL,
  linked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (student_id, guardian_id),
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (guardian_id) REFERENCES guardians(id) ON DELETE CASCADE
);

CREATE TABLE notifications_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  recipient_number VARCHAR(20),
  message TEXT,
  status ENUM('pending','sent','failed') DEFAULT 'pending',
  response TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE advisers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  subject VARCHAR(100) DEFAULT NULL,
  phone VARCHAR(20) DEFAULT NULL,
  section VARCHAR(50) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

