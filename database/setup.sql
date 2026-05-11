-- CapSU Request System Database Setup
-- Run this file once to initialize the database

CREATE DATABASE IF NOT EXISTS capsu_requests CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE capsu_requests;

CREATE TABLE IF NOT EXISTS admins (
  id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(50) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS request_types (
  id INT PRIMARY KEY AUTO_INCREMENT,
  code VARCHAR(20) UNIQUE NOT NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT,
  form_fields JSON,
  is_active TINYINT(1) DEFAULT 1
);

CREATE TABLE IF NOT EXISTS requests (
  id INT PRIMARY KEY AUTO_INCREMENT,
  tracking_number VARCHAR(20) UNIQUE NOT NULL,
  request_type_id INT NOT NULL,
  requester_name VARCHAR(150) NOT NULL,
  requester_firstname VARCHAR(75) DEFAULT NULL,
  requester_middlename VARCHAR(10) DEFAULT NULL,
  requester_lastname VARCHAR(75) DEFAULT NULL,
  requester_sex ENUM('Male','Female') DEFAULT NULL,
  requester_email VARCHAR(150) NOT NULL,
  requester_phone VARCHAR(30),
  requester_department VARCHAR(150),
  requester_position VARCHAR(150),
  purpose TEXT,
  additional_data JSON,
  status ENUM('pending','processing','approved','rejected','completed') DEFAULT 'pending',
  admin_notes TEXT,
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (request_type_id) REFERENCES request_types(id)
);

CREATE TABLE IF NOT EXISTS document_templates (
  id INT PRIMARY KEY AUTO_INCREMENT,
  request_type_id INT NOT NULL UNIQUE,
  template_content LONGTEXT,
  header_html LONGTEXT,
  footer_html TEXT,
  layout_json JSON,
  template_docx_path VARCHAR(255) DEFAULT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (request_type_id) REFERENCES request_types(id)
);

CREATE TABLE IF NOT EXISTS signatories (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  title VARCHAR(200) NOT NULL,
  role VARCHAR(100),
  is_active TINYINT(1) DEFAULT 1,
  sort_order INT DEFAULT 0
);

CREATE TABLE IF NOT EXISTS settings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  setting_key VARCHAR(100) UNIQUE NOT NULL,
  setting_value TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS admin_password_resets (
  id INT PRIMARY KEY AUTO_INCREMENT,
  admin_id INT NOT NULL,
  otp VARCHAR(6) NOT NULL,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS email_logs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  request_id INT,
  recipient_email VARCHAR(150),
  subject VARCHAR(255),
  sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  status ENUM('sent','failed') DEFAULT 'sent',
  FOREIGN KEY (request_id) REFERENCES requests(id)
);

-- Default admin: username=admin, password=admin123
INSERT INTO admins (username, password, name, email) VALUES
('admin', '$2y$10$os.EM80T38Wiru/B8xeucuCz.9ZlryeUdWYdMPvd6JuTAe5HIDv9G', 'System Administrator', 'admin@capsu.edu.ph')
ON DUPLICATE KEY UPDATE password=VALUES(password);

-- Request types
INSERT INTO request_types (code, name, description, form_fields) VALUES
('COE', 'Certificate of Employment', 'Certifies that an individual is currently employed at CapSU.',
  '[{"name":"period_of_service","label":"Period of Service","type":"text","placeholder":"e.g. January 1, 2020 to present","required":true},{"name":"employment_status","label":"Employment Status","type":"select","options":["Permanent","Temporary","Casual","Contractual","Job Order"],"required":true}]'),
('CNPAC', 'Certificate of No Pending Administrative Case', 'Certifies that the individual has no pending administrative case.',
  '[]'),
('CGS', 'Certificate of Good Standing', 'Certifies that the employee is in good standing at the university.',
  '[]'),
('SR', 'Service Record', 'Official record of employment history and service at CapSU.',
  '[{"name":"inclusive_dates","label":"Inclusive Dates Needed","type":"text","placeholder":"e.g. 2018 to present","required":false}]'),
('CA', 'Certificate of Attendance', 'Certifies attendance to a specific event, training, or seminar.',
  '[{"name":"event_name","label":"Event/Training Name","type":"text","required":true},{"name":"event_date","label":"Event Date","type":"text","required":true}]'),
('CC', 'Certificate of Completion', 'Certifies completion of a program, course, or training.',
  '[{"name":"program_name","label":"Program/Course Name","type":"text","required":true},{"name":"completion_date","label":"Completion Date","type":"text","required":true}]'),
('OTHER', 'Other / Special Request', 'For requests not covered by the above categories.',
  '[{"name":"special_request_details","label":"Describe your request in detail","type":"textarea","required":true}]')
ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), form_fields=VALUES(form_fields);

-- Default settings
INSERT INTO settings (setting_key, setting_value) VALUES
('university_name', 'Capiz State University'),
('university_address', 'Fuentes Drive, Roxas City, Capiz'),
('university_phone', '(036) 620-0367'),
('university_email', 'info@capsu.edu.ph'),
('admin_email', 'admin@capsu.edu.ph'),
('university_logo', ''),
('letterhead_html', '<div style="text-align:center;padding:10px 0;border-bottom:2px solid #1a3a6b;margin-bottom:20px;"><h2 style="color:#1a3a6b;margin:0;font-size:18pt;">CAPIZ STATE UNIVERSITY</h2><p style="margin:2px 0;color:#555;">Fuentes Drive, Roxas City, Capiz</p><p style="margin:2px 0;color:#555;">Tel: (036) 620-0367 | Email: info@capsu.edu.ph</p></div>'),
('footer_html', '<div style="text-align:center;padding:10px 0;border-top:1px solid #ccc;margin-top:20px;font-size:9pt;color:#777;">This document is computer-generated and is valid without signature unless stated otherwise.</div>')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

-- Default signatories
INSERT INTO signatories (name, title, role, is_active, sort_order) VALUES
('DR. MARIA SANTOS', 'University President', 'president', 1, 1),
('ATTY. JUAN DELA CRUZ', 'Vice President for Administration', 'vp_admin', 1, 2),
('MS. ANA REYES', 'Human Resource Management Officer', 'hrmo', 1, 3)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Default document templates
INSERT INTO document_templates (request_type_id, template_content)
SELECT id,
  CASE code
    WHEN 'COE' THEN '<p style="text-indent:40px;">This is to certify that <strong>{{requester_name}}</strong>, {{requester_position}}, of the {{requester_department}} of this University, is currently employed at <strong>Capiz State University</strong> under {{employment_status}} status with a period of service from {{period_of_service}}.</p><p style="text-indent:40px;">This certification is issued upon the request of the above-named employee for whatever legal purpose it may serve.</p>'
    WHEN 'CNPAC' THEN '<p style="text-indent:40px;">This is to certify that <strong>{{requester_name}}</strong>, {{requester_position}}, of the {{requester_department}} of this University, has <strong>NO PENDING ADMINISTRATIVE CASE</strong> filed against him/her in this office as of this date.</p><p style="text-indent:40px;">This certification is issued upon the request of the above-named employee for whatever legal purpose it may serve.</p>'
    WHEN 'CGS' THEN '<p style="text-indent:40px;">This is to certify that <strong>{{requester_name}}</strong>, {{requester_position}}, of the {{requester_department}} of this University, is a <strong>PERSON OF GOOD STANDING</strong> and has been performing his/her duties and responsibilities satisfactorily.</p><p style="text-indent:40px;">This certification is issued upon the request of the above-named employee for whatever legal purpose it may serve.</p>'
    WHEN 'SR' THEN '<p style="text-indent:40px;">This is the <strong>SERVICE RECORD</strong> of <strong>{{requester_name}}</strong> who has been employed at <strong>Capiz State University</strong> as {{requester_position}} under the {{requester_department}}.</p><p style="text-indent:40px;">Inclusive dates covered: {{inclusive_dates}}</p>'
    WHEN 'CA' THEN '<p style="text-indent:40px;">This is to certify that <strong>{{requester_name}}</strong>, {{requester_position}}, of the {{requester_department}} of this University, attended the <strong>{{event_name}}</strong> held on {{event_date}}.</p><p style="text-indent:40px;">This certification is issued upon the request of the above-named employee for whatever legal purpose it may serve.</p>'
    WHEN 'CC' THEN '<p style="text-indent:40px;">This is to certify that <strong>{{requester_name}}</strong>, {{requester_position}}, of the {{requester_department}} of this University, has successfully completed the <strong>{{program_name}}</strong> on {{completion_date}}.</p><p style="text-indent:40px;">This certification is issued upon the request of the above-named employee for whatever legal purpose it may serve.</p>'
    WHEN 'OTHER' THEN '<p style="text-indent:40px;">This is in response to the special request submitted by <strong>{{requester_name}}</strong>, {{requester_position}}, of the {{requester_department}} of this University.</p><p style="text-indent:40px;">Details: {{special_request_details}}</p><p style="text-indent:40px;">This document is issued upon the request of the above-named employee for whatever legal purpose it may serve.</p>'
  END
FROM request_types
ON DUPLICATE KEY UPDATE template_content=VALUES(template_content);
