# Real-time Updates and Token Auto-Refresh Specification

## Overview
This document details how the Mining Buddy application will implement real-time updates on the operation page while handling token refreshing in the background, ensuring users can leave the page open for extended periods without disruption.

## Mining Operation Page Real-time Updates

### User Experience
- Users will be able to leave the operation page open indefinitely
- Mining data will update automatically every 10 minutes (matching ESI's cache timer)
- Participant status changes (joins, leaves, kicks, bans) will update immediately
- ISK values will update as new market data becomes available
- UI will clearly indicate when the last update occurred and when the next update is expected
- No manual refresh of the page will be required at any point

### Technical Implementation

#### 1. Client-Side Components
- **AJAX Polling System**
  - Primary update loop that checks for changes at regular intervals
  - Separate polling frequencies for different data types:
    - Participant status: Every 5-10 seconds
    - Mining data: Every 10 minutes (matching ESI's cache)
    - Market prices: Every 30 minutes
  
- **WebSocket Alternative** (if hosting environment supports it)
  - Push-based updates for immediate participant status changes
  - Server-initiated updates when new mining data is available
  
- **Update Visualization**
  - Visual indicators when data is being fetched
  - Smooth transitions when updating table values
  - Countdown timers showing time until next update
  - Event log showing joins, leaves, and other activities

#### 2. Server-Side Components
- **Update API Endpoints**
  - `/api/operation_status.php`: Returns current operation status and participant list
  - `/api/mining_data.php`: Returns latest mining data for all participants
  - `/api/market_prices.php`: Returns current ore prices from Jita
  
- **Change Detection System**
  - Timestamp-based tracking of data changes
  - Only send changed data to minimize bandwidth usage
  - Queue important updates for immediate delivery

- **Background Processing**
  - Scheduled tasks to fetch mining data from ESI every 10 minutes
  - Market price updates at regular intervals
  - Database cleanup and optimization tasks

## Token Auto-Refresh Implementation

### Challenge
EVE Online's OAuth tokens expire after 20 minutes, but users need to keep the mining operation page open for hours without interruption.

### Solution
Implement a transparent token refresh system that:
1. Tracks token expiration for all active users
2. Refreshes tokens before they expire (e.g., at 15 minutes)
3. Updates database with new tokens
4. Continues API operations without user awareness

### Technical Implementation

#### 1. Token Management
- **Token Storage**
  - Store both access_token and refresh_token in database
  - Include token_expires timestamp (server time + expires_in seconds)
  - Add last_used timestamp to prioritize refresh for active users

- **Refresh Strategy**
  - Server-side scheduled task to scan for tokens nearing expiration
  - Prioritize refresh for users in active operations
  - Stagger refreshes to avoid API rate limits

#### 2. Client-Side Token Handling
- **Refresh Detection**
  - Client AJAX calls will check for 401 Unauthorized responses
  - If token expired error occurs, trigger client-side refresh request
  - Implement exponential backoff for retry attempts

- **Transparent User Experience**
  - Show subtle indicator during token refresh process
  - Cache last valid data to display during refresh
  - Resume normal updates once new token is obtained

#### 3. Server-Side Refresh Process
- **Refresh Endpoint** (`/api/token_refresh.php`)
  - Accept user_id parameter
  - Use refresh_token to obtain new access_token from EVE SSO
  - Update database with new tokens and expiration
  - Return success/failure status

- **Automated Background Refresh**
  - Scheduled task runs every 5 minutes
  - Identifies tokens expiring in the next 5 minutes
  - Performs refresh and updates database
  - Logs any refresh failures for monitoring

- **Failure Handling**
  - Track failed refresh attempts
  - Handle invalid refresh tokens by prompting re-authentication
  - Implement notification system for persistent failures

## Implementation Flow

### Initialization (Page Load)
1. User joins or creates mining operation
2. Initial data is fetched from ESI and displayed
3. Client initializes polling system and token monitoring
4. Server records user as active in the operation

### Ongoing Updates
1. Client polls server at appropriate intervals for different data types
2. Server checks if user's token is valid before making ESI requests
3. If ESI data is cached (within 10 min window), serve from cache
4. If new ESI data is available, fetch, process, and update database
5. Return processed data to client for display
6. Client updates UI with new information

### Token Refresh Process
1. Server background task identifies tokens nearing expiration
2. For each token, perform refresh operation with EVE SSO
3. Update database with new access token and expiration
4. Log successful refresh operations
5. Client operations continue uninterrupted

### Error Handling
1. If token refresh fails, mark user for re-authentication
2. If ESI API is temporarily unavailable, use cached data and retry
3. If user's token permissions are insufficient, prompt for new authentication
4. Log all errors for monitoring and troubleshooting

## User Interface Elements

### Status Indicators
- Token status indicator (small icon in corner)
  - Green: Active and valid
  - Yellow: Refreshing
  - Red: Error, needs re-authentication
  
- Data freshness indicators
  - Last updated timestamp
  - Next update countdown
  - Visual feedback during updates

### Notifications
- Toast notifications for important events
  - Participant joins/leaves
  - Admin actions
  - Token refresh issues
  
- Status messages
  - "Mining data updates every 10 minutes due to EVE API cache"
  - "Market prices updated from Jita"
  - "Token refreshed successfully"

## Technical Considerations

### Performance Optimization
- Minimize DOM updates by using virtual DOM comparison
- Batch updates to reduce browser reflow/repaint
- Use efficient data structures for change detection
- Implement request throttling and debouncing

### Bandwidth Efficiency
- Send only changed data, not complete dataset
- Compress responses where appropriate
- Implement progressive loading for large operations

### Browser Compatibility
- Support modern browsers (Chrome, Firefox, Safari, Edge)
- Handle tab visibility changes (pause/resume updates)
- Manage multiple open tabs with same operation

### Security
- Server-side validation for all requests
- CSRF protection for authenticated endpoints
- Rate limiting to prevent abuse
- Secure handling of tokens (never exposed to client-side JavaScript)