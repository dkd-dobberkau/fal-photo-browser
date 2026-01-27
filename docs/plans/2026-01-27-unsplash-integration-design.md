# TYPO3 Unsplash Integration – Design

## Übersicht

**Extension:** `t3_unsplash`
**Kompatibilität:** TYPO3 v14
**Zweck:** Unsplash-Bilder direkt im TYPO3 Backend suchen und importieren

## Kernfunktionen

| Feature | Beschreibung |
|---------|--------------|
| FAL-Driver | Unsplash als virtuelles File Storage für importierte Bilder |
| Bildauswahl-Modal | "Unsplash"-Button im Standard-Bildauswahl-Dialog |
| Backend-Modul | Eigenständiges Modul unter "Datei" zum Suchen und Importieren |
| Lokaler Download | Bilder werden in fileadmin gespeichert (DSGVO-konform) |
| Erweiterte Suche | Text, Orientierung, Farbe, Collections |

## Konfiguration

API-Key via Environment-Variable:

```bash
UNSPLASH_ACCESS_KEY=your_access_key_here
```

## Dateistruktur

```
t3_unsplash/
├── Classes/
│   ├── Controller/
│   │   └── UnsplashController.php      # Backend-Modul Controller
│   ├── Driver/
│   │   └── UnsplashDriver.php          # FAL-Driver
│   ├── Service/
│   │   └── UnsplashApiService.php      # API-Kommunikation
│   ├── EventListener/
│   │   └── ModifyFileBrowserEvent.php  # Modal-Integration
│   └── Domain/
│       └── Dto/
│           └── UnsplashPhoto.php       # Daten-Objekt für Fotos
├── Configuration/
│   ├── Backend/
│   │   ├── Modules.php                 # Backend-Modul Registrierung
│   │   └── AjaxRoutes.php              # AJAX-Endpunkte für Suche
│   └── Services.yaml                   # Dependency Injection
├── Resources/
│   ├── Private/
│   │   └── Templates/Backend/          # Fluid-Templates fürs Modul
│   └── Public/
│       ├── JavaScript/
│       │   └── unsplash-browser.js     # Such-UI
│       └── Css/
│           └── backend.css             # Styling
├── composer.json
└── ext_emconf.php
```

## UnsplashApiService

Zentrale Klasse für alle Unsplash-API-Aufrufe.

```php
class UnsplashApiService
{
    private const API_BASE = 'https://api.unsplash.com/';

    public function search(
        string $query,
        int $page = 1,
        int $perPage = 30,
        ?string $orientation = null,  // landscape, portrait, squarish
        ?string $color = null         // black_and_white, black, white, yellow, etc.
    ): array;

    public function getCollections(): array;

    public function getCollectionPhotos(string $collectionId): array;

    public function downloadPhoto(string $photoId, string $targetFolder): string;
}
```

## Datenfluss beim Import

1. Redakteur sucht → API-Request an Unsplash
2. Ergebnisse anzeigen → Vorschau-URLs (klein, von Unsplash CDN)
3. Bild auswählen → `downloadPhoto()` aufrufen
4. Download triggern → Unsplash API (tracking-konform)
5. Speichern → `fileadmin/unsplash/{year}/{month}/{filename}.jpg`
6. FAL-Referenz erstellen → sys_file + sys_file_metadata
7. Metadaten übernehmen → Fotograf, Beschreibung, Unsplash-URL

## Backend-Modul

Unter **Datei → Unsplash** erreichbar.

**Features:**
- Suchfeld mit Live-Suche (debounced)
- Filter-Dropdowns: Orientierung, Farbe, Collection
- Ergebnis-Grid mit Lazy Loading
- Klick auf Bild → Import-Dialog mit Vorschau, Fotograf-Info, Zielordner-Auswahl
- Import-Button → Download + FAL-Eintrag erstellen

## Modal im Bildauswahl-Dialog

Integration via TYPO3 Event oder JavaScript-Hook.

**Ablauf:**
1. Redakteur öffnet Bildauswahl (z.B. bei `media`-Feld)
2. Zusätzlicher Tab/Button "Unsplash" erscheint
3. Klick öffnet Such-Modal (gleiche UI wie Backend-Modul)
4. Nach Import wird das Bild automatisch ausgewählt

## FAL-Driver

Read-only Driver für importierte Unsplash-Bilder. Kein direktes Browsen der Unsplash-API im FAL-Tree, sondern Verweis auf das Modal für neue Bilder.

## Metadaten & Attribution

Beim Import werden automatisch übernommen:

| Unsplash-Feld | TYPO3 sys_file_metadata |
|---------------|-------------------------|
| `description` | `description` |
| `user.name` | `creator` |
| `user.links.html` | `unsplash_photographer_url` (custom) |
| `links.html` | `unsplash_photo_url` (custom) |
| `created_at` | `content_creation_date` |

Attribution-Helper für Frontend:

```html
<f:format.html>{file.properties.unsplash_attribution}</f:format.html>
<!-- Ausgabe: Foto von <a href="...">Max Mustermann</a> auf <a href="...">Unsplash</a> -->
```

## Fehlerbehandlung

| Situation | Reaktion |
|-----------|----------|
| Kein API-Key | Warnung im Backend-Modul, Button deaktiviert |
| API-Limit erreicht | Flash-Message mit Info, Retry-After anzeigen |
| Download fehlgeschlagen | Fehlermeldung, kein FAL-Eintrag |
| Bild existiert bereits | Bestehende Datei verwenden (Hash-Vergleich) |
