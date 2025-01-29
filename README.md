# Telegram File Downloader with MadelineProto

A Telegram file downloader that runs as a user client to download files from any accessible channel or chat and gives you a link to download the file. Built with MadelineProto.

## Features

- Downloads files from any accessible Telegram channel/chat
- Interactive commands within Telegram
- Stores files securely on your server
- Provides instant download links
- Supports all file types
- File size limit configuration
- MIME type filtering
- Clean and maintainable code structure

## Requirements

- PHP 8.0 or higher
- Composer
- Web server with HTTPS support
- Telegram API credentials (API ID and Hash)

## Installation

1. Clone this repository

2. Install dependencies:
```bash
composer install
```

3. Copy the environment file and configure it:
```bash
cp .env.example .env
```

4. Edit the `.env` file with your credentials:
- Get API_ID and API_HASH from https://my.telegram.org
- Set UPLOAD_DIR to a directory path (e.g., 'uploads')
- Set BASE_URL to your domain (e.g., 'https://your-domain.com/uploads/')

5. Create the uploads directory and ensure it's writable:
```bash
mkdir uploads
chmod 755 uploads
```

## Initial Setup (Important!)

### A. For VPS/Dedicated Servers

Before using the bot in production, you MUST perform the initial setup on your server:

1. SSH into your server and navigate to the project directory

2. Run the script for the first time:
```bash
php start.php
```

3. You will be prompted to:
   - Enter your phone number (with country code, e.g., +1234567890)
   - Enter the verification code sent to your Telegram
   - If you haven't signed up yet, enter your name

4. After successful authentication, the script will save your session credentials
   - These credentials will be stored in `user.madeline` file
   - Keep this file secure as it contains your session data
   - Do not share or expose this file

5. Once the initial setup is complete, you can:
   - Keep the script running using screen/tmux
   - Or set it up as a system service (recommended)

### Setting up as a System Service (Optional but Recommended)

Create a systemd service file (on Linux):
```bash
sudo nano /etc/systemd/system/telegram-downloader.service
```

Add the following content:
```ini
[Unit]
Description=Telegram File Downloader
After=network.target

[Service]
Type=simple
User=your-user
Group=your-group
WorkingDirectory=/path/to/telegram-filedownloader-madeline
ExecStart=/usr/bin/php start.php
Restart=always

[Install]
WantedBy=multi-user.target
```

Start the service:
```bash
sudo systemctl enable telegram-downloader
sudo systemctl start telegram-downloader
```

### B. For Shared Hosting

If you're using shared hosting, follow these special setup steps:

1. Upload the project files to your hosting:
   - Use FTP/SFTP to upload all files to your hosting
   - Place files in a directory like `public_html/telegram-downloader`
   - Make sure the directory is accessible via your domain

2. Configure PHP settings:
   - Check if your hosting meets these requirements:
     ```
     memory_limit = 256M (or higher)
     max_execution_time = 0
     allow_url_fopen = On
     ```
   - If available, set these in your `.htaccess` file:
     ```apache
     php_value memory_limit 256M
     php_value max_execution_time 0
     php_value allow_url_fopen On
     ```
   - Or create/edit `php.ini` in your project directory:
     ```ini
     memory_limit = 256M
     max_execution_time = 0
     allow_url_fopen = On
     ```

3. Set up the uploads directory:
   ```bash
   # Create directory outside public_html for security
   mkdir ../telegram_uploads
   chmod 755 ../telegram_uploads
   ```
   
   Update your `.env` file:
   ```
   UPLOAD_DIR=../telegram_uploads
   BASE_URL=https://your-domain.com/download.php?file=
   ```

4. Create a secure download script (`download.php`) (Optional but recommended):
   ```php
   <?php
   // Secure file download handler
   session_start();
   
   // Load environment variables
   require __DIR__ . '/vendor/autoload.php';
   $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
   $dotenv->load();
   
   $file = $_GET['file'] ?? '';
   $uploadDir = $_ENV['UPLOAD_DIR'];
   
   // Basic security checks
   if (empty($file) || !preg_match('/^[a-zA-Z0-9_-]+\.[a-zA-Z0-9]+$/', $file)) {
       die('Invalid file request');
   }
   
   $filePath = $uploadDir . '/' . $file;
   if (!file_exists($filePath)) {
       die('File not found');
   }
   
   // Serve the file
   header('Content-Type: ' . mime_content_type($filePath));
   header('Content-Disposition: attachment; filename="' . basename($file) . '"');
   header('Content-Length: ' . filesize($filePath));
   readfile($filePath);
   exit;
   ```

5. Initial Authentication (Two Options):

   Option 1 - Using SSH Access (if available):
   ```bash
   ssh username@your-hosting
   cd public_html/telegram-downloader
   php start.php
   ```

   Option 2 - Using Web Interface:
   - Create a temporary `auth.php`:
   ```php
   <?php
   require 'vendor/autoload.php';
   require 'src/UserClient.php';
   require 'src/FileHandler.php';
   
   // Load environment variables
   $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
   $dotenv->load();
   
   // Start authentication
   $settings = new \danog\MadelineProto\Settings;
   $settings->getAppInfo()
       ->setApiId((int)$_ENV['API_ID'])
       ->setApiHash($_ENV['API_HASH']);
   
   $client = new \TelegramFileDownloader\UserClient('user.madeline');
   $client->start();
   
   echo "Authentication complete! Delete this file now.";
   ```
   - Access it via browser: `https://your-domain.com/telegram-downloader/auth.php`
   - After authentication, DELETE `auth.php` immediately

6. Running the Script:

   For shared hosting without SSH access, set up a cron job:
   - Go to your hosting control panel's Cron Jobs section
   - Add a new cron job:
     ```
     * * * * * cd /home/username/public_html/telegram-downloader && /usr/bin/php start.php
     ```
   - Or use hosting's "PHP Command" if available:
     ```
     php -f /home/username/public_html/telegram-downloader/start.php
     ```

7. Security Recommendations:
   - Place `uploads` directory outside public_html
   - Use the secure download.php script
   - Set proper file permissions (755 for directories, 644 for files)
   - Keep session files (`*.madeline`) secure
   - Delete authentication script after setup
   - Use HTTPS for your domain
   - Set up proper error reporting in PHP

## Usage

Once the initial setup is complete and the script is running, you can use these commands in any Telegram chat:

1. Basic Commands:
   - `/help` - Show available commands
   - `/download [message link]` - Download file from a message link
   Example: `/download https://t.me/channel/123`

2. You can also:
   - Forward any message with media to your account
   - Reply to any message containing media with `/download`
   - Send a Telegram message link directly

Message link format examples:
- Public channel: https://t.me/channelname/123
- Private channel: https://t.me/c/1234567890/123

## Configuration

Configure the following in your `.env` file:

### Required Settings:
- `API_ID`: Your Telegram API ID
- `API_HASH`: Your Telegram API Hash
- `UPLOAD_DIR`: Directory where files will be stored
- `BASE_URL`: Base URL for generating download links (must end with /)

### Optional Settings:
- `MAX_FILE_SIZE`: Maximum allowed file size in bytes (default: 100MB)
- `ALLOWED_MIME_TYPES`: Comma-separated list of allowed MIME types, or * for all
- `LOGGER_USERNAME`: Custom username to show in logs (default: FileDownloader)

## Security Considerations

- The application generates safe filenames to prevent path traversal attacks
- File size limits prevent server storage abuse
- MIME type filtering available for controlling allowed file types
- Files are stored with safe permissions
- User sessions are stored securely
- Always use HTTPS for your domain
- Keep your session file (`user.madeline`) secure

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

For issues and feature requests, please open an issue on GitHub. 