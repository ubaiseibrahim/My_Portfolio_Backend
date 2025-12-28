-- Database Create Query (if needed)
-- CREATE DATABASE IF NOT EXISTS ubaise_ibrahim;
-- USE ubaise_ibrahim;

-- Projects Table
CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_name VARCHAR(255) NOT NULL,
    display_order INT DEFAULT 0,
    project_url VARCHAR(255),
    short_description TEXT,
    description LONGTEXT,
    technologies VARCHAR(255), -- Store as comma separated or JSON string
    featured_image VARCHAR(255),
    gallery_images TEXT, -- Store as JSON array of paths
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    start_date DATE,
    end_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Contact Messages Table
CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Users Table for Authentication
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    profile_picture VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sample Data for Projects
INSERT INTO projects (project_name, display_order, project_url, short_description, description, technologies, featured_image, gallery_images, status) VALUES 
('Luxury Portfolio', 1, 'https://example.com/project1', 'A premium portfolio design.', 'Full description of the luxury portfolio project with advanced animations.', 'React, CSS3, Framer Motion', 'uploads/projects/featured/sample1.jpg', '["uploads/projects/gallery/sample1_1.jpg", "uploads/projects/gallery/sample1_2.jpg"]', 'Active'),
('E-commerce Dashboard', 2, 'https://example.com/project2', 'Modern admin panel.', 'An elite dashboard for managing high-end retail products and analytics.', 'Vue.js, Tailwind, Node.js', 'uploads/projects/featured/sample2.jpg', '[]', 'Active');

-- Sample Data for Contact Messages
INSERT INTO contact_messages (name, email, message) VALUES 
('John Doe', 'john@example.com', 'I love your work! Can we collaborate?'),
('Site Visitor', 'visitor@example.com', 'Excellent design aesthetics on the homepage.');

-- Sample Data for Users
-- Note: Password for ubaiseibrahim is 'ubasie@eache' (hashed)
INSERT INTO users (full_name, username, email, password, profile_picture) VALUES 
('Ubaise Ibrahim', 'ubaiseibrahim', 'ubaise@example.com', '$2y$10$JpKf58xsaQam9/XypPaqcuwx2JMyYYfE4UK7iYrasQOE8TtIDRWfC', 'uploads/profiles/default.jpg');




