CREATE TABLE IF NOT EXISTS people (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(25) NULL,
    address_line1 VARCHAR(255) NULL,
    address_line2 VARCHAR(255) NULL,
    city VARCHAR(100) NULL,
    state VARCHAR(50) NULL,
    postal_code VARCHAR(20) NULL,
    google_place_id VARCHAR(255) NULL,
    latitude DECIMAL(10,6) NULL,
    longitude DECIMAL(10,6) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_people_first_last ON people(first_name, last_name);
