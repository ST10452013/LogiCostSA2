-- =====================================================================
-- LogiCost SA - MySQL database for the PHP website
-- Database: logiscost_sa2 (matches config/database.php)
--
-- Import in phpMyAdmin (Import tab) or from the command line:
--   mysql -u root -p < database/logiscost_sa2.sql
--
-- Sample login for every seeded user:
--   email:    owner@logicost.co.za   (or any email in the users table)
--   password: LogiCost@2026
-- =====================================================================

DROP DATABASE IF EXISTS logiscost_sa2;
CREATE DATABASE logiscost_sa2
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE logiscost_sa2;

-- ---------------------------------------------------------------------
-- Businesses registered on LogiCost
-- ---------------------------------------------------------------------
CREATE TABLE companies (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_name   VARCHAR(160) NOT NULL,
    business_type   VARCHAR(40)  NOT NULL DEFAULT 'manufacturer',
    email           VARCHAR(190) NOT NULL,
    phone           VARCHAR(30)  NULL,
    province        VARCHAR(60)  NULL,
    city            VARCHAR(100) NULL,
    status          ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_companies_province (province)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- People who log in; passwords are stored as bcrypt hashes
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    full_name       VARCHAR(160) NOT NULL,
    email           VARCHAR(190) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    role            ENUM('owner', 'manager', 'driver', 'staff') NOT NULL DEFAULT 'owner',
    status          ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    last_login_at   DATETIME     NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_company (company_id),
    CONSTRAINT fk_users_company FOREIGN KEY (company_id)
        REFERENCES companies (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Stock held by each business
-- ---------------------------------------------------------------------
CREATE TABLE inventory_items (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id          INT UNSIGNED NOT NULL,
    sku                 VARCHAR(60)  NOT NULL,
    product_name        VARCHAR(160) NOT NULL,
    category            VARCHAR(80)  NULL,
    quantity            INT UNSIGNED NOT NULL DEFAULT 0,
    low_stock_threshold INT UNSIGNED NOT NULL DEFAULT 5,
    cost_price          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    selling_price       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_inventory_company_sku (company_id, sku),
    CONSTRAINT fk_inventory_company FOREIGN KEY (company_id)
        REFERENCES companies (id) ON DELETE CASCADE,
    CONSTRAINT chk_inventory_prices CHECK (cost_price >= 0 AND selling_price >= 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Delivery jobs and their status history
-- ---------------------------------------------------------------------
CREATE TABLE delivery_jobs (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id          INT UNSIGNED NOT NULL,
    created_by          INT UNSIGNED NULL,
    reference_code      VARCHAR(30)  NOT NULL,
    pickup_address      VARCHAR(500) NOT NULL,
    dropoff_address     VARCHAR(500) NOT NULL,
    package_description VARCHAR(500) NOT NULL,
    recipient_name      VARCHAR(160) NULL,
    recipient_phone     VARCHAR(30)  NULL,
    scheduled_date      DATE         NULL,
    agreed_cost         DECIMAL(12,2) NULL,
    driver_name         VARCHAR(160) NULL,
    driver_phone        VARCHAR(30)  NULL,
    status              ENUM('pending', 'assigned', 'in_transit', 'delivered', 'cancelled')
                        NOT NULL DEFAULT 'pending',
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_delivery_company_reference (company_id, reference_code),
    KEY idx_delivery_status (company_id, status),
    CONSTRAINT fk_delivery_company FOREIGN KEY (company_id)
        REFERENCES companies (id) ON DELETE CASCADE,
    CONSTRAINT fk_delivery_user FOREIGN KEY (created_by)
        REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE delivery_status_history (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    delivery_id INT UNSIGNED NOT NULL,
    company_id  INT UNSIGNED NOT NULL,
    changed_by  INT UNSIGNED NULL,
    old_status  VARCHAR(20)  NULL,
    new_status  VARCHAR(20)  NOT NULL,
    note        VARCHAR(500) NULL,
    changed_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_history_company (company_id, changed_at),
    CONSTRAINT fk_history_delivery FOREIGN KEY (delivery_id)
        REFERENCES delivery_jobs (id) ON DELETE CASCADE,
    CONSTRAINT fk_history_company FOREIGN KEY (company_id)
        REFERENCES companies (id) ON DELETE CASCADE,
    CONSTRAINT fk_history_user FOREIGN KEY (changed_by)
        REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Marketplace: offers published by businesses and quote requests
-- ---------------------------------------------------------------------
CREATE TABLE marketplace_listings (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id   INT UNSIGNED NOT NULL,
    listing_type ENUM('product', 'service', 'delivery') NOT NULL,
    title        VARCHAR(160) NOT NULL,
    description  TEXT         NULL,
    province     VARCHAR(60)  NULL,
    city         VARCHAR(100) NULL,
    price        DECIMAL(12,2) NULL,
    currency     CHAR(3)      NOT NULL DEFAULT 'ZAR',
    status       ENUM('published', 'paused', 'closed') NOT NULL DEFAULT 'published',
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_listing_search (status, listing_type, province),
    CONSTRAINT fk_listing_company FOREIGN KEY (company_id)
        REFERENCES companies (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE marketplace_quote_requests (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id       INT UNSIGNED NOT NULL,
    buyer_company_id INT UNSIGNED NOT NULL,
    message          TEXT         NOT NULL,
    status           ENUM('pending', 'accepted', 'declined', 'closed') NOT NULL DEFAULT 'pending',
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_quote_buyer (buyer_company_id),
    CONSTRAINT fk_quote_listing FOREIGN KEY (listing_id)
        REFERENCES marketplace_listings (id) ON DELETE CASCADE,
    CONSTRAINT fk_quote_buyer FOREIGN KEY (buyer_company_id)
        REFERENCES companies (id) ON DELETE CASCADE
) ENGINE=InnoDB;


-- =====================================================================
-- Sample data (at least ten records per table)
-- =====================================================================

INSERT INTO companies (id, business_name, business_type, email, phone, province, city) VALUES
(1,  'LogiCost Demo Manufacturing', 'manufacturer',      'info@logicost.co.za',        '0115550101', 'Gauteng',       'Johannesburg'),
(2,  'Highveld Steel Traders',      'supplier',          'sales@highveldsteel.co.za',  '0115550102', 'Gauteng',       'Germiston'),
(3,  'Coastal Freight Logistics',   'transport_company', 'ops@coastalfreight.co.za',   '0315550103', 'KwaZulu-Natal', 'Durban'),
(4,  'Cape Packaging Solutions',    'supplier',          'hello@capepack.co.za',       '0215550104', 'Western Cape',  'Cape Town'),
(5,  'Karoo Haulage',               'transport_company', 'bookings@karoohaul.co.za',   '0535550105', 'Northern Cape', 'Kimberley'),
(6,  'Bay Industrial Coatings',     'manufacturer',      'info@baycoatings.co.za',     '0415550106', 'Eastern Cape',  'Gqeberha'),
(7,  'Limpopo Logistics Hub',       'warehouse',         'storage@limpopohub.co.za',   '0155550107', 'Limpopo',       'Polokwane'),
(8,  'Thabo Mokoena Deliveries',    'driver',            'thabo.m@deliveries.co.za',   '0825550108', 'Free State',    'Bloemfontein'),
(9,  'SafeWork PPE Supplies',       'supplier',          'orders@safework.co.za',      '0125550109', 'Gauteng',       'Pretoria'),
(10, 'Lowveld Fresh Retail',        'retailer',          'manager@lowveldfresh.co.za', '0135550110', 'Mpumalanga',    'Mbombela');

-- Password for all sample users: LogiCost@2026
INSERT INTO users (id, company_id, full_name, email, password_hash, role, last_login_at) VALUES
(1,  1,  'Lethabo Mdhluli',  'owner@logicost.co.za',       '$2y$10$2YKwMoryaQG3mJhA1z0nWuq4r/SD/rKUJ2NTZhySVJtUoVJUzvbtu', 'owner',   '2026-10-28 09:15:00'),
(2,  2,  'Johan Botha',      'johan@highveldsteel.co.za',  '$2y$10$2YKwMoryaQG3mJhA1z0nWuq4r/SD/rKUJ2NTZhySVJtUoVJUzvbtu', 'owner',   '2026-10-27 14:02:00'),
(3,  3,  'Ayesha Patel',     'ayesha@coastalfreight.co.za','$2y$10$2YKwMoryaQG3mJhA1z0nWuq4r/SD/rKUJ2NTZhySVJtUoVJUzvbtu', 'owner',   '2026-10-26 08:40:00'),
(4,  4,  'Pieter van Wyk',   'pieter@capepack.co.za',      '$2y$10$2YKwMoryaQG3mJhA1z0nWuq4r/SD/rKUJ2NTZhySVJtUoVJUzvbtu', 'owner',   NULL),
(5,  5,  'Kagiso Sithole',   'kagiso@karoohaul.co.za',     '$2y$10$2YKwMoryaQG3mJhA1z0nWuq4r/SD/rKUJ2NTZhySVJtUoVJUzvbtu', 'owner',   '2026-10-20 11:30:00'),
(6,  6,  'Zanele Mthembu',   'zanele@baycoatings.co.za',   '$2y$10$2YKwMoryaQG3mJhA1z0nWuq4r/SD/rKUJ2NTZhySVJtUoVJUzvbtu', 'owner',   NULL),
(7,  7,  'Mpho Radebe',      'mpho@limpopohub.co.za',      '$2y$10$2YKwMoryaQG3mJhA1z0nWuq4r/SD/rKUJ2NTZhySVJtUoVJUzvbtu', 'owner',   '2026-10-25 16:45:00'),
(8,  8,  'Thabo Mokoena',    'thabo.m@deliveries.co.za',   '$2y$10$2YKwMoryaQG3mJhA1z0nWuq4r/SD/rKUJ2NTZhySVJtUoVJUzvbtu', 'owner',   '2026-10-28 07:05:00'),
(9,  9,  'Nomsa Zulu',       'nomsa@safework.co.za',       '$2y$10$2YKwMoryaQG3mJhA1z0nWuq4r/SD/rKUJ2NTZhySVJtUoVJUzvbtu', 'owner',   NULL),
(10, 10, 'David Mahlangu',   'david@lowveldfresh.co.za',   '$2y$10$2YKwMoryaQG3mJhA1z0nWuq4r/SD/rKUJ2NTZhySVJtUoVJUzvbtu', 'owner',   '2026-10-22 13:20:00'),
(11, 1,  'Sipho Dlamini',    'sipho@logicost.co.za',       '$2y$10$2YKwMoryaQG3mJhA1z0nWuq4r/SD/rKUJ2NTZhySVJtUoVJUzvbtu', 'driver',  '2026-10-28 06:30:00'),
(12, 1,  'Modise Mashishi',  'modise@logicost.co.za',      '$2y$10$2YKwMoryaQG3mJhA1z0nWuq4r/SD/rKUJ2NTZhySVJtUoVJUzvbtu', 'manager', '2026-10-27 10:10:00');

INSERT INTO inventory_items (company_id, sku, product_name, category, quantity, low_stock_threshold, cost_price, selling_price) VALUES
(1, 'STL-001', 'Mild Steel Sheet 2mm',        'Raw Materials', 120, 20,  450.00,  620.00),
(1, 'STL-002', 'Galvanised Steel Pipe 50mm',  'Raw Materials',  18, 25,  310.00,  425.00),
(1, 'BLT-010', 'Hex Bolt M12 (Box of 100)',   'Fasteners',      64, 15,  185.00,  260.00),
(1, 'WLD-004', 'Welding Rods 3.2mm (5kg)',    'Consumables',     9, 10,  290.00,  395.00),
(1, 'PNT-021', 'Industrial Primer 20L',       'Coatings',       32,  8, 1150.00, 1490.00),
(1, 'PKG-100', 'Cardboard Box Large',         'Packaging',     540,100,   14.50,   22.00),
(1, 'PKG-101', 'Pallet Wrap 500mm',           'Packaging',      45, 12,   98.00,  135.00),
(1, 'ELC-007', '3-Phase Motor 5.5kW',         'Equipment',       4,  2, 7800.00, 9950.00),
(1, 'SAF-030', 'Safety Boots (Size 9)',       'Safety',         26, 10,  420.00,  560.00),
(1, 'SAF-031', 'Hi-Vis Vest Reflective',      'Safety',          3, 15,   45.00,   75.00),
(2, 'HS-SHEET-3', 'Steel Sheet 3mm',          'Steel',         300, 50,  610.00,  790.00),
(9, 'PPE-PACK',   'PPE Starter Pack',         'Safety',         80, 20,  560.00,  780.00);

INSERT INTO delivery_jobs (id, company_id, created_by, reference_code, pickup_address, dropoff_address, package_description, recipient_name, recipient_phone, scheduled_date, agreed_cost, driver_name, driver_phone, status) VALUES
(1,  1, 1, 'LC-1001', 'Midrand Industrial Park, Gauteng',        'Pretoria West Depot, Gauteng',         '12 steel sheets',             'Thabo Nkosi',    '0821234567', '2026-10-20', 1450.00, 'Sipho Dlamini', '0825550111', 'delivered'),
(2,  1, 1, 'LC-1002', 'Germiston Factory, Gauteng',              'Durban Harbour Gate 3, KwaZulu-Natal', '2 pallets of fasteners',      'Ayesha Patel',   '0839876543', '2026-10-27', 6800.00, 'Johan Botha',   '0825550112', 'in_transit'),
(3,  1, 12,'LC-1003', 'Kempton Park Warehouse, Gauteng',         'Polokwane Retail Store, Limpopo',      'Industrial primer x 10',      'Lerato Mokoena', '0724567890', '2026-10-30', 4200.00, 'Neo Molefe',    '0825550113', 'assigned'),
(4,  1, 1, 'LC-1004', 'Paarden Eiland, Cape Town',               'Stellenbosch Workshop, Western Cape',  'Welding consumables',         'Pieter van Wyk', '0612345678', '2026-11-02',  950.00, NULL,            NULL,         'pending'),
(5,  1, 12,'LC-1005', 'Isando Distribution Centre, Gauteng',     'Rustenburg Mine Supply, North West',   'Safety boots x 40',           'Kagiso Sithole', '0798765432', '2026-10-18', 3100.00, 'Sipho Dlamini', '0825550111', 'delivered'),
(6,  1, 1, 'LC-1006', 'Port Elizabeth Plant, Eastern Cape',      'East London Depot, Eastern Cape',      '3-phase motor',               'Zanele Mthembu', '0745678901', '2026-11-04', 2750.00, NULL,            NULL,         'pending'),
(7,  1, 1, 'LC-1007', 'Benoni Workshop, Gauteng',                'Springs Assembly Line, Gauteng',       'Galvanised pipes x 30',       'Ruan Pretorius', '0823456789', '2026-10-21',  880.00, NULL,            NULL,         'cancelled'),
(8,  1, 12,'LC-1008', 'Bloemfontein Hub, Free State',            'Kimberley Branch, Northern Cape',      'Packaging materials',         'Mpho Radebe',    '0712345098', '2026-10-28', 2300.00, 'Johan Botha',   '0825550112', 'in_transit'),
(9,  1, 1, 'LC-1009', 'Pinetown Factory, KwaZulu-Natal',         'Pietermaritzburg Store, KwaZulu-Natal','Pallet wrap x 25',            'Nomsa Zulu',     '0834567123', '2026-10-19', 1200.00, 'Neo Molefe',    '0825550113', 'delivered'),
(10, 1, 1, 'LC-1010', 'Alberton Steel Yard, Gauteng',            'Vereeniging Plant, Gauteng',           'Mild steel sheets x 60',      'David Mahlangu', '0765432109', '2026-10-31', 1900.00, 'Sipho Dlamini', '0825550111', 'assigned'),
(11, 3, 3, 'CF-2001', 'Durban Harbour, KwaZulu-Natal',           'City Deep, Johannesburg',              'Container of imported parts', 'Johan Botha',    '0825550112', '2026-11-01', 9800.00, NULL,            NULL,         'pending');

INSERT INTO delivery_status_history (delivery_id, company_id, changed_by, old_status, new_status, note, changed_at) VALUES
(1,  1, 1,  NULL,         'pending',    'Delivery created.',                       '2026-10-17 09:00:00'),
(1,  1, 1,  'pending',    'assigned',   'Assigned to Sipho Dlamini.',              '2026-10-18 08:00:00'),
(1,  1, 11, 'assigned',   'in_transit', 'Collected from Midrand.',                 '2026-10-20 07:30:00'),
(1,  1, 11, 'in_transit', 'delivered',  'Signed for by Thabo Nkosi.',              '2026-10-20 11:45:00'),
(2,  1, 1,  NULL,         'pending',    'Delivery created.',                       '2026-10-24 10:15:00'),
(2,  1, 1,  'pending',    'assigned',   'Assigned to Johan Botha.',                '2026-10-25 09:00:00'),
(2,  1, 1,  'assigned',   'in_transit', 'Left Germiston on the N3.',               '2026-10-27 06:10:00'),
(5,  1, 12, 'in_transit', 'delivered',  'Delivered to mine stores.',               '2026-10-18 15:20:00'),
(7,  1, 1,  'pending',    'cancelled',  'Customer cancelled the order.',           '2026-10-21 12:00:00'),
(9,  1, 11, 'in_transit', 'delivered',  'Delivered and checked by store manager.', '2026-10-19 14:35:00'),
(10, 1, 12, 'pending',    'assigned',   'Assigned to Sipho Dlamini.',              '2026-10-28 16:00:00');

INSERT INTO marketplace_listings (id, company_id, listing_type, title, description, province, city, price) VALUES
(1,  2,  'product',  'Mild steel sheets - bulk discount',  '2mm and 3mm sheets, minimum order 50.',          'Gauteng',       'Germiston',    590.00),
(2,  3,  'delivery', 'JHB to Durban overnight freight',    '34-ton trucks, daily departures.',               'KwaZulu-Natal', 'Durban',      6500.00),
(3,  4,  'product',  'Custom printed cartons',             'Branded boxes in any size, 5-day turnaround.',   'Western Cape',  'Cape Town',     18.50),
(4,  9,  'product',  'PPE starter packs',                  'Boots, hard hat, vest and gloves per worker.',   'Gauteng',       'Pretoria',     780.00),
(5,  5,  'delivery', 'Northern Cape long-haul trucking',   'Flatbed and tautliner available.',               'Northern Cape', 'Kimberley',   8200.00),
(6,  6,  'service',  'Industrial spray painting',          'On-site coating for machinery and structures.',  'Eastern Cape',  'Gqeberha',    3500.00),
(7,  7,  'service',  'Secure pallet storage',              'R85 per pallet per week, 24-hour security.',     'Limpopo',       'Polokwane',     85.00),
(8,  8,  'delivery', 'Bakkie deliveries in Bloemfontein',  'Same-day local deliveries up to 1 ton.',         'Free State',    'Bloemfontein', 450.00),
(9,  1,  'product',  'Galvanised pipes - surplus stock',   '50mm pipes, 30 available at cost.',              'Gauteng',       'Johannesburg', 310.00),
(10, 10, 'service',  'Cold storage for produce',           'Chilled bays near the N4.',                      'Mpumalanga',    'Mbombela',     120.00);

INSERT INTO marketplace_quote_requests (listing_id, buyer_company_id, message, status) VALUES
(1,  1,  'Please quote for 100 sheets delivered to Midrand.',   'pending'),
(2,  1,  'Need weekly freight for 3 months. Best rate?',        'accepted'),
(3,  1,  'Quote for 2,000 printed cartons please.',              'pending'),
(4,  1,  'PPE packs for 25 new workers.',                        'declined'),
(5,  3,  'Two loads to Upington next week.',                     'pending'),
(6,  2,  'Can you coat a 12m steel frame?',                      'accepted'),
(7,  10, 'Need space for 40 pallets from 1 November.',           'pending'),
(8,  4,  'Truck hire for 5 days in Bloemfontein.',               'closed'),
(9,  6,  'Interested in all 30 pipes. Can you deliver?',         'pending'),
(10, 7,  'Cold storage for 10 pallets of produce.',              'pending');
