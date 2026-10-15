# House Blessing Request Form - Implementation Complete ✅

## Overview
Successfully implemented a complete House Blessing Request form with the following features:

## Form Fields
The House Blessing form displays these custom fields:
- **House/Family Name** - Name of the family receiving the blessing
- **Address** - Full address of the house to be blessed
- **Landmark** - Nearby landmark to help locate the address
- **Preferred Date** - Date picker for the blessing service
- **Preferred Time** - Time picker with constraints (8:00 AM - 4:00 PM)

## Key Features Implemented

### 1. Time Constraints
- **Booking window**: 8:00 AM to 4:00 PM only
- Time input includes `min="08:00"` and `max="16:00"` HTML attributes
- Server-side validation in `helpers/service_request.php` ensures times stay within window
- Error message: "House blessing services can only be booked between 8:00 AM and 4:00 PM."

### 2. Auto-Duration Calculation
- End time is automatically set to 1 hour after the start time
- User enters start date and time, end time is calculated by JavaScript
- The end datetime field is hidden from the user (automatically managed)

### 3. Form Validation
Updated `helpers/service_request.php` with:
- Required field validation for: House/Family Name, Address, Landmark
- Time window validation (8 AM - 4 PM)
- Form submission validation to prevent out-of-window times

### 4. Conflict Detection - No Facility Matching
Modified `models/ChurchCalendar.php`:
- House Blessing requests use **time/date-only conflict detection**
- NOT venue/facility-based (since they happen at people's homes)
- Completely separate from other services:
  - Won't conflict with venue-specific events
  - Won't block facility bookings
  - Can occur anywhere in the church schedule
  
**Implementation Detail**: 
```sql
-- House Blessing query (no facility_id checking)
SELECT sr.subject AS label, sr.preferred_start_datetime AS start_datetime,
       sr.preferred_end_datetime AS end_datetime, NULL AS facility_id
FROM service_requests sr 
INNER JOIN service_types st ON st.id = sr.service_type_id 
WHERE st.code = "HOUSE_BLESSING" 
  AND sr.status IN ("APPROVED", "SCHEDULED")
  AND EXISTS (SELECT 1 FROM service_request_participants WHERE service_request_id = sr.id)
```

### 5. Database Storage
Request data saved to `service_requests` table with:
- `service_type_id` = 6 (HOUSE_BLESSING)
- `preferred_facility_id` = NULL (no facility required)
- `preferred_start_datetime` = Date + Time (8 AM - 4 PM)
- `preferred_end_datetime` = Start time + 1 hour
- `request_data` = JSON containing: `house_family_name`, `house_address`, `house_landmark`

## Files Modified

### 1. `member/services/request.php`
- Complete rewrite with proper form structure
- Added House Blessing conditional form section
- Added JavaScript for time handling and 1-hour auto-duration
- Client-side time validation (8 AM - 4 PM check)

### 2. `helpers/service_request.php`
- Added `house_family_name`, `house_address`, `house_landmark` to input fields
- Added HOUSE_BLESSING to service_request_participants() function
- Added time window validation in validate_service_request()
- HOUSE_BLESSING requires 3 fields: family name, address, landmark

### 3. `models/ChurchCalendar.php`
- Separated HOUSE_BLESSING from facility-based conflict checking
- Added dedicated query for House Blessing (no facility filtering)
- Added facility clause handling: House Blessings skip facility matching

## Testing Checklist
✅ Form loads with House Blessing selected
✅ All 5 required fields display correctly
✅ Time picker shows with 8:00 AM - 4:00 PM constraints
✅ Service description displays: "House blessing request"
✅ Submit button present
✅ JavaScript time calculation ready
✅ Validation rules configured

## Conflict Scenarios Handled
| Scenario | Result | Reason |
|----------|--------|--------|
| House Blessing (8-9 AM) + Event at Main Hall (10-11 AM) | ✅ NO CONFLICT | Different time slots |
| House Blessing (10-11 AM) + House Blessing (10-11 AM) | ✅ CONFLICT | Same time range |
| House Blessing (all day) + Wedding at Main Hall (same time) | ✅ NO CONFLICT | Different resource types (home vs facility) |
| Two Events at Main Hall (overlapping) | ✅ CONFLICT | Same facility (still works) |

## Next Steps (Ready for Testing)
1. Fill out House Blessing form with test data
2. Submit and verify success redirect
3. Check calendar to see if request appears (once approved)
4. Verify no false conflicts with facility-based events
5. Test time validation (try times outside 8 AM - 4 PM)

## Technical Notes
- House Blessings do NOT use facility_id field (always NULL)
- Time window is enforced both client-side and server-side
- Duration is always exactly 1 hour (calculated, not user-input)
- Conflicts check only time/date, ignoring facility matching
- Participants table stores family name in "name" field with role "FAMILY_CONTACT"
