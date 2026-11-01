# CMMP scheduling rules

This document describes how **church events**, **service requests**, **facilities**, and **pastor/counselor** assignments interact.

## Event statuses

| Status | Blocks calendar / conflicts? |
|--------|------------------------------|
| DRAFT | No |
| PUBLISHED | Yes (venue/time overlap) |
| CANCELLED | No |
| COMPLETED | Shown historically; conflict rules follow published logic until ended |

Publishing an event runs the same conflict checks as member service booking.

## Facility overlap

- **Specific venue** selected: conflicts include published events at that venue **and church-wide events** (`facility_id` is null).
- **No venue** selected: only **church-wide** published events are treated as venue conflicts until a facility is chosen.

## Service requests on the calendar

| Member calendar | Admin calendar |
|-----------------|----------------|
| **Red days** = confirmed bookings (assigned / approved / scheduled / confirmed / completed) | Red days include published events and confirmed bookings |
| **Pending** requests appear as separate calendar entries (not red whole-day blocks) | Full schedule view |

New member submissions do **not** treat other members’ **pending** requests as hard blocks. Confirmed and in-progress bookings still block.

## Service types

- **House blessing**: at the member’s home; not blocked by church facility events. Hours **8:00 AM–4:00 PM**.
- **Funeral service**: conflict window uses **6:00–8:00 PM** “funeral night” slices.
- **Counseling**: uses the **counseling office** facility for conflict checks.

## Event category → service blocks

Configured in `event_service_blocks` (see migration `step25_event_service_blocks.sql`). Example: **Worship** may block **Facility reservation** and **Mañanita** on the same calendar day even when a specific room might appear free.

Defaults apply if the table is empty.

## Pastor / counselor assignment

Staff assignment checks:

- Overlapping **appointments**
- Overlapping **service assignments**
- Overlapping **published event assignments** (`event_assignments`)

## Member flow

1. Pick a date (optional) → service list shows **(Unavailable on this date)** only for types that conflict (time probe, venue, category rules).
2. Submit → `assertAvailable()` validates exact date, time, and facility.

Administrators should run new SQL migrations after pulling updates.
