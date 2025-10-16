# TYPO3 Official Documentation Setup

This document explains how to get this extension's documentation listed on the official TYPO3 documentation platform at `docs.typo3.org`.

## Prerequisites

✅ **Extension is published in TER** - The extension `rte_ckeditor_image` is already published in the TYPO3 Extension Repository
✅ **Repository is referenced on TER** - GitHub repository is linked on the [TER detail page](https://extensions.typo3.org/extension/rte_ckeditor_image)
✅ **Documentation structure exists** - `Documentation/guides.xml` is configured with correct `interlink-shortcode="netresearch/rte-ckeditor-image"`

## Webhook Setup (Repository Owner Action Required)

To enable automatic documentation rendering on `docs.typo3.org`, a webhook must be configured in the GitHub repository settings.

### Step 1: Add Webhook to GitHub

1. Go to **Settings** → **Webhooks** → **Add webhook**
2. Configure the webhook:

   ```
   Payload URL: https://docs-hook.typo3.org
   Content type: application/json
   SSL verification: Enable SSL verification (recommended)
   ```

3. Select trigger events:
   - ✅ **Push events** (triggers on branch pushes)
   - ✅ **Tag push events** (triggers on release tags - optional but recommended)

4. **Active:** ✅ (ensure webhook is enabled)
5. Click **Add webhook**

### Step 2: Request Documentation Team Approval

Before the documentation will render, the TYPO3 Documentation Team must approve the repository.

Contact the TYPO3 Documentation Team:
- **Slack:** [TYPO3 Slack](https://typo3.org/community/meet/chat-slack) - #typo3-documentation channel
- **Email:** documentation@typo3.org (if available)
- **Reference:** Provide the extension key `rte_ckeditor_image` and repository URL

## Testing Documentation Rendering

Once the webhook is configured and approved, test it by:

1. **Push to main branch:**
   ```bash
   git push origin main
   ```

2. **Monitor deployment:**
   - Visit [intercept.typo3.com/admin/docs/deployments](https://intercept.typo3.com/admin/docs/deployments)
   - Look for your repository in the deployment queue

3. **Check rendered documentation:**
   - Main/Master branch: `https://docs.typo3.org/p/netresearch/rte-ckeditor-image/main/en-us/`
   - Released versions: `https://docs.typo3.org/p/netresearch/rte-ckeditor-image/13.0/en-us/`

## Local Documentation Rendering

### Using Docker

Render documentation locally to preview changes before pushing:

```bash
# Full render (recommended)
docker run --rm -v "$(pwd)":/project ghcr.io/typo3-documentation/render-guides:latest --config=Documentation

# View output
open Documentation-GENERATED-temp/Index.html
```

### Using Makefile

A Makefile is provided for convenience:

```bash
# Generate documentation
make docs

# Clean generated files
make clean-docs
```

## Documentation Structure

The documentation follows TYPO3 standards:

```
Documentation/
├── guides.xml              # Main configuration (interlink-shortcode, project metadata)
├── Index.md                # Entry point with table of contents
├── README.md               # Installation quickstart
├── API/                    # Developer API documentation
├── Architecture/           # System architecture
├── CKEditor/               # CKEditor plugin development
├── Configuration/          # Integration configuration
├── Examples/               # Usage examples
├── Integration/            # Integration guides
└── Troubleshooting/        # Common issues and solutions
```

## Version Management

TYPO3 docs platform displays only `Major.Minor` versions (e.g., `13.0`, `13.1`) and omits patch levels to reduce documentation volume.

**Supported Branches:**
- `main` / `master` → Development version at `/main/en-us/`
- `documentation-draft` → Draft preview at `/draft/en-us/` (excluded from search)
- Tagged releases → Versioned docs at `/{Major.Minor}/en-us/`

## Known Issues & Improvements

### Cross-Reference Warnings

The current documentation renders successfully but shows warnings about unresolved cross-references:

```
WARNING: Reference Integration/Configuration.md#custom-image-styles could not be resolved
```

**Cause:** Inline Markdown links use `.md` extension, but MyST cross-references should omit it.

**Fix:** Update links from:
```markdown
[Configuration Guide](Integration/Configuration.md#custom-image-styles)
```

To:
```markdown
[Configuration Guide](Integration/Configuration#custom-image-styles)
```

This is a **cosmetic issue** and does not prevent rendering. Can be fixed in a future update.

## Continuous Integration

Documentation validation is recommended in CI pipelines:

```yaml
# Example .github/workflows/docs.yml
name: Documentation

on: [push, pull_request]

jobs:
  validate-docs:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Render Documentation
        run: |
          docker run --rm -v "$PWD":/project \
            ghcr.io/typo3-documentation/render-guides:latest \
            --config=Documentation

      - name: Check for errors
        run: |
          if grep -q "ERROR" Documentation-GENERATED-temp/warnings.log 2>/dev/null; then
            echo "Documentation has errors"
            exit 1
          fi
```

## Migration from Old Format

If migrating from legacy Sphinx documentation (`Settings.cfg`), use the migration tool:

```bash
docker run --rm --pull always \
  -v "$(pwd)":/project \
  -it ghcr.io/typo3-documentation/render-guides:latest \
  migrate Documentation
```

This is **not needed** for this extension - `guides.xml` is already properly configured.

## Support

**TYPO3 Documentation:**
- Official Guide: [How to Document](https://docs.typo3.org/m/typo3/docs-how-to-document/main/en-us/)
- Migration Guide: [Sphinx to PHP-Based Rendering](https://docs.typo3.org/m/typo3/docs-how-to-document/main/en-us/Howto/Migration/Index.html)
- Extension Documentation: [Writing Docs for Extensions](https://docs.typo3.org/m/typo3/docs-how-to-document/main/en-us/Howto/WritingDocForExtension/Index.html)

**Get Help:**
- TYPO3 Slack: #typo3-documentation
- GitHub Issues: Report documentation-specific issues with `[DOCS]` tag

## Checklist for Repository Owner

- [ ] Add webhook to GitHub repository (Settings → Webhooks)
- [ ] Configure webhook URL: `https://docs-hook.typo3.org`
- [ ] Enable SSL verification
- [ ] Select "Push events" trigger
- [ ] Contact TYPO3 Documentation Team for approval
- [ ] Test with a push to main branch
- [ ] Monitor deployment at intercept.typo3.com
- [ ] Verify documentation appears at docs.typo3.org
- [ ] (Optional) Add documentation CI validation
- [ ] (Optional) Fix cross-reference warnings

## Timeline

Once webhook is configured and approved by TYPO3 Documentation Team:
- **First render:** Within minutes of next push
- **Approval process:** Typically 1-3 business days
- **Automatic updates:** Every push to main/master or tagged releases

---

**Status:** Documentation structure is ready. Webhook configuration and team approval pending.
