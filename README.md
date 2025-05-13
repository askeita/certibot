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

## Usage Examples

- Access the quiz interface: http://localhost:8000
- Trigger crawling or MCQ generation via the API or admin interface.

## Customization

- Edit Twig templates to change the UI.
- Add new crawlers or question types in `src/Command/`.

## License

Open source project under the MIT license.
