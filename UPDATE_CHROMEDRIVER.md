# ChromeDriver Update Guide

## Problem

When ChromeDriver and Chrome are not at the same version, you may encounter the following error:

```
Element not found: session not created: This version of ChromeDriver only supports Chrome version XXX
Current browser version is YYY with binary path /opt/google/chrome/chrome
```

## Solution

ChromeDriver must match the major version of Chrome installed on your system. This document explains how to update ChromeDriver.

## Version Verification

### 1. Check installed Chrome version

```bash
/opt/google/chrome/chrome --version
# or
google-chrome --version
```

Example output:
```
Google Chrome 144.0.7559.109
```

### 2. Check current ChromeDriver version

```bash
cd /var/www/html/github-ask/certibot/drivers
./chromedriver --version
```

Example output:
```
ChromeDriver 144.0.7559.109 (6a8d5e49388fcc8a7d56d2a275e4ef424eb10960-refs/branch-heads/7559@{#4008})
```

## Update Methods

### Method 1: Automated Bash Script (Recommended)

The project contains a bash script `update-chromedriver.sh` that automates the update process.

#### Usage:

```bash
cd /var/www/html/github-ask/certibot
bash update-chromedriver.sh
```

#### What the script does:

1. Automatically detects the installed Chrome version
2. Downloads the compatible ChromeDriver version from the official Google repository
3. Backs up the old ChromeDriver (renamed to `chromedriver.old`)
4. Installs the new ChromeDriver
5. Makes the file executable
6. Verifies that the installation was successful

#### Manual modification (if necessary):

If you need to force a specific version, edit the `update-chromedriver.sh` file and modify the variable:

```bash
CHROMEDRIVER_VERSION="144.0.7559.109"
```

### Method 2: Python Script

The project also contains a Python script `update_chromedriver.py`.

#### Usage:

```bash
cd /var/www/html/github-ask/certibot
python3 update_chromedriver.py
```

#### Manual modification:

Edit the `update_chromedriver.py` file and modify the variable:

```python
CHROME_VERSION = "144.0.7559.109"
```

### Method 3: Manual Update

If you prefer to update manually:

#### 1. Identify the required ChromeDriver version

Check the major version of Chrome (e.g., 144) and find the complete version at:
- https://googlechromelabs.github.io/chrome-for-testing/

Or use the JSON API:
```bash
curl -s https://googlechromelabs.github.io/chrome-for-testing/latest-versions-per-milestone.json | python3 -m json.tool | grep -A 3 '"milestone": "144"'
```

#### 2. Download ChromeDriver

```bash
cd /var/www/html/github-ask/certibot/drivers

# Backup the old one
mv chromedriver chromedriver.old

# Download (replace VERSION with the exact version, e.g., 144.0.7559.109)
VERSION="144.0.7559.109"
wget "https://storage.googleapis.com/chrome-for-testing-public/${VERSION}/linux64/chromedriver-linux64.zip" -O chromedriver.zip

# Extract
unzip -o chromedriver.zip
mv chromedriver-linux64/chromedriver .
chmod +x chromedriver

# Clean up
rm -rf chromedriver-linux64 chromedriver.zip
```

#### 3. Verify installation

```bash
./chromedriver --version
```

## Verify Proper Functionality

After the update, test that ChromeDriver works correctly with your crawl commands:

```bash
cd /var/www/html/github-ask/certibot

# Test exam topics crawl
symfony console app:crawl:symfony-exam-topics 7

# Test documentation crawl
symfony console app:crawl:symfony-doc 7
```

## Troubleshooting

### Error "Port 9515 is already in use"

If you get this error, a ChromeDriver process is already running:

```bash
# Identify the process
ps aux | grep chromedriver

# Kill ChromeDriver processes
pkill -f chromedriver

# Or check the port
sudo lsof -i :9515
```

### Insufficient permissions

If ChromeDriver is not executable:

```bash
cd /var/www/html/github-ask/certibot/drivers
chmod +x chromedriver
```

### ChromeDriver won't start

Verify you have the necessary dependencies:

```bash
# On Ubuntu/Debian
sudo apt-get update
sudo apt-get install -y libnss3 libgconf-2-4 libfontconfig1
```

## Official Resources

- **Chrome for Testing**: https://googlechromelabs.github.io/chrome-for-testing/
- **ChromeDriver Downloads**: https://storage.googleapis.com/chrome-for-testing-public/
- **ChromeDriver Documentation**: https://chromedriver.chromium.org/

## Update Frequency

Chrome updates automatically on a regular basis. It is recommended to:

1. Verify ChromeDriver/Chrome compatibility before each important use
2. Update ChromeDriver after each major Chrome update
3. Keep the update scripts up to date in the repository

## Important Notes

- **Always match the major version**: Chrome 144.x.x.x requires ChromeDriver 144.x.x.x
- **Minor versions** may differ slightly, but the major version must match
- **Always backup** the old ChromeDriver before updating (renamed to `chromedriver.old`)
- **Test immediately** after the update to ensure everything works

## Complete Workflow Example

```bash
# 1. Check Chrome version
/opt/google/chrome/chrome --version
# Output: Google Chrome 144.0.7559.109

# 2. Update ChromeDriver
cd /var/www/html/github-ask/certibot
bash update-chromedriver.sh

# 3. Verify new version
cd drivers
./chromedriver --version
# Output: ChromeDriver 144.0.7559.109 (...)

# 4. Test functionality
cd ..
symfony console app:crawl:symfony-exam-topics 7

# 5. If everything works, commit changes
git add update-chromedriver.sh update_chromedriver.py
git commit -m "Update ChromeDriver to version 144.0.7559.109"
```

## Version History

| Date       | Chrome Version | ChromeDriver Version | Notes                        |
|------------|----------------|---------------------|------------------------------|
| 2026-01-30 | 144.0.7559.109 | 144.0.7559.109      | Major update to v144         |
| 2026-01-29 | 141.0.7390.54  | 141.0.7390.54       | Previous version             |

---

**Last updated**: January 30, 2026
