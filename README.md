# CertiBot

CertiBot is a Symfony application for generating, crawling, and reformulating multiple-choice questions (MCQs) from Symfony documentation, with storage in MongoDB.

## Features

- **Crawling** Symfony documentation and exam topics.
- **Automatic MCQ generation** from text.
- **Interactive quiz** interface for Symfony certification training.
- **API** to trigger crawling and MCQ generation commands.
- **MongoDB storage** for questions and results.

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

### Quiz interface
1. **Access the application** 
   - Open your browser and navigate to `http://localhost:8000`.
   - ***Log in if necessary (Coming soon).***

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
   # Start exploring the documentation
   php bin/console app:crawl-symfony-docs
   
   # Generate Questions: Multiple-choice questions based on explored content
   php bin/console app:generate-mcq
   ```

### API Usage
   - ***Coming soon***

## Customization and Contribution

- Edit Twig templates to change the UI.
- Add new crawlers or question types in `src/Command/`.

## License

Open source project under the MIT license.
