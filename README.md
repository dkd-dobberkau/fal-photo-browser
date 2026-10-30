# FAL Photo Browser

A TYPO3 extension for searching and importing stock photos directly in the TYPO3 backend.

**Powered by [Unsplash](https://unsplash.com)**

![Photo search results in the TYPO3 backend](https://raw.githubusercontent.com/dkd-dobberkau/fal-photo-browser/main/Resources/Public/Screenshots/photo-search.jpg)

## Features

- **Backend Module**: Dedicated module under "Media" for browsing and importing photos
- **Advanced Search**: Filter by text, orientation (landscape, portrait, square), and color
- **Local Import**: Downloads images to your fileadmin for GDPR compliance and performance
- **Automatic Metadata**: Populates title, description, and alt text, plus photographer and copyright info if `EXT:filemetadata` is installed
- **Download Tracking**: Complies with Unsplash API guidelines by tracking downloads
- **Custom Metadata Fields**: Stores Unsplash photo ID, URL, and photographer URL for reference
- **AI Photo Assistant (optional)**: Describe the photo you need in your own words and get matching search terms and filters

## Requirements

- TYPO3 v14.0+
- PHP 8.2+
- Unsplash API Access Key ([Get one here](https://unsplash.com/developers))
- Anthropic API key for the optional Photo Assistant ([Get one here](https://console.anthropic.com/))

## Installation

### Via Composer (recommended)

```bash
composer require dkd-dobberkau/fal-photo-browser
```

### Manual Installation

1. Download the extension
2. Place it in `packages/dkd_fal_photo_browser/`
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
vendor/bin/typo3 extension:setup --extension=dkd_fal_photo_browser
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

### Photo Assistant (optional)

The Photo Assistant uses the Claude API. To enable it, set the `ANTHROPIC_API_KEY` environment variable the same way as `UNSPLASH_ACCESS_KEY` above:

```yaml
web_environment:
  - ANTHROPIC_API_KEY=your_api_key_here
```

Photo search and import work without this key.

### Content Security Policy

The extension automatically registers CSP rules to allow loading thumbnail images from `images.unsplash.com` in the backend.

## Usage

1. Navigate to **Media > Photo Browser** in the TYPO3 backend
2. Enter a search term (e.g., "mountains", "office", "technology")
3. Optionally filter by orientation or color
4. Click **Import** on any photo to download it

### Photo Assistant

If the Photo Assistant is enabled, click **Photo Assistant** and describe what you need instead of searching by keyword, for example "a cozy café in the morning". The assistant replies in your language, creates English search terms, suggests orientation and color filters, and offers follow-up ideas to refine the search.

![The Photo Assistant suggesting search terms and filters](https://raw.githubusercontent.com/dkd-dobberkau/fal-photo-browser/main/Resources/Public/Screenshots/photo-assistant.jpg)

### Imported Images

Imported images are stored in `fileadmin/unsplash/YYYY/MM/` with full metadata including:

- Title and description
- Alt text
- Photographer URL
- Original Unsplash URL

The photographer name, copyright notice, and source are only stored if the TYPO3 system extension `EXT:filemetadata` (`typo3/cms-filemetadata`) is installed, because it provides these fields.

## File Structure

```
dkd_fal_photo_browser/
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

![The Unsplash tab in the file metadata form](https://raw.githubusercontent.com/dkd-dobberkau/fal-photo-browser/main/Resources/Public/Screenshots/metadata-unsplash-tab.jpg)

## API Compliance

This extension follows [Unsplash API Guidelines](https://help.unsplash.com/en/articles/2511245-unsplash-api-guidelines):

- Downloads are tracked via the API (required)
- Photographer attribution is stored in metadata (the photographer URL, and the name if `EXT:filemetadata` is installed)
- "Powered by Unsplash" attribution in the UI
- Images are hotlink-free (downloaded locally)

## Data Protection

The Photo Assistant sends the messages you type, including the earlier messages of the conversation, to the [Anthropic API](https://www.anthropic.com/). The search terms it creates are sent to the Unsplash API. Do not enter personal or confidential data in the chat, and check whether your organization needs a data processing agreement before you enable it.

Without `ANTHROPIC_API_KEY`, no data is sent to Anthropic.

## Support

Please report bugs and feature requests in the [GitHub issue tracker](https://github.com/dkd-dobberkau/fal-photo-browser/issues).

## License

GPL-2.0-or-later

## Credits

- Photos provided by [Unsplash](https://unsplash.com)
- Photo Assistant powered by [Claude](https://www.anthropic.com/claude)
- Built for TYPO3 CMS
