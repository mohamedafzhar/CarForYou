-- =====================================================
-- CARFORYOU DATABASE - ADVANCED SQL FEATURES
-- For SLGTI Database Systems Assignment
-- =====================================================

-- Drop existing advanced objects if they exist
DROP PROCEDURE IF EXISTS create_booking;
DROP PROCEDURE IF EXISTS process_payment;
DROP PROCEDURE IF EXISTS cancel_booking;
DROP PROCEDURE IF EXISTS generate_revenue_report;
DROP PROCEDURE IF EXISTS generate_monthly_report;
DROP PROCEDURE IF EXISTS get_user_booking_history;
DROP FUNCTION IF EXISTS calculate_total_amount;
DROP FUNCTION IF EXISTS calculate_penalty;
DROP FUNCTION IF EXISTS get_days_rented;
DROP TRIGGER IF EXISTS after_payment_insert;
DROP TRIGGER IF EXISTS before_booking_delete;
DROP TRIGGER IF EXISTS after_car_return;

-- =====================================================
-- 1. ADD MISSING FOREIGN KEY (booking → users) - Only if user_id doesn't exist
-- =====================================================
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'carrental' AND TABLE_NAME = 'booking' AND COLUMN_NAME = 'user_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE booking ADD COLUMN user_id INT DEFAULT NULL AFTER user_email', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key if not exists
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = 'carrental' AND TABLE_NAME = 'booking' AND CONSTRAINT_NAME = 'fk_booking_user');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE booking ADD CONSTRAINT fk_booking_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update existing bookings with user_id
UPDATE booking b JOIN users u ON b.user_email = u.email SET b.user_id = u.id WHERE b.user_id IS NULL;

-- =====================================================
-- 2. FUNCTIONS
-- =====================================================

-- Function: Calculate total rental amount
DELIMITER $$
CREATE FUNCTION calculate_total_amount(
    p_from_date DATE,
    p_to_date DATE,
    p_price_per_day INT
)
RETURNS INT
DETERMINISTIC
NO SQL
BEGIN
    DECLARE total_days INT;
    DECLARE total_amount INT;
    
    SET total_days = DATEDIFF(p_to_date, p_from_date);
    IF total_days < 1 THEN
        SET total_days = 1;
    END IF;
    
    SET total_amount = total_days * p_price_per_day;
    
    RETURN total_amount;
END$$
DELIMITER ;

-- Function: Calculate late return penalty
DELIMITER $$
CREATE FUNCTION calculate_penalty(
    p_actual_return DATE,
    p_expected_return DATE,
    p_price_per_day INT
)
RETURNS INT
DETERMINISTIC
NO SQL
BEGIN
    DECLARE late_days INT;
    DECLARE penalty_rate INT DEFAULT 50; -- 50% penalty per day
    DECLARE penalty INT;
    
    IF p_actual_return IS NULL OR p_expected_return IS NULL THEN
        RETURN 0;
    END IF;
    
    SET late_days = DATEDIFF(p_actual_return, p_expected_return);
    
    IF late_days > 0 THEN
        SET penalty = late_days * p_price_per_day * penalty_rate / 100;
        RETURN penalty;
    END IF;
    
    RETURN 0;
END$$
DELIMITER ;

-- Function: Get number of days rented
DELIMITER $$
CREATE FUNCTION get_days_rented(
    p_from_date DATE,
    p_to_date DATE
)
RETURNS INT
DETERMINISTIC
NO SQL
BEGIN
    DECLARE days INT;
    
    SET days = DATEDIFF(p_to_date, p_from_date);
    
    IF days < 1 THEN
        RETURN 1;
    END IF;
    
    RETURN days;
END$$
DELIMITER ;

-- =====================================================
-- 3. STORED PROCEDURES WITH ERROR HANDLING
-- =====================================================

-- Procedure: Create Booking (with transaction & error handling)
DELIMITER $$
CREATE PROCEDURE create_booking(
    IN p_user_id INT,
    IN p_user_email VARCHAR(150),
    IN p_car_id INT,
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_message TEXT
)
BEGIN
    DECLARE v_total_amount INT;
    DECLARE v_price_per_day INT;
    DECLARE v_car_status VARCHAR(20);
    DECLARE v_error_msg VARCHAR(255) DEFAULT '';
    DECLARE v_days INT;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SELECT 'ERROR: An error occurred while processing your booking. Please try again.' AS result;
    END;
    
    DECLARE EXIT HANDLER FOR NOT FOUND
    BEGIN
        ROLLBACK;
        SELECT 'ERROR: Car not found in the system.' AS result;
    END;
    
    START TRANSACTION;
    
    -- Check if car exists and get price
    SELECT price_per_day, status INTO v_price_per_day, v_car_status
    FROM cars WHERE id = p_car_id
    FOR UPDATE;
    
    -- Validate car availability
    IF v_car_status != 'Available' THEN
        ROLLBACK;
        SELECT 'ERROR: Car is not available for booking.' AS result;
    ELSEIF p_from_date < CURDATE() THEN
        ROLLBACK;
        SELECT 'ERROR: Booking date cannot be in the past.' AS result;
    ELSEIF p_to_date < p_from_date THEN
        ROLLBACK;
        SELECT 'ERROR: Return date must be after pickup date.' AS result;
    ELSE
        -- Calculate total using function
        SET v_days = get_days_rented(p_from_date, p_to_date);
        SET v_total_amount = calculate_total_amount(p_from_date, p_to_date, v_price_per_day);
        
        -- Insert booking
        INSERT INTO booking (user_id, user_email, car_id, from_date, to_date, message, status, total_amount)
        VALUES (p_user_id, p_user_email, p_car_id, p_from_date, p_to_date, p_message, 'Pending', v_total_amount);
        
        -- Update car status
        UPDATE cars SET status = 'Booked' WHERE id = p_car_id;
        
        COMMIT;
        
        SELECT 
            'SUCCESS' AS result, 
            LAST_INSERT_ID() AS booking_id, 
            v_total_amount AS total_amount,
            CONCAT('Booking created! Please proceed to payment within 30 minutes.') AS message;
    END IF;
END$$
DELIMITER ;

-- Procedure: Process Payment (with transaction & error handling)
DELIMITER $$
CREATE PROCEDURE process_payment(
    IN p_booking_id INT,
    IN p_card_type VARCHAR(20),
    IN p_card_last4 VARCHAR(4),
    IN p_card_holder VARCHAR(100),
    IN p_amount DECIMAL(10,2),
    OUT p_receipt_no VARCHAR(20)
)
BEGIN
    DECLARE v_booking_status VARCHAR(20);
    DECLARE v_booking_total INT;
    DECLARE v_receipt VARCHAR(20);
    DECLARE v_exists INT DEFAULT 0;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SELECT 'ERROR: Payment processing failed. Please try again.' AS result;
    END;
    
    -- Check if payment already exists
    SELECT COUNT(*) INTO v_exists FROM card_payments WHERE booking_id = p_booking_id;
    IF v_exists > 0 THEN
        SELECT 'ERROR: Payment already processed for this booking.' AS result;
    ELSE
        START TRANSACTION;
        
        -- Get booking info
        SELECT status, total_amount INTO v_booking_status, v_booking_total
        FROM booking WHERE id = p_booking_id
        FOR UPDATE;
        
        IF v_booking_status IS NULL THEN
            ROLLBACK;
            SELECT 'ERROR: Booking not found.' AS result;
        ELSEIF v_booking_status = 'Confirmed' THEN
            ROLLBACK;
            SELECT 'ERROR: Booking already confirmed.' AS result;
        ELSE
            -- Generate receipt number
            SET v_receipt = CONCAT('RCP', UPPER(SUBSTRING(MD5(CONCAT(p_booking_id, NOW())), 1, 8)));
            
            -- Insert payment record
            INSERT INTO card_payments (
                booking_id, user_email, card_type, card_last4, card_holder, 
                expiry_month, expiry_year, amount, payment_date, receipt_no, status
            )
            SELECT 
                p_booking_id, 
                user_email, 
                p_card_type, 
                p_card_last4, 
                p_card_holder,
                '00', '00',
                p_amount,
                NOW(),
                v_receipt,
                'completed'
            FROM booking WHERE id = p_booking_id;
            
            -- Update booking status (will also trigger the AFTER INSERT trigger)
            UPDATE booking 
            SET status = 'Confirmed', 
                payment_status = 'paid', 
                payment_date = NOW() 
            WHERE id = p_booking_id;
            
            COMMIT;
            
            SET p_receipt_no = v_receipt;
            SELECT 'SUCCESS' AS result, v_receipt AS receipt_no, p_amount AS amount;
        END IF;
    END IF;
END$$
DELIMITER ;

-- Procedure: Cancel Booking (with error handling)
DELIMITER $$
CREATE PROCEDURE cancel_booking(
    IN p_booking_id INT,
    IN p_user_id INT
)
BEGIN
    DECLARE v_status VARCHAR(20);
    DECLARE v_car_id INT;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SELECT 'ERROR: Could not cancel booking. Please contact support.' AS result;
    END;
    
    START TRANSACTION;
    
    -- Get booking info
    SELECT status, car_id INTO v_status, v_car_id
    FROM booking 
    WHERE id = p_booking_id AND user_id = p_user_id
    FOR UPDATE;
    
    IF v_status IS NULL THEN
        ROLLBACK;
        SELECT 'ERROR: Booking not found or access denied.' AS result;
    ELSEIF v_status IN ('Cancelled', 'Completed') THEN
        ROLLBACK;
        SELECT CONCAT('ERROR: Cannot cancel a booking that is already ', v_status) AS result;
    ELSE
        -- Update booking status
        UPDATE booking SET status = 'Cancelled' WHERE id = p_booking_id;
        
        -- Free up the car
        UPDATE cars SET status = 'Available' WHERE id = v_car_id;
        
        COMMIT;
        SELECT 'SUCCESS' AS result, 'Booking cancelled successfully.' AS message;
    END IF;
END$$
DELIMITER ;

-- =====================================================
-- 4. TRIGGERS
-- =====================================================

-- Trigger: After payment insert → Confirm booking
DELIMITER $$
CREATE TRIGGER after_payment_insert
AFTER INSERT ON card_payments
FOR EACH ROW
BEGIN
    -- Update booking to confirmed status
    UPDATE booking 
    SET status = 'Confirmed',
        payment_status = 'paid',
        payment_date = NEW.payment_date
    WHERE id = NEW.booking_id AND status = 'Pending';
END$$
DELIMITER ;

-- Trigger: Before booking delete → Log cancellation
DELIMITER $$
CREATE TRIGGER before_booking_delete
BEFORE DELETE ON booking
FOR EACH ROW
BEGIN
    -- Free the car if booking is cancelled
    IF OLD.status != 'Confirmed' THEN
        UPDATE cars SET status = 'Available' WHERE id = OLD.car_id;
    END IF;
    
    -- Log to a hypothetical audit table (optional)
    INSERT INTO booking_audit (booking_id, action, old_status, user_id, timestamp)
    VALUES (OLD.id, 'DELETE', OLD.status, OLD.user_id, NOW());
END$$
DELIMITER ;

-- Trigger: After car return → Calculate penalty if late
DELIMITER $$
CREATE TRIGGER after_car_return
AFTER UPDATE ON booking
FOR EACH ROW
BEGIN
    DECLARE v_penalty INT;
    DECLARE v_price INT;
    
    -- Check if car is being returned
    IF NEW.return_status = 'returned' AND OLD.return_status != 'returned' THEN
        -- Get car price
        SELECT price_per_day INTO v_price FROM cars WHERE id = NEW.car_id;
        
        -- Calculate penalty if late
        SET v_penalty = calculate_penalty(NEW.actual_return_date, NEW.to_date, v_price);
        
        -- Update penalty if any
        IF v_penalty > 0 THEN
            UPDATE booking SET penalty_amount = v_penalty WHERE id = NEW.id;
            
            -- Update car status back to available
            UPDATE cars SET status = 'Available' WHERE id = NEW.car_id;
        ELSE
            UPDATE cars SET status = 'Available' WHERE id = NEW.car_id;
        END IF;
    END IF;
END$$
DELIMITER ;

-- Create audit table for trigger
CREATE TABLE IF NOT EXISTS booking_audit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT,
    action VARCHAR(50),
    old_status VARCHAR(50),
    user_id INT,
    timestamp DATETIME,
    INDEX idx_booking (booking_id),
    INDEX idx_timestamp (timestamp)
);

-- =====================================================
-- 5. CURSORS (Report Generation)
-- =====================================================

-- Procedure: Generate Revenue Report using Cursor
DELIMITER $$
CREATE PROCEDURE generate_revenue_report()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_booking_id INT;
    DECLARE v_amount INT;
    DECLARE v_status VARCHAR(20);
    DECLARE v_total_revenue INT DEFAULT 0;
    DECLARE v_total_bookings INT DEFAULT 0;
    DECLARE v_confirmed_count INT DEFAULT 0;
    DECLARE v_pending_count INT DEFAULT 0;
    
    DECLARE cur CURSOR FOR 
        SELECT id, total_amount, status 
        FROM booking 
        WHERE payment_status = 'paid' AND total_amount IS NOT NULL;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
    
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO v_booking_id, v_amount, v_status;
        
        IF done = 1 THEN
            LEAVE read_loop;
        END IF;
        
        SET v_total_revenue = v_total_revenue + COALESCE(v_amount, 0);
        SET v_total_bookings = v_total_bookings + 1;
        
        IF v_status = 'Confirmed' THEN
            SET v_confirmed_count = v_confirmed_count + 1;
        ELSEIF v_status = 'Pending' THEN
            SET v_pending_count = v_pending_count + 1;
        END IF;
    END LOOP;
    
    CLOSE cur;
    
    SELECT 
        'REVENUE REPORT' AS report_type,
        v_total_revenue AS total_revenue_lkr,
        v_total_bookings AS total_completed_bookings,
        v_confirmed_count AS confirmed_bookings,
        v_pending_count AS pending_bookings,
        CASE 
            WHEN v_total_bookings > 0 
            THEN ROUND(v_total_revenue / v_total_bookings) 
            ELSE 0 
        END AS average_booking_value;
END$$
DELIMITER ;

-- Procedure: Generate Monthly Report using Cursor
DELIMITER $$
CREATE PROCEDURE generate_monthly_report(
    IN p_year INT,
    IN p_month INT
)
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_car_name VARCHAR(255);
    DECLARE v_count INT;
    DECLARE v_total INT DEFAULT 0;
    
    DECLARE cur CURSOR FOR 
        SELECT c.car_name, COUNT(b.id) as booking_count
        FROM cars c
        LEFT JOIN booking b ON c.id = b.car_id 
            AND YEAR(b.posting_date) = p_year 
            AND MONTH(b.posting_date) = p_month
        GROUP BY c.id, c.car_name
        ORDER BY booking_count DESC;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
    
    CREATE TEMPORARY TABLE IF NOT EXISTS monthly_report_temp (
        car_name VARCHAR(255),
        booking_count INT
    );
    
    DELETE FROM monthly_report_temp;
    
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO v_car_name, v_count;
        
        IF done = 1 THEN
            LEAVE read_loop;
        END IF;
        
        INSERT INTO monthly_report_temp VALUES (v_car_name, v_count);
        SET v_total = v_total + v_count;
    END LOOP;
    
    CLOSE cur;
    
    SELECT *, v_total AS total_bookings FROM monthly_report_temp;
    
    DROP TEMPORARY TABLE monthly_report_temp;
END$$
DELIMITER ;

-- Procedure: Get User Booking History using Cursor
DELIMITER $$
CREATE PROCEDURE get_user_booking_history(IN p_user_id INT)
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_booking_id INT;
    DECLARE v_car_name VARCHAR(255);
    DECLARE v_from_date DATE;
    DECLARE v_to_date DATE;
    DECLARE v_status VARCHAR(20);
    DECLARE v_amount INT;
    
    DECLARE cur CURSOR FOR 
        SELECT b.id, c.car_name, b.from_date, b.to_date, b.status, COALESCE(b.total_amount, 0)
        FROM booking b
        JOIN cars c ON b.car_id = c.id
        WHERE b.user_id = p_user_id
        ORDER BY b.posting_date DESC;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
    
    CREATE TEMPORARY TABLE IF NOT EXISTS user_history_temp (
        booking_id INT,
        car_name VARCHAR(255),
        from_date DATE,
        to_date DATE,
        status VARCHAR(20),
        amount INT
    );
    
    DELETE FROM user_history_temp;
    
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO v_booking_id, v_car_name, v_from_date, v_to_date, v_status, v_amount;
        
        IF done = 1 THEN
            LEAVE read_loop;
        END IF;
        
        INSERT INTO user_history_temp VALUES (v_booking_id, v_car_name, v_from_date, v_to_date, v_status, v_amount);
    END LOOP;
    
    CLOSE cur;
    
    SELECT * FROM user_history_temp;
    
    DROP TEMPORARY TABLE user_history_temp;
END$$
DELIMITER ;

-- =====================================================
-- 6. SUBQUERIES (Analytics)
-- =====================================================

-- Subquery 1: Get users who have made bookings
-- SELECT * FROM users WHERE id IN (SELECT DISTINCT user_id FROM booking);

-- Subquery 2: Find the highest paying customer
-- SELECT u.full_name, u.email, SUM(b.total_amount) as total_spent
-- FROM users u
-- JOIN booking b ON u.id = b.user_id
-- GROUP BY u.id, u.full_name, u.email
-- ORDER BY total_spent DESC LIMIT 1;

-- Subquery 3: Find cars that have never been booked
-- SELECT * FROM cars WHERE id NOT IN (SELECT DISTINCT car_id FROM booking WHERE car_id IS NOT NULL);

-- Subquery 4: Get average booking value per car type
-- SELECT car_type, AVG(total_amount) as avg_amount
-- FROM cars c
-- JOIN booking b ON c.id = b.car_id
-- GROUP BY car_type;

-- Subquery 5: Find bookings with penalties using subquery
-- SELECT * FROM booking WHERE penalty_amount > 
--     (SELECT AVG(penalty_amount) FROM booking WHERE penalty_amount > 0);

-- =====================================================
-- 7. VIEWS (For Easy Reporting)
-- =====================================================

-- View: Booking Details with User and Car Info
CREATE OR REPLACE VIEW v_booking_details AS
SELECT 
    b.id AS booking_id,
    b.from_date,
    b.to_date,
    get_days_rented(b.from_date, b.to_date) AS days_rented,
    b.total_amount,
    b.status AS booking_status,
    b.payment_status,
    b.return_status,
    b.penalty_amount,
    u.full_name AS customer_name,
    u.email AS customer_email,
    u.contact_no,
    c.car_name,
    c.car_model,
    c.car_type,
    c.price_per_day,
    cp.receipt_no,
    cp.payment_date
FROM booking b
JOIN users u ON b.user_id = u.id
JOIN cars c ON b.car_id = c.id
LEFT JOIN card_payments cp ON b.id = cp.booking_id;

-- View: Revenue Summary by Month
CREATE OR REPLACE VIEW v_monthly_revenue AS
SELECT 
    YEAR(b.payment_date) AS year,
    MONTH(b.payment_date) AS month,
    COUNT(*) AS total_bookings,
    SUM(b.total_amount) AS total_revenue,
    AVG(b.total_amount) AS avg_booking_value
FROM booking b
WHERE b.payment_status = 'paid'
GROUP BY YEAR(b.payment_date), MONTH(b.payment_date)
ORDER BY year DESC, month DESC;

-- View: Car Utilization Report
CREATE OR REPLACE VIEW v_car_utilization AS
SELECT 
    c.id,
    c.car_name,
    c.car_model,
    c.status,
    COUNT(b.id) AS total_bookings,
    COALESCE(SUM(b.total_amount), 0) AS total_earnings,
    (SELECT COUNT(*) FROM booking) AS total_system_bookings,
    ROUND(COUNT(b.id) * 100.0 / (SELECT COUNT(*) FROM booking), 2) AS utilization_percentage
FROM cars c
LEFT JOIN booking b ON c.id = b.car_id
GROUP BY c.id, c.car_name, c.car_model, c.status;

-- View: Customer Lifetime Value
CREATE OR REPLACE VIEW v_customer_ltv AS
SELECT 
    u.id,
    u.full_name,
    u.email,
    COUNT(b.id) AS total_bookings,
    COALESCE(SUM(b.total_amount), 0) AS lifetime_value,
    COALESCE(AVG(b.total_amount), 0) AS avg_booking_value,
    MIN(b.posting_date) AS first_booking,
    MAX(b.posting_date) AS last_booking,
    CASE 
        WHEN COUNT(b.id) >= 5 THEN 'VIP'
        WHEN COUNT(b.id) >= 3 THEN 'Premium'
        WHEN COUNT(b.id) >= 1 THEN 'Regular'
        ELSE 'New'
    END AS customer_tier
FROM users u
LEFT JOIN booking b ON u.id = b.user_id
GROUP BY u.id, u.full_name, u.email;

-- =====================================================
-- 8. HOW TO USE IN PHP
-- =====================================================

/*
// Using Stored Procedure: Create Booking
$stmt = $conn->prepare("CALL create_booking(?, ?, ?, ?, ?, ?)");
$stmt->bind_param("isssss", $user_id, $email, $car_id, $from_date, $to_date, $message);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

// Using Function in SELECT
$sql = "SELECT calculate_total_amount('2024-01-01', '2024-01-05', 2000) AS total";
$result = mysqli_query($conn, $sql);

// Using Trigger: Just insert payment, booking auto-confirms
$sql = "INSERT INTO card_payments (...) VALUES (...)";
mysqli_query($conn, $sql);

// Using View for reports
$sql = "SELECT * FROM v_booking_details WHERE booking_status = 'Confirmed'";
$result = mysqli_query($conn, $sql);

// Using Subquery
$sql = "SELECT * FROM users WHERE id IN (SELECT DISTINCT user_id FROM booking)";
$result = mysqli_query($conn, $sql);

// Using Cursor Procedure
$stmt = $conn->prepare("CALL generate_revenue_report()");
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
*/

-- =====================================================
-- TEST DATA (Optional)
-- =====================================================

/*
-- Test the function
SELECT calculate_total_amount('2024-03-01', '2024-03-05', 2500) AS total_amount;

-- Test the booking procedure
CALL create_booking(1, 'test@email.com', 1, '2024-04-01', '2024-04-05', 'Test booking');

-- Test revenue report
CALL generate_revenue_report();

-- Test views
SELECT * FROM v_monthly_revenue;
SELECT * FROM v_car_utilization ORDER BY total_earnings DESC;
SELECT * FROM v_customer_ltv ORDER BY lifetime_value DESC LIMIT 5;
*/
