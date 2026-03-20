Create DATABASE bikerentalbt;

use bikerentalbt;

CREATE TABLE admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50),
    password VARCHAR(255)
);

-- NOTE: Default admin password is 'admin123' with MD5 hash for initial setup
-- After first login, you MUST change the password using the "Change Password" feature
-- to upgrade to bcrypt hashing (PASSWORD_DEFAULT)
INSERT INTO admin (username, password) VALUES ('admin', MD5('admin123'));

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    password VARCHAR(255),
    contact_number VARCHAR(20),
    document VARCHAR(255)
);

CREATE TABLE bikes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand VARCHAR(100),
    price INT,
    image VARCHAR(255),
    status VARCHAR(50) DEFAULT 'available'
);

INSERT INTO bikes (brand, price, image) VALUES
('Yamaha FZ', 2000, 'assets/images/bike1.jpg'),
('Honda Shine', 1500, 'assets/images/bike2.jpg');

CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    bike_id INT NOT NULL,
    date_from DATE,
    date_to DATE,
    status VARCHAR(50) DEFAULT 'pending',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (bike_id) REFERENCES bikes(id) ON DELETE RESTRICT
);
