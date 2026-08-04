#!/usr/bin/env python3
"""
Generic WebDriver Update Script
Automatically updates ChromeDriver, GeckoDriver (Firefox), or EdgeDriver
based on the installed browser version.
"""

import os
import sys
import re
import urllib.request
import json
import zipfile
import tarfile
import shutil
import subprocess
import argparse
import platform
from typing import Optional, Dict, Tuple


# Auto-detect platform
def detect_platform() -> str:
    """
    Automatically detect the current platform.

    Returns:
        Platform string compatible with WebDriver downloads
    """
    system = platform.system().lower()
    machine = platform.machine().lower()

    if system == 'linux':
        return 'linux64' if '64' in machine or 'amd64' in machine or 'x86_64' in machine else 'linux32'
    elif system == 'darwin':  # macOS
        if machine in ('arm64', 'aarch64'):
            return 'mac-arm64'
        else:
            return 'mac-x64'
    elif system == 'windows':
        return 'win64' if sys.maxsize > 2**32 else 'win32'
    else:
        raise RuntimeError(f"Unsupported platform: {system}/{machine}")


# Get drivers directory from script location
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
DRIVERS_DIR = os.path.join(SCRIPT_DIR, "drivers")
PLATFORM = detect_platform()

print(f"Detected platform: {PLATFORM}")
print(f"Drivers directory: {DRIVERS_DIR}")


class DriverUpdater:
    """Base class for driver updaters"""
    
    def __init__(self, drivers_dir: str):
        self.drivers_dir = drivers_dir
        
    def get_browser_version(self) -> Optional[str]:
        """Get the installed browser version"""
        raise NotImplementedError
        
    def get_driver_download_url(self, version: str) -> str:
        """Get the download URL for the driver"""
        raise NotImplementedError
        
    def get_driver_filename(self) -> str:
        """Get the driver filename"""
        raise NotImplementedError
        
    def extract_driver(self, archive_path: str) -> None:
        """Extract the driver from the downloaded archive"""
        driver_filename = self.get_driver_filename()

        # Determine archive type and extract
        if archive_path.endswith('.zip'):
            with zipfile.ZipFile(archive_path, 'r') as zip_ref:
                # Find the driver executable
                for file in zip_ref.namelist():
                    if driver_filename in file or 'chromedriver' in file.lower() or 'geckodriver' in file.lower():
                        # Extract with proper name
                        with zip_ref.open(file) as source:
                            with open(driver_filename, 'wb') as target:
                                shutil.copyfileobj(source, target)
                        break
        elif archive_path.endswith(('.tar.gz', '.tgz')):
            with tarfile.open(archive_path, 'r:gz') as tar_ref:
                for member in tar_ref.getmembers():
                    if driver_filename in member.name or 'chromedriver' in member.name.lower() or 'geckodriver' in member.name.lower():
                        member.name = driver_filename
                        tar_ref.extract(member)
                        break
        else:
            raise ValueError(f"Unsupported archive format: {archive_path}")

        # Set executable permissions (Unix only)
        if platform.system() != 'Windows':
            os.chmod(driver_filename, 0o750)  # More secure permissions

    def update(self) -> bool:
        """Update the driver"""
        print(f"\n{'=' * 60}")
        print(f"Mise à jour de {self.__class__.__name__}")
        print(f"{'=' * 60}\n")
        
        # Get browser version
        browser_version = self.get_browser_version()
        if not browser_version:
            print(f"❌ Impossible de détecter la version du navigateur")
            return False
            
        print(f"Version du navigateur détectée: {browser_version}")
        
        # Get download URL
        try:
            download_url = self.get_driver_download_url(browser_version)
            print(f"URL de téléchargement: {download_url}")
        except Exception as e:
            print(f"❌ Erreur lors de la récupération de l'URL: {e}")
            return False
            
        # Change to drivers directory
        os.chdir(self.drivers_dir)
        
        # Backup old driver
        driver_filename = self.get_driver_filename()
        if os.path.exists(driver_filename):
            backup_name = f"{driver_filename}.old"
            print(f"Sauvegarde de l'ancien driver vers {backup_name}...")
            if os.path.exists(backup_name):
                os.remove(backup_name)
            shutil.move(driver_filename, backup_name)
            
        # Download
        archive_name = f"{driver_filename}_temp.archive"
        print("Téléchargement en cours...")
        try:
            urllib.request.urlretrieve(download_url, archive_name)
            print("✅ Téléchargement réussi")
        except Exception as e:
            print(f"❌ Erreur lors du téléchargement: {e}")
            return False
            
        # Extract
        print("Extraction...")
        try:
            self.extract_driver(archive_name)
            # Set executable permissions (Unix/Mac only)
            if platform.system() != 'Windows':
                os.chmod(driver_filename, 0o755)
            print("✅ Installation réussie")
        except Exception as e:
            print(f"❌ Erreur lors de l'extraction: {e}")
            return False
        finally:
            # Cleanup
            if os.path.exists(archive_name):
                os.remove(archive_name)
                
        # Verify
        print("\nVérification de la version installée:")
        try:
            result = subprocess.run(
                [f"./{driver_filename}", "--version"],
                capture_output=True,
                text=True,
                timeout=5
            )
            print(result.stdout.strip())
        except Exception as e:
            print(f"⚠️  Avertissement: {e}")
            
        print(f"\n{'=' * 60}")
        print("✅ Mise à jour terminée!")
        print(f"{'=' * 60}\n")
        return True


class ChromeDriverUpdater(DriverUpdater):
    """ChromeDriver updater"""
    
    def get_browser_version(self) -> Optional[str]:
        """Get Chrome version"""
        try:
            # Try google-chrome
            result = subprocess.run(
                ["google-chrome", "--version"],
                capture_output=True,
                text=True,
                timeout=5
            )
            if result.returncode == 0:
                match = re.search(r'(\d+\.\d+\.\d+\.\d+)', result.stdout)
                if match:
                    return match.group(1)
        except:
            pass
            
        try:
            # Try chromium
            result = subprocess.run(
                ["chromium", "--version"],
                capture_output=True,
                text=True,
                timeout=5
            )
            if result.returncode == 0:
                match = re.search(r'(\d+\.\d+\.\d+\.\d+)', result.stdout)
                if match:
                    return match.group(1)
        except:
            pass
            
        return None
        
    def get_driver_download_url(self, version: str) -> str:
        """Get ChromeDriver download URL"""
        # Chrome for Testing API
        base_url = "https://googlechromelabs.github.io/chrome-for-testing"
        
        # Try to get the exact version
        try:
            url = f"{base_url}/known-good-versions-with-downloads.json"
            with urllib.request.urlopen(url, timeout=10) as response:
                data = json.loads(response.read())
                
            # Find matching version
            for item in data.get('versions', []):
                if item.get('version') == version:
                    downloads = item.get('downloads', {}).get('chromedriver', [])
                    for download in downloads:
                        if download.get('platform') == PLATFORM:
                            return download.get('url')
        except Exception as e:
            print(f"⚠️  Impossible de trouver la version exacte: {e}")
            
        # Fallback: try to construct URL directly
        return f"https://storage.googleapis.com/chrome-for-testing-public/{version}/{PLATFORM}/chromedriver-{PLATFORM}.zip"
        
    def get_driver_filename(self) -> str:
        return "chromedriver"
        
    def extract_driver(self, archive_path: str) -> None:
        """Extract ChromeDriver from zip"""
        with zipfile.ZipFile(archive_path, 'r') as zip_ref:
            # Extract all to current directory
            zip_ref.extractall(".")
            
        # ChromeDriver is in a subdirectory (platform-dependent)
        # Try different possible directory names
        possible_dirs = [
            f"chromedriver-{PLATFORM}",
            "chromedriver-linux64",
            "chromedriver-mac-arm64",
            "chromedriver-mac-x64",
            "chromedriver-win64",
            "chromedriver"
        ]

        driver_name = "chromedriver.exe" if platform.system() == "Windows" else "chromedriver"

        for extracted_dir in possible_dirs:
            if os.path.exists(extracted_dir):
                driver_path = os.path.join(extracted_dir, driver_name)
                if os.path.exists(driver_path):
                    shutil.move(driver_path, driver_name)
                    shutil.rmtree(extracted_dir)
                    return

        # Check if already extracted to the right place
        if os.path.exists(driver_name):
            return

        raise Exception("Could not find extracted chromedriver")


class GeckoDriverUpdater(DriverUpdater):
    """GeckoDriver (Firefox) updater"""
    
    def get_browser_version(self) -> Optional[str]:
        """Get Firefox version"""
        try:
            result = subprocess.run(
                ["firefox", "--version"],
                capture_output=True,
                text=True,
                timeout=5
            )
            if result.returncode == 0:
                match = re.search(r'(\d+\.\d+)', result.stdout)
                if match:
                    return match.group(1)
        except:
            pass
            
        return None
        
    def get_driver_download_url(self, version: str) -> str:
        """Get GeckoDriver download URL"""
        # Get latest release from GitHub API
        api_url = "https://api.github.com/repos/mozilla/geckodriver/releases/latest"
        
        # Map platform names to geckodriver naming
        platform_map = {
            'linux64': 'linux64',
            'linux32': 'linux32',
            'mac-arm64': 'macos-aarch64',
            'mac-x64': 'macos',
            'win64': 'win64',
            'win32': 'win32'
        }

        gecko_platform = platform_map.get(PLATFORM, 'linux64')

        try:
            with urllib.request.urlopen(api_url, timeout=10) as response:
                data = json.loads(response.read())
                
            # Find the appropriate asset for our platform
            for asset in data.get('assets', []):
                name = asset.get('name', '')
                if gecko_platform in name and name.endswith('.tar.gz' if 'linux' in gecko_platform or 'macos' in gecko_platform else '.zip'):
                    return asset.get('browser_download_url')
        except Exception as e:
            print(f"⚠️  Erreur API GitHub: {e}")
            
        # Fallback: construct URL for latest
        extension = '.tar.gz' if PLATFORM.startswith('linux') or PLATFORM.startswith('mac') else '.zip'
        return f"https://github.com/mozilla/geckodriver/releases/latest/download/geckodriver-latest-{gecko_platform}{extension}"

    def get_driver_filename(self) -> str:
        return "geckodriver"
        
    def extract_driver(self, archive_path: str) -> None:
        """Extract GeckoDriver from tar.gz"""
        driver_filename = self.get_driver_filename()
        with tarfile.open(archive_path, 'r:gz') as tar_ref:
            # Safely extract only the geckodriver binary to prevent path traversal
            for member in tar_ref.getmembers():
                # Normalize member name and check for path traversal attempts
                member_name = os.path.normpath(member.name).replace(os.sep, '/')
                if member_name.startswith('../') or member_name.startswith('/'):
                    raise ValueError(f"Path traversal attempt detected: {member.name}")

                # Extract only the geckodriver executable
                if 'geckodriver' in member_name.lower() and not member.isdir():
                    # Extract to current directory with safe name
                    member.name = driver_filename
                    tar_ref.extract(member, path=".", filter='data')
                    break


class EdgeDriverUpdater(DriverUpdater):
    """EdgeDriver (Microsoft Edge) updater"""
    
    def get_browser_version(self) -> Optional[str]:
        """Get Edge version"""
        try:
            result = subprocess.run(
                ["microsoft-edge", "--version"],
                capture_output=True,
                text=True,
                timeout=5
            )
            if result.returncode == 0:
                match = re.search(r'(\d+\.\d+\.\d+\.\d+)', result.stdout)
                if match:
                    return match.group(1)
        except:
            pass
            
        try:
            result = subprocess.run(
                ["microsoft-edge-stable", "--version"],
                capture_output=True,
                text=True,
                timeout=5
            )
            if result.returncode == 0:
                match = re.search(r'(\d+\.\d+\.\d+\.\d+)', result.stdout)
                if match:
                    return match.group(1)
        except:
            pass
            
        return None
        
    def get_driver_download_url(self, version: str) -> str:
        """Get EdgeDriver download URL"""
        # Map platform names to Edge driver naming
        edge_platform_map = {
            'linux64': 'linux64',
            'mac-arm64': 'mac64_m1',
            'mac-x64': 'mac64',
            'win64': 'win64',
            'win32': 'win32'
        }

        edge_platform = edge_platform_map.get(PLATFORM, 'linux64')
        return f"https://msedgedriver.azureedge.net/{version}/edgedriver_{edge_platform}.zip"

    def get_driver_filename(self) -> str:
        return "msedgedriver"
        
    def extract_driver(self, archive_path: str) -> None:
        """Extract EdgeDriver from zip"""
        with zipfile.ZipFile(archive_path, 'r') as zip_ref:
            zip_ref.extractall(".")
            
        # Determine driver name based on platform
        driver_name = "msedgedriver.exe" if platform.system() == "Windows" else "msedgedriver"
        alt_name = "edgedriver.exe" if platform.system() == "Windows" else "edgedriver"

        # Rename if needed
        if os.path.exists(driver_name):
            pass
        elif os.path.exists(alt_name):
            shutil.move(alt_name, driver_name)
        else:
            raise Exception(f"Could not find extracted {driver_name}")


def detect_browsers() -> Dict[str, bool]:
    """Detect which browsers are installed"""
    browsers = {
        'chrome': False,
        'firefox': False,
        'edge': False
    }
    
    # Check Chrome
    try:
        subprocess.run(
            ["google-chrome", "--version"],
            capture_output=True,
            timeout=5
        )
        browsers['chrome'] = True
    except:
        try:
            subprocess.run(
                ["chromium", "--version"],
                capture_output=True,
                timeout=5
            )
            browsers['chrome'] = True
        except:
            pass
            
    # Check Firefox
    try:
        subprocess.run(
            ["firefox", "--version"],
            capture_output=True,
            timeout=5
        )
        browsers['firefox'] = True
    except:
        pass
        
    # Check Edge
    try:
        subprocess.run(
            ["microsoft-edge", "--version"],
            capture_output=True,
            timeout=5
        )
        browsers['edge'] = True
    except:
        try:
            subprocess.run(
                ["microsoft-edge-stable", "--version"],
                capture_output=True,
                timeout=5
            )
            browsers['edge'] = True
        except:
            pass
            
    return browsers


def main():
    parser = argparse.ArgumentParser(
        description="Update WebDriver for Chrome, Firefox, or Edge"
    )
    parser.add_argument(
        'browser',
        nargs='?',
        choices=['chrome', 'firefox', 'edge', 'all'],
        help="Browser driver to update (chrome, firefox, edge, or all)"
    )
    parser.add_argument(
        '--detect',
        action='store_true',
        help="Detect installed browsers and update all their drivers"
    )
    
    args = parser.parse_args()
    
    # Create drivers directory if it doesn't exist
    os.makedirs(DRIVERS_DIR, exist_ok=True)
    
    # Detect browsers if requested or no browser specified
    if args.detect or args.browser is None:
        print("Détection des navigateurs installés...")
        installed = detect_browsers()
        print("\nNavigateurs détectés:")
        for browser, is_installed in installed.items():
            status = "✅" if is_installed else "❌"
            print(f"  {status} {browser.capitalize()}")
        print()
        
        if not args.browser:
            print("Mise à jour de tous les drivers des navigateurs détectés...")
            args.browser = 'all'
            
    # Update drivers
    updaters = {
        'chrome': ChromeDriverUpdater(DRIVERS_DIR),
        'firefox': GeckoDriverUpdater(DRIVERS_DIR),
        'edge': EdgeDriverUpdater(DRIVERS_DIR)
    }
    
    success_count = 0
    total_count = 0
    
    if args.browser == 'all':
        if args.detect:
            # Only update drivers for installed browsers
            for browser_name, is_installed in installed.items():
                if is_installed:
                    total_count += 1
                    if updaters[browser_name].update():
                        success_count += 1
        else:
            # Update all drivers
            for browser_name, updater in updaters.items():
                total_count += 1
                if updater.update():
                    success_count += 1
    else:
        total_count = 1
        if updaters[args.browser].update():
            success_count += 1
            
    # Summary
    print("\n" + "=" * 60)
    print("RÉSUMÉ")
    print("=" * 60)
    print(f"Drivers mis à jour avec succès: {success_count}/{total_count}")
    print("=" * 60 + "\n")
    
    return 0 if success_count == total_count else 1


if __name__ == "__main__":
    sys.exit(main())
