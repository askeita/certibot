# CertiBot

CertiBot is a Symfony application for generating, crawling, and reformulating multiple-choice questions (MCQs) from Symfony documentation, with storage in MongoDB.

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
