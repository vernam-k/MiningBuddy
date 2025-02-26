# Mining Buddy

Mining Buddy is a web application for EVE Online players to track mining operations, monitor profits, and optimize mining fleet efficiency. It uses the EVE Online ESI API to fetch real-time data about mining activities and market prices.

## Features

- **EVE Online SSO Authentication**: Secure login using EVE Online's Single Sign-On
- **Mining Operation Management**: Create and join mining operations with unique invite codes
- **Real-time Tracking**: Monitor mining activities of all participants in real-time
- **ISK Value Calculation**: Automatically calculate the value of mined ores based on Jita market prices
- **Mining Director Controls**: Special admin controls for operation management
- **User Statistics**: Track personal and fleet mining performance
- **Leaderboards**: See top mining corporations, directors, and operations

## Installation

### Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache, Nginx, etc.)
- EVE Online Developer Application (for API access)

### Setup Steps

1. **Clone the repository**

```bash
git clone https://github.com/vernam-k/MiningBuddy.git
cd MiningBuddy
```

2. **Create a database**

Create a MySQL database for the application and import the schema:

```bash
mysql -u your_username -p your_database_name < sql/schema.sql
```

3. **Configure the application**

Edit the `config.php` file with your database credentials and EVE Online API information:

```php
// Database settings
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_username');
define('DB_PASS', 'your_database_password');

// EVE Online API settings
define('EVE_CLIENT_ID', 'your_eve_client_id');
define('EVE_SECRET_KEY', 'your_eve_secret_key');
define('EVE_CALLBACK_URL', 'https://your-domain.com/callback.php');
```

4. **Set up an EVE Developer Application**

You'll need to create an application in the [EVE Developers Portal](https://developers.eveonline.com/) with the following scopes:
- `esi-industry.read_character_mining.v1`
- `esi-markets.structure_markets.v1`
- `esi-universe.read_structures.v1`

Set the callback URL to match your `EVE_CALLBACK_URL` configuration.

5. **Create required directories**

Ensure these directories exist and are writable by the web server:
```bash
mkdir -p logs assets/img
chmod 775 logs assets/img
```

6. **Set up a virtual host**

Configure your web server to point to the application directory. Example for Apache:

```apache
<VirtualHost *:80>
    ServerName miningbuddy.example.com
    DocumentRoot /path/to/MiningBuddy
    
    <Directory /path/to/MiningBuddy>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/miningbuddy_error.log
    CustomLog ${APACHE_LOG_DIR}/miningbuddy_access.log combined
</VirtualHost>
```

7. **Configure web server**

Make sure your web server is properly configured to serve the application.

## Usage

### First-time Setup

1. Navigate to your Mining Buddy installation in a web browser
2. Click "Login with EVE Online" and authorize the application
3. You'll be redirected to the dashboard

### Creating a Mining Operation

1. From the dashboard, click "Create Operation"
2. Enter a title and optional description
3. Click "Create Operation"
4. Share the generated join code with other fleet members

### Joining a Mining Operation

1. From the dashboard, enter the join code in the "Join Operation" form
2. Click "Join Operation"
3. You'll be redirected to the operation page

### Mining Operation Page

- **Operation Status**: View the operation duration, total ISK, and join code
- **Participants List**: See all participants, their mining activity, and ISK values
- **Mining Data**: Mining data updates every 10 minutes due to EVE API cache limitations
- **Admin Controls**: Mining Directors can kick, ban, promote, and end operations

## API Endpoints

The application includes several API endpoints for real-time updates:

- `/api/operation_status.php` - Get operation status and participant list
- `/api/mining_data.php` - Get mining data for all participants
- `/api/participant_action.php` - Handle participant actions (kick, ban, promote, leave)
- `/api/token_refresh.php` - Handle EVE API token refreshing

## Troubleshooting

### Token Errors

If you encounter token errors, check:
1. Your EVE Developer Application settings
2. The scopes assigned to your application
3. The callback URL configuration

### Mining Data Not Updating

Remember that EVE Online's mining ledger API has a 10-minute cache timer. Data will only update after this period.

## License

This project is licensed under the MIT License - see the LICENSE file for details.
