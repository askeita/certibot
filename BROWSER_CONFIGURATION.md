# Browser Configuration for Crawlers (Chrome, Firefox, Edge)

This document explains how to configure which browser is used by the Panther-based crawlers in this project.

By default, **Google Chrome** is the recommended and configured browser.
You can also run the crawlers with **Firefox** or **Microsoft Edge** if the corresponding WebDriver is available.

## 1. Environment Variables

The following environment variables control the browser choice and WebDriver paths:

- `BROWSER` – which browser to use (`chrome`, `firefox`, or `edge`)
- `CHROME_DRIVER_PATH` – absolute path to the ChromeDriver binary
- `GECKO_DRIVER_PATH` – absolute path to the GeckoDriver (Firefox) binary
- `EDGE_DRIVER_PATH` – absolute path to the EdgeDriver (Microsoft Edge) binary

### 1.1 Default values in `.env`

```env
# Default browser for crawlers (chrome, firefox, edge)
BROWSER=chrome

# Default WebDriver paths (override in .env.local as needed)
CHROME_DRIVER_PATH=/var/www/html/github-ask/certibot/drivers/chromedriver
GECKO_DRIVER_PATH=/var/www/html/github-ask/certibot/drivers/geckodriver
EDGE_DRIVER_PATH=/var/www/html/github-ask/certibot/drivers/msedgedriver
```

### 1.2 Local overrides in `.env.local`

On your development machine, you can override these values. For example, to use Firefox:

```env
BROWSER=firefox

CHROME_DRIVER_PATH=/var/www/html/github-ask/certibot/drivers/chromedriver
GECKO_DRIVER_PATH=/var/www/html/github-ask/certibot/drivers/geckodriver
EDGE_DRIVER_PATH=/var/www/html/github-ask/certibot/drivers/msedgedriver
```

Or to use Edge:

```env
BROWSER=edge

CHROME_DRIVER_PATH=/var/www/html/github-ask/certibot/drivers/chromedriver
GECKO_DRIVER_PATH=/var/www/html/github-ask/certibot/drivers/geckodriver
EDGE_DRIVER_PATH=/var/www/html/github-ask/certibot/drivers/msedgedriver
```

> **Note:** Paths must point to executables that are present and have execute permissions (`chmod +x`).

## 2. Commands Using the Browser

The following Symfony console commands use Panther + WebDriver and therefore depend on the browser configuration:

- `app:crawl:symfony-exam-topics` – crawls the Symfony certification website for exam topics
- `app:crawl:symfony-doc` – crawls the Symfony documentation website for links related to topics

Internally, both commands:

1. Read `BROWSER` to decide which browser to use.
2. Use the corresponding `*_DRIVER_PATH` to locate the WebDriver binary.
3. Create a Panther client for Chrome, Firefox, or Edge.

## 3. Browser Selection Logic

The selection logic is as follows:

- If `BROWSER=firefox`:
  - Use `Client::createFirefoxClient(GECKO_DRIVER_PATH, [...options...])`
- If `BROWSER=edge`:
  - Use `Client::createChromeClient(EDGE_DRIVER_PATH, [...options...])` (EdgeDriver speaks the same protocol as ChromeDriver)
- For any other value (default `chrome`):
  - Use `Client::createChromeClient(CHROME_DRIVER_PATH, [...options...])`

If the required driver path is missing or empty, the command throws a clear `RuntimeException` indicating which path is not configured.

## 4. Installing WebDrivers

### 4.1 ChromeDriver (Recommended)

Chrome is the default and recommended browser for this project.

- Place the `chromedriver` binary in `drivers/chromedriver`
- Ensure it is executable:

```bash
chmod +x drivers/chromedriver
```

See `UPDATE_CHROMEDRIVER.md` for detailed instructions on matching ChromeDriver to your local Chrome version.

### 4.2 GeckoDriver (Firefox)

1. Download GeckoDriver for your platform (Linux) from:
   - https://github.com/mozilla/geckodriver/releases
2. Extract it and place the `geckodriver` binary in:

```bash
/var/www/html/github-ask/certibot/drivers/geckodriver
chmod +x /var/www/html/github-ask/certibot/drivers/geckodriver
```

3. Set in `.env.local`:

```env
BROWSER=firefox
GECKO_DRIVER_PATH=/var/www/html/github-ask/certibot/drivers/geckodriver
```

### 4.3 EdgeDriver (Microsoft Edge)

1. Download Edge WebDriver for Linux from Microsoft docs.
2. Place the `msedgedriver` binary in:

```bash
/var/www/html/github-ask/certibot/drivers/msedgedriver
chmod +x /var/www/html/github-ask/certibot/drivers/msedgedriver
```

3. Set in `.env.local`:

```env
BROWSER=edge
EDGE_DRIVER_PATH=/var/www/html/github-ask/certibot/drivers/msedgedriver
```

## 5. Verifying the Configuration

To quickly verify which browser is being used, you can temporarily remove the `--headless` option in the commands and run:

```bash
symfony console app:crawl:symfony-exam-topics 6 -vv
symfony console app:crawl:symfony-doc 6 -vv
```

The appropriate browser window should open (Chrome, Firefox, or Edge) and perform the crawling.

For normal, unattended runs, it is recommended to keep the `--headless` option enabled.

## 6. Notes

- Chrome remains the **default and recommended** browser for this project due to better support and more stable headless behavior.
- Firefox and Edge support is best-effort and may require updated drivers as browsers evolve.
- Always ensure that the WebDriver version matches the installed browser version.
