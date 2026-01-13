#!/usr/bin/env python3
"""
CSV Product Uploader

Uploads a CSV file to the Portuga import endpoint.

Usage:
    python upload_products.py path/to/PRODUTOS.csv

Configuration:
    - Load from localapp/config.json: endpoint_url, api_key
    - Or use environment variables: UPLOAD_ENDPOINT, IMPORT_API_KEY
"""

import os
import sys
import json
import requests
from pathlib import Path


def load_config():
    """Load configuration from config.json or environment variables"""
    config = {
        'endpoint_url': None,
        'api_key': None
    }
    
    # Try to load from config.json
    config_path = Path(__file__).parent / 'config.json'
    if config_path.exists():
        try:
            with open(config_path, 'r', encoding='utf-8') as f:
                file_config = json.load(f)
                config['endpoint_url'] = file_config.get('endpoint_url')
                config['api_key'] = file_config.get('api_key')
        except Exception as e:
            print(f"Warning: Could not load config.json: {e}")
    
    # Override with environment variables if present
    if os.getenv('UPLOAD_ENDPOINT'):
        config['endpoint_url'] = os.getenv('UPLOAD_ENDPOINT')
    if os.getenv('IMPORT_API_KEY'):
        config['api_key'] = os.getenv('IMPORT_API_KEY')
    
    return config


def upload_csv(csv_path, endpoint_url, api_key=None):
    """
    Upload CSV file to the import endpoint
    
    Args:
        csv_path: Path to CSV file
        endpoint_url: URL of the import endpoint
        api_key: Optional API key for authentication
        
    Returns:
        dict: Response from the server
    """
    if not os.path.exists(csv_path):
        raise FileNotFoundError(f"CSV file not found: {csv_path}")
    
    # Prepare headers
    headers = {}
    if api_key:
        headers['IMPORT_API_KEY'] = api_key
    
    # Prepare files for multipart upload
    with open(csv_path, 'rb') as f:
        files = {'file': (os.path.basename(csv_path), f, 'text/csv')}
        
        # Make POST request
        print(f"Uploading {csv_path} to {endpoint_url}...")
        response = requests.post(
            endpoint_url,
            files=files,
            headers=headers,
            timeout=300  # 5 minutes timeout for large files
        )
    
    # Check response
    response.raise_for_status()
    
    return response.json()


def main():
    """Main entry point"""
    if len(sys.argv) < 2:
        print("Usage: python upload_products.py path/to/PRODUTOS.csv")
        print("\nConfiguration:")
        print("  Set endpoint_url and api_key in localapp/config.json")
        print("  Or use environment variables:")
        print("    UPLOAD_ENDPOINT - URL of the import endpoint")
        print("    IMPORT_API_KEY  - API key for authentication")
        sys.exit(1)
    
    csv_path = sys.argv[1]
    
    # Load configuration
    config = load_config()
    
    if not config['endpoint_url']:
        print("Error: endpoint_url not configured")
        print("Set it in localapp/config.json or UPLOAD_ENDPOINT environment variable")
        sys.exit(1)
    
    try:
        # Upload CSV
        result = upload_csv(csv_path, config['endpoint_url'], config['api_key'])
        
        # Print result
        print("\n" + "="*60)
        print("IMPORT RESULT")
        print("="*60)
        print(json.dumps(result, indent=2, ensure_ascii=False))
        print("="*60)
        
        # Check for errors
        if result.get('success') is False:
            print("\n❌ Import failed!")
            sys.exit(1)
        
        if result.get('errors'):
            print(f"\n⚠️  Import completed with {len(result['errors'])} errors")
            sys.exit(1)
        
        print("\n✅ Import completed successfully!")
        print(f"   Inserted: {result.get('imported', 0)}")
        print(f"   Updated:  {result.get('updated', 0)}")
        print(f"   Skipped:  {result.get('skipped', 0)}")
        
    except requests.exceptions.HTTPError as e:
        print(f"\n❌ HTTP Error: {e}")
        if e.response:
            try:
                error_data = e.response.json()
                print(json.dumps(error_data, indent=2, ensure_ascii=False))
            except (json.JSONDecodeError, ValueError):
                print(e.response.text)
        sys.exit(1)
    except Exception as e:
        print(f"\n❌ Error: {e}")
        sys.exit(1)


if __name__ == '__main__':
    main()
