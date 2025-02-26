# Mining Operations Management Specification

## Overview
This document outlines the rules and implementation details for managing mining operations in the Mining Buddy application, specifically addressing operation lifecycle management, inactivity handling, and participation restrictions.

## Operation Lifecycle Management

### Operation States
Operations can exist in the following states:
- **Active**: Operation is ongoing and accepting participants
- **Ending**: Operation is in the 5-second countdown to termination
- **Syncing**: Operation has ended and is in the 10-minute data sync period
- **Ended**: Operation is complete and archived

### Operation Creation
1. Any authenticated user can create a new mining operation
2. Creating user automatically becomes the Mining Director
3. System generates a unique join code for the operation
4. Initial mining ledger snapshots are taken for the creator

### Operation Termination
Operations can be terminated in the following ways:

#### 1. Manual Termination
- Mining Director clicks "End Mining Operation" button
- System initiates 5-second countdown visible to all participants
- After countdown, operation status changes to "Syncing"
- 10-minute data sync period begins to capture final mining ledger updates
- After sync period, operation is marked "Ended" and archived

#### 2. Inactivity Auto-Termination
- System will track mining activity across all participants
- If no new ore is mined by any participant for 2 hours:
  - System will automatically initiate termination sequence
  - All participants will see notification: "Operation ending due to inactivity"
  - Standard 5-second countdown and 10-minute sync period follows
  - Operation record will be marked as "auto-terminated due to inactivity"

#### 3. System Maintenance Termination
- In case of scheduled maintenance, admins can force-terminate operations
- Special notification will be shown to users
- Abbreviated countdown may be used

## Participation Restrictions

### Single Operation Limitation
Users will be restricted to participating in only one operation at a time:

#### Technical Implementation
1. Database will track user's current active operation (if any)
2. When user attempts to create or join an operation:
   - System checks if user is already in an active operation
   - If yes, present options to either:
     a) Stay in current operation
     b) Leave current operation and join new one

#### Enforcement Mechanisms
1. **Database Constraints**:
   - `operation_participants` table will have a unique constraint on `user_id` where `status='active'`
   - Prevents database-level concurrent participation

2. **Application Logic**:
   - All join/create endpoints will verify user's current participation status
   - Dashboard will clearly show current active operation and prevent joining others

3. **Edge Case Handling**:
   - If user's session expires while in operation, they will be returned to the same operation on re-login
   - If user opens multiple browser tabs, same operation will be shown in all tabs

### Joining Operations

#### Join Process
1. User enters the join code on dashboard
2. System verifies code is valid for an active operation
3. System checks if user is already in another active operation
   - If yes, prompt to leave current operation first
   - If no, proceed with join
4. Initial mining ledger snapshot is taken
5. User is added to operation with "active" status
6. All existing participants are notified of new join

#### Rejoining After Leaving
1. User can rejoin an operation they previously left if:
   - Operation is still active
   - User has not joined a different operation
   - User has not been banned from the operation
2. On rejoin:
   - New mining ledger snapshot is taken
   - Status is reset to "active"
   - UI is updated for all participants

## Inactivity Tracking System

### Mining Activity Monitoring
The system will implement a robust inactivity detection mechanism:

1. **Operation-Level Tracking**:
   - For each operation, store last_mining_activity timestamp
   - Update whenever any participant shows new mining data
   - Schedule background task to check operations without activity for >2 hours

2. **Technical Implementation**:
   - Create background job that runs every 15 minutes
   - Query for operations where:
     - Status = 'active'
     - NOW() - last_mining_activity > 2 hours
   - For matching operations, trigger auto-termination sequence

3. **Edge Cases**:
   - New operations with no mining yet will have null last_mining_activity
   - Set grace period of 1 hour for new operations before eligible for auto-termination
   - If all participants leave, operation will still auto-terminate after 2 hours

### Activity Indicators
1. UI will show time since last mining activity
2. Warning indicators when approaching inactivity threshold:
   - At 1 hour: "Operation inactivity warning"
   - At 1.5 hours: "Operation will auto-terminate in 30 minutes if no mining occurs"
   - At 1.75 hours: "Operation will auto-terminate in 15 minutes if no mining occurs"

## Database Implementation

### Additional Fields for Operation Management

1. **mining_operations table**:
   - `last_mining_activity` (DATETIME, NULL) - Timestamp of last detected mining activity
   - `auto_terminate_exempt` (BOOLEAN, DEFAULT FALSE) - Flag to exempt from auto-termination (for special cases)
   - `termination_type` (ENUM('manual', 'inactivity', 'system', NULL), DEFAULT NULL) - Records how operation was terminated

2. **users table**:
   - `active_operation_id` (INT, NULL, FK to mining_operations.operation_id) - Current active operation user is participating in

3. **operation_participants table**:
   - Add unique constraint for `user_id` where `status='active'` to prevent multiple active operations

## API Endpoints

1. **/api/operation/join.php**
   - Handles operation joining logic
   - Checks for existing participation
   - Returns appropriate error if user already in operation

2. **/api/operation/leave.php**
   - Updates user status to "left"
   - Updates UI for all participants
   - Updates user's active_operation_id to NULL

3. **/api/operation/check_activity.php**
   - Internal endpoint for background task
   - Checks inactivity across operations
   - Triggers auto-termination if needed

## UI/UX Considerations

### Dashboard
- Clearly indicate user's current active operation
- Disable "Create Operation" and "Join Operation" forms if already in operation
- Show option to leave current operation
- Display estimated time remaining before inactivity auto-termination

### Operation Page
- Display inactivity warnings when approaching threshold
- Show countdown during auto-termination sequence
- Clear visual indication of operation status (active, ending, syncing, ended)

## Implementation Workflow

1. **Database Modifications**:
   - Add required fields for tracking participation and inactivity
   - Implement constraints to prevent multiple active operations

2. **API Development**:
   - Create endpoints for joining/leaving operations
   - Implement inactivity checking background task

3. **UI Implementation**:
   - Develop status indicators and warnings
   - Create intuitive flow for operation transitions

4. **Testing**:
   - Test operation lifecycle with various timing scenarios
   - Verify single operation constraint works correctly
   - Confirm auto-termination functions as expected