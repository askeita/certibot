# CertiBot

CertiBot is a Symfony application for generating, crawling, and reformulating multiple-choice questions (MCQs) from Symfony documentation, with storage in MongoDB.

> **Note on browsers:**
> The crawling commands use [Symfony Panther](https://github.com/symfony/panther) under the hood and require a WebDriver (ChromeDriver, GeckoDriver, or EdgeDriver). **Google Chrome is the default and recommended browser** for this project. If you want to use Firefox or Edge instead, see `BROWSER_CONFIGURATION.md` for detailed instructions.

## Features

- **User Authentication** with registration, login, and email verification.
- **Crawling** Symfony documentation and exam topics.
- **Automatic MCQ generation** from text.
- **Interactive quiz** interface for Symfony certification training.
- **API** to trigger crawling and MCQ generation commands.
- **MongoDB storage** for questions, results, and user data.

## Requirements

- PHP >= 8.1
- [Composer](https://getcomposer.org/)
- [Node.js](https://nodejs.org/) and npm (for JS assets)
- [MongoDB](https://www.mongodb.com/)
- A supported browser + WebDriver for crawling:
  - **Recommended:** Google Chrome + ChromeDriver
  - Optional: Firefox + GeckoDriver, or Microsoft Edge + EdgeDriver

## 🔧 Automatic WebDriver Updates

This application includes an automatic WebDriver update system (ChromeDriver, GeckoDriver, EdgeDriver) to ensure compatibility with your installed browsers.

### Prerequisites

- Python 3.6+ installed and accessible via `python3`
- Supported browsers installed (Chrome, Firefox, and/or Edge)

### Manual Update

```bash
# Update all drivers
php bin/console app:update-drivers

# Update a specific driver
php bin/console app:update-drivers chrome
php bin/console app:update-drivers firefox
php bin/console app:update-drivers edge

# Force update (ignore cache)
php bin/console app:update-drivers chrome --force

# Clear version check cache
php bin/console app:update-drivers --clear-cache
```

### Automatic Update

To enable automatic driver updates before each crawl command, add to your `.env`:

```env
DRIVER_AUTO_UPDATE_ENABLED=true
```

When enabled, the system will automatically check and update drivers before:
- `app:crawl:symfony-exam-topics`
- `app:crawl:symfony-doc`

### Configuration

Available environment variables in `.env`:

```env
# Enable/disable automatic updates (default: false)
DRIVER_AUTO_UPDATE_ENABLED=false

# Interval between version checks in seconds (default: 86400 = 24h)
DRIVER_CHECK_INTERVAL=86400

# Timeout for update process in seconds (default: 120)
DRIVER_UPDATE_TIMEOUT=120
```

### How It Works

1. **Automatic platform detection**: The system auto-detects your OS (Linux, macOS, Windows)
2. **Version checking**: Compares browser version with driver version
3. **Smart caching**: Avoids frequent checks (24h default)
4. **Automatic download**: Downloads and installs compatible version if needed

### Multi-Platform Support

The system supports:
- **Linux**: linux64, linux32
- **macOS**: mac-arm64 (Apple Silicon), mac-x64 (Intel)
- **Windows**: win64, win32

Platform detection is automatic, no configuration needed.

### Troubleshooting

#### Driver not found
```bash
# Force update
php bin/console app:update-drivers chrome --force
```

#### Permission errors
```bash
# Ensure drivers directory is accessible
chmod 755 drivers/
```

#### Python not found
```bash
# Check Python installation
python3 --version

# On some systems
python --version
```

#### Version compatibility issues
```bash
# Clear cache and force update
php bin/console app:update-drivers --clear-cache --force
```

## Installation

1. **Clone the repository**
   ```bash
   git clone git@github.com:askeita/certibot.git
   cd certibot
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Install JS dependencies**
   ```bash
   npm install
   ```
   and then build the assets:
      ```bash
      npm run build
      ```

4. **Configure environment**
    - Copy `.env` to `.env.local` and adjust variables (especially MongoDB connection).
    - Configure mailer settings for email verification:
      ```env
      MAILER_DSN=smtp://user:password@localhost:1025
      ```
      For development, you can use a tool like [MailHog](https://github.com/mailhog/MailHog) or [Mailtrap](https://mailtrap.io/).

5. **Start MongoDB service** (if not already running)
   ```bash
   mongod

6. **Create the `symfony_certification` database**

7. **Start the Symfony server**
   ```bash
   symfony server:start
   ```
   or
   ```bash
   php -S localhost:8000 -t public
   ```

## Running Tests
To run the unit tests, ensure you have PHPUnit installed and run:
   ```bash
   php bin/phpunit
   ```

## Project Structure

- `src/Controller/` : Web and API controllers
- `src/Command/` : Crawling and MCQ generation commands
- `src/Repository/` : MongoDB access
- `templates/` : Twig templates
- `tests/` : Unit tests

## How to Use CertiBot

### Authentication
The application features a complete authentication system:

1. **Registration**
   - Navigate to `/register` to create a new account.
   - Fill in your username, email, password, and password confirmation.
   - Upon successful registration, a verification email will be sent to your email address.

2. **Email Verification**
   - Check your email inbox for a verification message from CertiBot.
   - Click the verification link to activate your account.
   - Once verified, you can log in to the application.

3. **Login**
   - Navigate to `/login` to access your account.
   - Enter your username and password.
   - Use the "Remember me" option to stay logged in for up to one week.

4. **Logout**
   - Click the logout button to end your session.

### Quiz interface
1. **Access the application** 
   - Open your browser and navigate to `http://localhost:8000`.
   - **Register a new account** at `http://localhost:8000/register` if you don't have one yet.
   - **Verify your email** by clicking the link sent to your email address.
   - **Log in** at `http://localhost:8000/login` with your credentials.

2. **Start a quiz**
    - Click on "Train with CertiBot" to begin.
    - Choose the desired training duration and click "Next".
    - Select the Symfony version you want to cover and click "Start training".
    - If your database is empty, the tool will first crawl the list of exam topics on the Symfony certification website. It will then crawl the Symfony documentation for the selected version and retrieve links and paragraphs related to the different topics. Then it will generate Multiple-Choice Questions (MCQs) based on the crawled content using OpenAI API.

3. **View your results**
    - After completing the quiz, you will see your score and the correct answers.
    - You can also view the links to the documentation for each question. 
    - View your attempt history (***Coming soon***).
    - Identify your strengths and weaknesses by topic (***Coming soon***).

### Command Line Interface (CLI)
Via CLI, you can run the following commands:
   ```bash
   # Crawl Symfony certification website and exam topics:
   symfony console app:crawl:symfony-exam-topics
   
   # Crawl Symfony documentation for a specific version and retrieve links and paragraphs related to the exam topics:
   symfony console app:crawl:symfony-doc
   
   # Generate Questions: Multiple-choice questions based on explored content using OpenAI API:
   symfony console app:reformulate-text-to-mcq
   ```

### API Usage
   - ***Coming soon***

## Customization and Contribution

- Edit Twig templates to change the UI.
- Add new crawlers or question types in `src/Command/`.

## License

Open source project under the MIT license.
