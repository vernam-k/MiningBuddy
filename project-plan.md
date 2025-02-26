# Mining Buddy - Project Plan

## Project Overview
Mining Buddy is a web application for Eve Online players that facilitates the management and tracking of mining operations. The application will allow players to create mining operations, invite others to join, and track the mining activity and ISK (in-game currency) value of all participants in real-time.

## Technical Stack
- **Frontend**: HTML, CSS (with a dark theme framework), JavaScript (with AJAX for real-time updates)
- **Backend**: PHP
- **Database**: MySQL
- **Authentication**: Eve Online SSO (OAuth 2.0)
- **Data Source**: Eve Online ESI API

## Eve Online API Integration
We'll need to integrate with several Eve Online ESI API endpoints:

1. **Authentication**: SSO OAuth 2.0 for user login
   - Endpoint: https://login.eveonline.com/v2/oauth/authorize/
   - Scopes needed:
     - esi-industry.read_character_mining.v1 (for mining ledger)
     - esi-universe.read_structures.v1 (potentially needed for structure information)
     - esi-markets.structure_markets.v1 (for market prices)

2. **Character Information**:
   - `/characters/{character_id}/` - Get character information
   - `/characters/{character_id}/corporationhistory/` - Get corporation history
   - `/characters/{character_id}/portrait/` - Get character portrait

3. **Corporation Information**:
   - `/corporations/{corporation_id}/` - Get corporation information
   - `/corporations/{corporation_id}/icons/` - Get corporation logos

4. **Mining Data**:
   - `/characters/{character_id}/mining/` - Get character mining ledger (updates every 10 minutes)

5. **Market Data**:
   - `/markets/prices/` - Get market prices for ores
   - `/markets/10000002/orders/` (Jita region) - Get specific market orders from Jita IV - Moon 4 - Caldari Navy Assembly Plant

6. **Universe Data**:
   - `/universe/types/{type_id}/` - Get information about specific items (ores)
   - `/universe/graphics/{graphic_id}/` - Get graphics for items (ore icons)

## Database Schema Design

### Tables:

1. **users**
   - user_id (PK, AUTO_INCREMENT)
   - character_id (BIGINT, from EVE API)
   - character_name (VARCHAR(255))
   - avatar_url (VARCHAR(512))
   - corporation_id (BIGINT)
   - corporation_name (VARCHAR(255))
   - corporation_logo_url (VARCHAR(512))
   - access_token (TEXT)
   - refresh_token (TEXT)
   - token_expires (DATETIME)
   - created_at (DATETIME)
   - last_login (DATETIME)

2. **mining_operations**
   - operation_id (PK, AUTO_INCREMENT)
   - director_id (FK to users.user_id)
   - join_code (VARCHAR(10), UNIQUE)
   - title (VARCHAR(255))
   - description (TEXT)
   - status (ENUM('active', 'ended'))
   - created_at (DATETIME)
   - ended_at (DATETIME, NULL)

3. **operation_participants**
   - participant_id (PK, AUTO_INCREMENT)
   - operation_id (FK to mining_operations.operation_id)
   - user_id (FK to users.user_id)
   - status (ENUM('active', 'left', 'kicked', 'banned'))
   - join_time (DATETIME)
   - leave_time (DATETIME, NULL)
   - is_admin (BOOLEAN, DEFAULT 0)

4. **mining_ledger_snapshots**
   - snapshot_id (PK, AUTO_INCREMENT)
   - operation_id (FK to mining_operations.operation_id)
   - user_id (FK to users.user_id)
   - type_id (INT, ore type from EVE API)
   - quantity (BIGINT)
   - snapshot_time (DATETIME)
   - snapshot_type (ENUM('start', 'update', 'end'))

5. **ore_types**
   - type_id (PK, from EVE API)
   - name (VARCHAR(255))
   - icon_url (VARCHAR(512))

6. **market_prices**
   - price_id (PK, AUTO_INCREMENT)
   - type_id (FK to ore_types.type_id)
   - jita_best_buy (DECIMAL(20,2))
   - updated_at (DATETIME)

7. **banned_users**
   - ban_id (PK, AUTO_INCREMENT)
   - operation_id (FK to mining_operations.operation_id)
   - user_id (FK to users.user_id)
   - banned_by (FK to users.user_id)
   - banned_at (DATETIME)

## Page Structure and Features

### 1. index.php
- **UI Elements**:
  - "Login with Eve Online" button
  - Top 3 mining corporations this year
  - Top 3 Mining Directors this year
  - Top 10 Mining Operations this month
  - Top 10 Mining Directors this month
- **Functionality**:
  - SSO login redirect
  - Display statistics from the database

### 2. dashboard.php (requires authentication)
- **UI Elements**:
  - Create a Mining Operation form
  - Join Mining Operation form (with code input)
  - List of previous operations with statistics
  - Current active mining operation (if any)
  - User statistics (total ISK mined, rank, etc.)
- **Functionality**:
  - Create new operations
  - Join existing operations with code
  - View operation history and stats
  - Redirect to active operation

### 3. operation.php (requires authentication)
- **UI Elements**:
  - Operation information (join code, total ISK, director info)
  - Participant list with columns:
    - Character Name & Avatar
    - Corporation Name & Logo
    - Ores Mined (with icons)
    - ISK Value
  - Admin commands (for director)
  - Leave operation button (for participants)
  - Operation timer
  - Status indicators
- **Functionality**:
  - Real-time updates via AJAX
  - Admin functions (kick, ban, promote, end operation)
  - Visual indicators for participant status
  - Mining ledger updates every 10 minutes
  - End operation countdown and data sync

### 4. Config and Utility Files
- **config.php**: Configuration settings
- **callback.php**: SSO callback handler
- **header.php**: Common navigation elements
- **footer.php**: Common footer elements
- **login.php**: Login processing
- **logout.php**: Logout processing
- **api/token_refresh.php**: Background token refresh

## Technical Implementation Details

### Authentication Flow
1. User clicks "Login with Eve Online"
2. Redirect to Eve Online SSO
3. After authentication, Eve redirects to callback.php
4. Process token, fetch character data
5. Store user data and tokens in database
6. Redirect to dashboard

### Real-time Updates Implementation
1. Use AJAX to poll server for updates
2. Implement server-side timestamp tracking
3. Use WebSockets if possible for more efficient updates
4. Handle token refresh in the background

### Mining Ledger Processing
1. Take snapshot of user's mining ledger on operation join
2. Poll for updates every 10 minutes
3. Calculate deltas between snapshots
4. Update UI with new information

### Market Price Updates
1. Fetch Jita buy orders periodically
2. Filter for highest buy order at specific location
3. Update database with current prices
4. Recalculate ISK values based on new prices

### Admin Functions
1. Implement operation director privileges
2. Add kick/ban functionality
3. Implement promotion to pass director role
4. Create end operation sequence with countdown

## Development Phases

### Phase 1: Foundation
1. Set up project structure
2. Create database schema
3. Implement authentication with Eve SSO
4. Create basic page templates with dark theme

### Phase 2: Core Functionality
1. Implement dashboard features
2. Create operation creation and joining
3. Develop basic mining operation tracking
4. Set up API integration for character data

### Phase 3: Real-time Features
1. Implement mining ledger tracking
2. Develop market price integration
3. Add real-time updates via AJAX
4. Create admin functions

### Phase 4: Polish and Optimization
1. Refine UI/UX with dark theme
2. Optimize database queries
3. Implement token refresh mechanism
4. Add detailed statistics and rankings

## Security Considerations
1. Secure storage of API tokens
2. Prevent operation code brute forcing
3. Validate all user inputs
4. Implement proper session management
5. Secure API endpoints

## Performance Optimization
1. Cache frequently accessed data
2. Minimize API calls to Eve Online
3. Optimize database queries with proper indexing
4. Implement efficient AJAX polling mechanisms

## Testing Strategy
1. Test authentication flow
2. Verify mining ledger calculations
3. Test real-time updates
4. Validate admin functions
5. Check performance under load