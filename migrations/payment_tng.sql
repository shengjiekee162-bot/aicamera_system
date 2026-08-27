USE ai_camera_pos;

-- Keeps historical Card/QR orders readable while allowing all new TNG sales.
ALTER TABLE orders
    MODIFY payment_method ENUM('cash','card','qr','tng') NOT NULL;
