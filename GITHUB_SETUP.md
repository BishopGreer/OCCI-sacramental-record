# OCCI Sacramental Records — GitHub Setup

## One-Time Repository Setup

Run these commands on your local machine (requires Git installed):

```bash
# 1. Clone your empty repository
git clone https://github.com/YOUR-USERNAME/OCCI-sacramental-record.git
cd OCCI-sacramental-record

# 2. Copy all plugin files into this folder
#    (copy the contents of the occi-sacramental-records/ folder here)

# 3. Initial commit and push
git add .
git commit -m "Initial release: OCCI Sacramental Records v1.0.4"
git push origin main

# 4. Tag the current version and push the tag
git tag v1.0.4
git push origin v1.0.4
```

Pushing the tag triggers GitHub Actions to automatically:
- Build the versioned ZIP file
- Create a GitHub Release
- Attach the ZIP as a downloadable release asset

## Releasing Future Versions

After making changes:

```bash
git add .
git commit -m "Version 1.0.5: describe what changed"
git push origin main

git tag v1.0.5
git push origin v1.0.5
```

That is all. GitHub Actions handles the rest.

## Configuring Automatic Updates on WordPress Sites

After the first release is published, add this to wp-config.php on each WordPress site:

```php
// GitHub releases (public repository — no token needed)
define( 'OCCI_UPDATE_URL',    'https://github.com/YOUR-USERNAME/OCCI-sacramental-record' );
define( 'OCCI_UPDATE_SOURCE', 'github' );
```

The plugin checks for updates every 12 hours. Updates will appear in
WordPress Admin → Plugins → Updates exactly like any other plugin.

## Self-Hosted Update JSON (Alternative)

If you prefer self-hosted updates on myocci.org:

1. After each release, upload the ZIP to: `https://myocci.org/updates/`
2. Update `https://myocci.org/updates/occi-sacramental-records.json` with:

```json
{
  "version":      "1.0.5",
  "download_url": "https://myocci.org/updates/occi-sacramental-records-1.0.5.zip",
  "requires":     "6.0",
  "requires_php": "8.0",
  "tested":       "6.8",
  "last_updated": "2026-05-05",
  "changelog":    "<h4>1.0.5</h4><ul><li>What changed</li></ul>"
}
```

Then in wp-config.php:

```php
define( 'OCCI_UPDATE_URL', 'https://myocci.org/updates/occi-sacramental-records.json' );
// OCCI_UPDATE_SOURCE defaults to 'self_hosted' — no need to define it
```

## Repository Structure

```
OCCI-sacramental-record/
├── .github/
│   └── workflows/
│       └── release.yml          ← Auto-builds releases on version tags
├── .gitignore
├── GITHUB_SETUP.md              ← This file
├── occi-sacramental-records.php ← Main plugin file
├── readme.txt                   ← WordPress plugin readme with changelog
├── includes/                    ← PHP class files
├── admin/                       ← CSS and JS assets
└── assets/
    └── images/
        └── certificate-template.png
```

Pax et Bonum.
