# Local Application Tools

This directory contains Python tools for interacting with the Portuga web backend.

## CSV Product Uploader

The `upload_products.py` script uploads product catalog CSV files to the web backend for import into the database.

### Installation

Install required Python packages:

```bash
pip install requests
```

### Configuration

Create a `config.json` file in the `localapp` directory (copy from `config.example.json`):

```json
{
  "endpoint_url": "https://yoursite.com/api/import_products.php",
  "api_key": "your-secret-api-key-here"
}
```

**Configuration options:**

- `endpoint_url` - URL of the import endpoint on your server
- `api_key` - API key for authentication (optional, but required if IMPORT_API_KEY is set on server)

**Alternative: Environment Variables**

You can also configure using environment variables:

```bash
export UPLOAD_ENDPOINT="https://yoursite.com/api/import_products.php"
export IMPORT_API_KEY="your-secret-api-key-here"
```

### Usage

Upload a CSV file:

```bash
python localapp/upload_products.py path/to/PRODUTOS.csv
```

The script will:
1. Read configuration from `config.json` or environment variables
2. Upload the CSV file to the endpoint
3. Display the import results (inserted, updated, skipped items)

### CSV Format

The CSV file should have the following columns (Portuguese or English names accepted):

| Column | Alternative Names | Description |
|--------|------------------|-------------|
| # | Código, Code | Product PDV code (optional) |
| Descrição | Description, Nome, Name | Product name (required) |
| Grupo | Group, Categoria, Category | Product group/category (default: "Geral") |
| Custo | Cost | Product cost price |
| Venda | Preço, Price | Product sale price (required) |
| Ativo | Active, Disponível | Availability (Sim/Yes/1 = active) |

**Price format:** Brazilian format is supported: `R$ 1.234,56` will be converted to `1234.56`

### Example CSV

```csv
#,Descrição,Grupo,Custo,Venda,Ativo
1,Pizza Margherita,Pizzas Salgadas,R$ 15,00,R$ 35,00,Sim
2,Pizza Calabresa,Pizzas Salgadas,R$ 16,50,R$ 38,00,Sim
3,Coca-Cola 2L,Bebidas,R$ 5,00,R$ 10,00,Sim
```

## Manual Testing with curl

You can also test the endpoint manually using curl:

### Without API Key

```bash
curl -X POST \
  -F "file=@path/to/PRODUTOS.csv" \
  https://yoursite.com/api/import_products.php
```

### With API Key (Header)

```bash
curl -X POST \
  -H "IMPORT_API_KEY: your-secret-api-key-here" \
  -F "file=@path/to/PRODUTOS.csv" \
  https://yoursite.com/api/import_products.php
```

### With API Key (POST parameter)

```bash
curl -X POST \
  -F "file=@path/to/PRODUTOS.csv" \
  -F "api_key=your-secret-api-key-here" \
  https://yoursite.com/api/import_products.php
```

### Expected Response

```json
{
  "success": true,
  "imported": 45,
  "updated": 12,
  "skipped": 0,
  "total_rows": 57,
  "errors": [],
  "details_url": null
}
```

## Server Configuration

On the server side, set the `IMPORT_API_KEY` environment variable to enable API key authentication:

```bash
# In .env file
IMPORT_API_KEY=your-secret-api-key-here
```

If `IMPORT_API_KEY` is not set, the endpoint will accept requests without authentication (not recommended for production).

## Logging

Import operations are logged to `logs/import_products.log` on the server for debugging and audit purposes.

## Notes

- The import endpoint uses database transactions, so if an error occurs, all changes are rolled back
- Existing products are matched by `pdv_code` (if provided) or by `name` + `group_id`
- Images are intentionally not supported by this import endpoint
- The cost column is added to the database for profit margin calculations
