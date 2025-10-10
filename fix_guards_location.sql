-- Alternative fix: Change guards' location from "NCR" to "Manila"
-- This will make guards match Maria Santos' assigned location

UPDATE guard_locations 
SET location = 'Manila' 
WHERE location = 'NCR';

-- Verify the change
SELECT u.first_name, u.last_name, gl.location 
FROM users u 
JOIN guard_locations gl ON u.user_id = gl.user_id 
WHERE gl.location = 'Manila';