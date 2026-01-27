# FAL Photo Browser

A TYPO3 extension for searching and importing stock photos directly in the TYPO3 backend.

**Powered by [Unsplash](https://unsplash.com)**

## Features

- **Backend Module**: Dedicated module under "Media" for browsing and importing photos
- **Advanced Search**: Filter by text, orientation (landscape, portrait, square), and color
- **Local Import**: Downloads images to your fileadmin for GDPR compliance and performance
- **Automatic Metadata**: Populates title, description, alt text, copyright, and photographer info
- **Download Tracking**: Complies with Unsplash API guidelines by tracking downloads
- **Custom Metadata Fields**: Stores Unsplash photo ID, URL, and photographer URL for reference

## Requirements

- TYPO3 v14.0+
- PHP 8.2+
- Unsplash API Access Key ([Get one here](https://unsplash.com/developers))

## Installation

### Via Composer (recommended)

```bash
composer require dkd-dobberkau/fal-photo-browser
```

### Manual Installation

1. Download the extension
2. Place it in `packages/fal_photo_browser/`
3. Add the path repository to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "packages/*"
        }
    ]
}
```

4. Run `composer require dkd-dobberkau/fal-photo-browser:@dev`

### Activate Extension

```bash
vendor/bin/typo3 extension:setup --extension=fal_photo_browser
```

## Configuration

### API Key Setup

Set the `UNSPLASH_ACCESS_KEY` environment variable with your Unsplash API key.

#### DDEV

Add to `.ddev/config.yaml`:

```yaml
web_environment:
  - UNSPLASH_ACCESS_KEY=your_access_key_here
```

#### Apache/Nginx

```apache
SetEnv UNSPLASH_ACCESS_KEY your_access_key_here
```

```nginx
fastcgi_param UNSPLASH_ACCESS_KEY your_access_key_here;
```

#### Docker

```yaml
environment:
  - UNSPLASH_ACCESS_KEY=your_access_key_here
```

### Content Security Policy

The extension automatically registers CSP rules to allow loading thumbnail images from `images.unsplash.com` in the backend.

## Usage

1. Navigate to **Media > Photo Browser** in the TYPO3 backend
2. Enter a search term (e.g., "mountains", "office", "technology")
3. Optionally filter by orientation or color
4. Click **Import** on any photo to download it

Imported images are stored in `fileadmin/unsplash/YYYY/MM/` with full metadata including:

- Title and description
- Alt text
- Photographer name and URL
- Copyright notice
- Original Unsplash URL

## File Structure

```
fal_photo_browser/
├── Classes/
│   ├── Controller/
│   │   └── PhotoBrowserController.php
│   ├── Domain/
│   │   └── Dto/
│   │       └── UnsplashPhoto.php
│   └── Service/
│       ├── FileImportService.php
│       └── UnsplashApiService.php
├── Configuration/
│   ├── Backend/
│   │   ├── AjaxRoutes.php
│   │   └── Modules.php
│   ├── ContentSecurityPolicies.php
│   ├── Icons.php
│   ├── Services.yaml
│   └── TCA/
│       └── Overrides/
│           └── sys_file_metadata.php
├── Resources/
│   ├── Private/
│   │   ├── Language/
│   │   │   └── locallang_mod.xlf
│   │   └── Templates/
│   │       └── Backend/
│   │           └── Index.html
│   └── Public/
│       ├── Css/
│       │   └── backend.css
│       ├── Icons/
│       │   └── module-falphotobrowser.svg
│       └── JavaScript/
│           └── photo-browser.js
├── composer.json
├── ext_emconf.php
└── ext_tables.sql
```

## Metadata Fields

The extension adds custom fields to `sys_file_metadata`:

| Field | Description |
|-------|-------------|
| `unsplash_photo_id` | Original Unsplash photo ID |
| `unsplash_photo_url` | Link to photo on Unsplash |
| `unsplash_photographer_url` | Link to photographer's profile |

These fields appear in a dedicated "Unsplash" tab when editing file metadata.

## API Compliance

This extension follows [Unsplash API Guidelines](https://help.unsplash.com/en/articles/2511245-unsplash-api-guidelines):

- Downloads are tracked via the API (required)
- Photographer attribution is stored in metadata
- "Powered by Unsplash" attribution in the UI
- Images are hotlink-free (downloaded locally)

## License

GPL-2.0-or-later

## Credits

- Photos provided by [Unsplash](https://unsplash.com)
- Built for TYPO3 CMS
