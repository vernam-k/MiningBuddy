# Statistics and Archives Management Specification

## Overview
This document outlines how Mining Buddy will manage statistics calculation, historical data retention, and archived operations. The system will maintain comprehensive statistics at multiple levels (individual, corporation, operation) while ensuring efficient data storage and retrieval.

## Statistics Tracking System

### Statistics Categories

#### 1. User Statistics
- Total ISK mined (all time, yearly, monthly, weekly)
- Total ore volume mined by type
- Number of operations participated in
- Average ISK per operation
- Average mining rate (ISK/hour)
- Peak mining performance (best operation)
- Total active mining time
- Ranking among all users
- Most mined ore types

#### 2. Corporation Statistics
- Total ISK mined by all members
- Number of members participating in operations
- Number of operations directed by members
- Average ISK per operation
- Top performing members
- Most active mining directors
- Corporation ranking
- Most mined ore types

#### 3. Operation Statistics
- Total ISK generated
- Duration
- Number of participants
- Peak concurrent participants
- Distribution of ore types mined
- Most valuable contributor
- Most active contributor
- Average ISK per participant
- Mining efficiency over time

#### 4. Director Statistics
- Number of operations created
- Total ISK generated across all operations
- Average ISK per operation
- Total number of participants managed
- Average operation duration
- Director ranking

### Statistics Calculation System

#### Real-time vs. Aggregated Statistics
The system will implement a two-tier statistics approach:

1. **Real-time Statistics**
   - Calculated on-demand for active operations
   - Updated as new mining data becomes available
   - Displayed on operation page and dashboard
   - Not permanently stored until operation ends

2. **Aggregated Statistics**
   - Pre-calculated and stored for completed operations
   - Updated in batch processes
   - Used for historical views and rankings
   - Optimized for efficient retrieval

#### Calculation Methods

1. **Operation-Level Calculations**
   - When operation ends, final snapshots are taken
   - Comparison between initial and final ledger states
   - Results stored in dedicated statistics tables
   - Materialized data for common queries

2. **User-Level Aggregations**
   - Background process updates user statistics after each operation
   - Incremental updates to avoid recalculating all data
   - Daily refresh of ranking data
   - Monthly archiving of detailed statistics

3. **Corporation-Level Aggregations**
   - Derived from user statistics for corporation members
   - Updated daily via scheduled task
   - Special handling for corporation changes (user switching corps)

## Data Archiving System

### Operation Archiving

#### Archiving Process
1. After operation completes (including 10-minute sync period):
   - All participant data is finalized
   - Final statistics are calculated
   - Operation status changes to "Archived"
   - Detailed mining data is moved to archival tables

2. **Archival Database Structure**:
   - Core operation data remains in primary tables
   - Detailed mining records moved to partitioned archival tables
   - Optimized for storage efficiency over query speed
   - Indexed for specific historical queries

#### Data Retention Policies
1. **Complete Data Retention**: 
   - Operation summary data: Indefinite
   - User participation records: Indefinite
   - Aggregated statistics: Indefinite

2. **Detailed Data Retention**:
   - Minute-by-minute mining data: 30 days
   - Raw ledger snapshots: 60 days
   - Market price history: 90 days

3. **Data Compression**:
   - After 30 days, detailed data compressed
   - After 90 days, certain raw data purged after statistics extraction
   - Critical data backed up before purging

### Archive Access System

#### User-Facing Access
1. **Dashboard Archive Access**:
   - List of user's past operations
   - Filter by date range, ISK value, participants
   - Export operation history
   - View detailed operation statistics

2. **Public Statistics Access**:
   - Leaderboards
   - Corporation rankings
   - Top operations of all time
   - Notable mining achievements

#### Admin-Level Access
1. **Mining Director Tools**:
   - Comprehensive statistics for all past operations
   - Participant performance comparison
   - Operation efficiency analysis
   - Export to CSV/Excel formats

## Database Implementation

### Statistics Tables

1. **user_statistics**
   - user_id (FK to users)
   - total_isk_mined
   - total_operations_joined
   - total_active_time
   - average_isk_per_operation
   - average_isk_per_hour
   - highest_isk_operation_id
   - highest_isk_operation_value
   - current_rank
   - last_week_rank
   - last_month_rank
   - last_updated

2. **corporation_statistics**
   - corporation_id
   - corporation_name
   - total_isk_mined
   - total_members_participating
   - total_operations_directed
   - average_isk_per_operation
   - most_active_director_id
   - top_contributor_id
   - current_rank
   - last_week_rank
   - last_month_rank
   - last_updated

3. **operation_statistics**
   - operation_id (FK to mining_operations)
   - total_isk_generated
   - operation_duration
   - participant_count
   - peak_concurrent_participants
   - most_valuable_contributor_id
   - most_active_contributor_id
   - average_isk_per_participant
   - most_mined_ore_type_id
   - operation_efficiency_score
   - created_at

4. **time_period_statistics**
   - period_id
   - period_type (ENUM: 'daily', 'weekly', 'monthly', 'yearly')
   - start_date
   - end_date
   - total_operations
   - total_isk_generated
   - total_participants
   - top_operation_id
   - top_user_id
   - top_corporation_id
   - total_mining_time

### Archive Tables

1. **archived_operations**
   - operation_id (FK to mining_operations)
   - archive_date
   - archive_data_path (for externally stored data)
   - summary_json (contains condensed operation summary)
   - is_compressed
   - has_detailed_data
   - retention_expire_date

2. **archived_mining_data**
   - archive_id
   - operation_id (FK to mining_operations)
   - user_id (FK to users)
   - data_date (for partitioning)
   - mining_data_json (compressed JSON of mining data)
   - created_at

3. **yearly_leaderboards**
   - year
   - top_users_json (JSON array of top 100 users and stats)
   - top_corporations_json (JSON array of top 50 corps and stats)
   - top_operations_json (JSON array of top 50 operations)
   - top_directors_json (JSON array of top 50 directors)
   - generated_at

## Data Visualization & Reporting

### Dashboard Statistics Visualizations
1. **Personal Performance Graphs**
   - ISK mined over time (daily/weekly/monthly)
   - Comparison to previous periods
   - Ranking history
   - Mining composition by ore type (pie chart)

2. **Leaderboard Widgets**
   - Current position in various rankings
   - Distance to next rank
   - Historical rank changes
   - Top performers this period

### Operation History Visualizations
1. **Operation Timeline View**
   - Chronological list of past operations
   - Visual indicators for operation size/value
   - Filters for time period, ISK value, participants
   - Quick view vs. detailed view options

2. **Operation Details View**
   - Comprehensive statistics for selected operation
   - Participant contribution breakdown
   - Ore composition charts
   - Mining rate over time during operation
   - Comparison to user's average performance

### Administration Reporting
1. **Director Dashboards**
   - Operation success metrics
   - Participant retention rates
   - Comparative performance to other directors
   - Operation efficiency trends

2. **Site-wide Statistics**
   - Global mining activity charts
   - Active users over time
   - Most profitable time periods
   - Most popular mining directors and corporations

## Implementation Strategy

### Database Optimization
1. **Table Partitioning**
   - Partition archive tables by date
   - Separate recent from historical data
   - Optimize query performance for recent operations

2. **Indexing Strategy**
   - Index fields used in sorting and filtering
   - Composite indexes for common query patterns
   - Periodic index maintenance

3. **Caching Layer**
   - Redis cache for frequently accessed statistics
   - Materialized views for complex aggregations
   - Cache invalidation strategy based on data updates

### Background Processing
1. **Statistics Calculation Jobs**
   - Scheduled tasks for recalculating aggregated statistics
   - Nightly processing of ranking updates
   - Weekly generation of leaderboards

2. **Archiving Jobs**
   - Daily job to identify operations for archiving
   - Weekly compression of older detailed data
   - Monthly purging of expired detailed data

3. **Data Integrity Jobs**
   - Verification of statistics accuracy
   - Cross-check of aggregated vs. raw data
   - Reconciliation of inconsistencies

## User Interface Integration

### Dashboard Integration
1. **Statistics Widgets**
   - Personalized statistics summary
   - Historical operation list with key metrics
   - Ranking information and progress indicators
   - Quick filters for viewing past performance

2. **Archive Access**
   - Searchable archive of past operations
   - Filter options for finding specific operations
   - Export functionality for personal records
   - Detailed view for analyzing past performance

### Leaderboards and Rankings
1. **Main Leaderboard Page**
   - Tabs for different time periods (all-time, yearly, monthly)
   - Filters for different statistical categories
   - Corporation vs. Individual views
   - Regional filters (optional future feature)

2. **Detailed Ranking Pages**
   - Per-category detailed statistics
   - Historical ranking charts
   - Performance comparison tools
   - Achievement badges and recognition

### Operation History Access
1. **User History View**
   - Comprehensive list of past operations
   - Sortable by various metrics
   - Detailed drill-down into specific operations
   - Performance comparisons between operations

2. **Global Operation History**
   - List of notable operations (top ISK, participant count, etc.)
   - Famous mining fleet achievements
   - Historical trends in mining operations

## Performance Considerations

### Query Optimization
1. **Aggregate Table Usage**
   - Pre-calculate common statistics
   - Update aggregates on schedule rather than calculating on-demand
   - Use materialized views for complex statistics

2. **Pagination and Lazy Loading**
   - Implement pagination for long history lists
   - Lazy load detailed statistics when viewing specific operations
   - Progressive loading of historical data

### Data Volume Management
1. **Archival Strategy**
   - Move older detailed data to cold storage
   - Keep summary data readily available
   - Implement data retrieval system for accessing archived details

2. **Purging Policies**
   - Define clear retention policies
   - Automate purging of non-essential historical data
   - Maintain compliance with privacy regulations

## Future Enhancements

### Advanced Analytics
1. **Predictive Mining Models**
   - Predict optimal mining fleet compositions
   - Forecast market trends for ore values
   - Suggest optimal mining times

2. **Operation Effectiveness Scoring**
   - Develop scoring algorithm for operation efficiency
   - Compare similar operations for optimization
   - Provide recommendations for improving mining efficiency

### Extended Reporting
1. **Corporation Management Tools**
   - Fleet composition analysis
   - Member performance tracking
   - Mining operation planning tools

2. **Economic Impact Analysis**
   - Track contribution to market movement
   - Analyze impact on regional economies
   - Provide market intelligence for ore sales