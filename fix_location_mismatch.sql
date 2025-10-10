-- Fix location mismatch: Change Maria Santos' location from "Manila" to "NCR"
-- This will allow her to see guards located in NCR

UPDATE oic_locations 
SET location_name = 'NCR' 
WHERE oic_user_id = (
    SELECT User_ID 
    FROM users 
    WHERE First_Name = 'Maria' AND Last_Name = 'Santos' 
    AND Role_ID = 8
);

-- Verify the change
SELECT u.First_Name, u.Last_Name, ol.location_name 
FROM users u 
JOIN oic_locations ol ON u.User_ID = ol.oic_user_id 
WHERE u.Role_ID = 8;