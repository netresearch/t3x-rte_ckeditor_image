import { test, expect } from '@playwright/test';

/**
 * Documentation screenshots for the image reference validation feature.
 *
 * Renders styled HTML mockups and takes element screenshots.
 * NOT included in CI — run manually when updating documentation.
 *
 * Run with: Build/Scripts/runTests.sh -s e2e -- tests/doc-screenshots.spec.ts
 * Screenshots saved to: Documentation/Images/
 */

test.describe('Documentation Screenshots', () => {
    test('capture CLI command output screenshot', async ({ page }) => {
        const terminalHtml = `<!DOCTYPE html>
<html>
<head><style>
body { margin: 0; padding: 20px; background: #1e1e2e; font-family: 'JetBrains Mono', 'Fira Code', 'Cascadia Code', monospace; }
.terminal {
    background: #1e1e2e;
    border-radius: 8px;
    padding: 0;
    max-width: 900px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.4);
    overflow: hidden;
}
.titlebar {
    background: #313244;
    padding: 8px 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.dot { width: 12px; height: 12px; border-radius: 50%; }
.dot-red { background: #f38ba8; }
.dot-yellow { background: #f9e2af; }
.dot-green { background: #a6e3a1; }
.titlebar-text { color: #6c7086; font-size: 13px; margin-left: 8px; }
.content { padding: 16px 20px; color: #cdd6f4; font-size: 14px; line-height: 1.6; }
.prompt { color: #89b4fa; }
.cmd { color: #a6e3a1; }
.title { color: #cdd6f4; font-weight: bold; font-size: 16px; display: block; margin: 12px 0 8px; text-decoration: underline; text-underline-offset: 4px; }
.dim { color: #6c7086; }
.yellow { color: #f9e2af; }
.green { color: #a6e3a1; }
.red { color: #f38ba8; }
.cyan { color: #89dceb; }
.white { color: #cdd6f4; }
table { border-collapse: collapse; margin: 8px 0; }
td { padding: 2px 16px 2px 0; color: #cdd6f4; white-space: nowrap; }
td.type { color: #f9e2af; }
td.fixable-yes { color: #a6e3a1; }
td.fixable-no { color: #f38ba8; }
.sep { color: #45475a; }
.summary td:first-child { color: #6c7086; }
.summary td:last-child { color: #cdd6f4; }
.note { color: #89b4fa; background: rgba(137, 180, 250, 0.1); padding: 6px 12px; border-radius: 4px; margin: 8px 0; display: inline-block; }
</style></head>
<body>
<div class="terminal">
    <div class="titlebar">
        <span class="dot dot-red"></span>
        <span class="dot dot-yellow"></span>
        <span class="dot dot-green"></span>
        <span class="titlebar-text">bash &mdash; bin/typo3 rte_ckeditor_image:validate</span>
    </div>
    <div class="content">
        <span class="prompt">$</span> <span class="cmd">bin/typo3 rte_ckeditor_image:validate</span>
        <br><br>
        <span class="title">RTE Image Reference Validation</span>
        <br>
        <table class="summary">
            <tr><td>Scanned records</td><td>142</td></tr>
            <tr><td>Scanned images</td><td>287</td></tr>
            <tr><td>Issues found</td><td>5</td></tr>
            <tr><td>Affected records</td><td>4</td></tr>
        </table>
        <br>
        <span class="white" style="font-weight:bold">Issues</span><br>
        <span class="sep">-------- ----------- ----- ---------- --------- ------------------------------------------ ----------------------------------------- --------</span><br>
        <table>
            <tr><td class="dim">Type</td><td class="dim">Table</td><td class="dim">UID</td><td class="dim">Field</td><td class="dim">File UID</td><td class="dim">Current src</td><td class="dim">Expected src</td><td class="dim">Fixable</td></tr>
            <tr><td class="type">processed_image_src</td><td>tt_content</td><td>42</td><td>bodytext</td><td>15</td><td>/fileadmin/_processed_/a/b/csm_photo_abc...</td><td>/fileadmin/photos/team.jpg</td><td class="fixable-yes">yes</td></tr>
            <tr><td class="type">src_mismatch</td><td>tt_content</td><td>87</td><td>bodytext</td><td>23</td><td>/fileadmin/old-path/banner.jpg</td><td>/fileadmin/images/banner.jpg</td><td class="fixable-yes">yes</td></tr>
            <tr><td class="type">broken_src</td><td>tt_content</td><td>93</td><td>bodytext</td><td>8</td><td>-</td><td>/fileadmin/logos/company.png</td><td class="fixable-yes">yes</td></tr>
            <tr><td class="type">orphaned_file_uid</td><td>tt_content</td><td>105</td><td>bodytext</td><td>999</td><td>/fileadmin/deleted-image.jpg</td><td>-</td><td class="fixable-yes">yes</td></tr>
            <tr><td class="type">missing_file_uid</td><td>tt_content</td><td>112</td><td>bodytext</td><td>-</td><td>/fileadmin/legacy/photo.jpg</td><td>-</td><td class="fixable-no">no</td></tr>
        </table>
        <br>
        <span class="note">! [NOTE] Dry-run mode. 4 fixable issue(s) found. Use --fix to apply corrections.</span>
    </div>
</div>
</body>
</html>`;

        await page.setContent(terminalHtml);
        await page.waitForTimeout(500);

        const terminal = page.locator('.terminal');
        await terminal.screenshot({
            path: 'Documentation/Images/cli-validate-references.png',
        });

        expect(await terminal.isVisible()).toBe(true);
    });

    test('capture upgrade wizard screenshot', async ({ page }) => {
        // Render a TYPO3-styled upgrade wizard panel as HTML
        // (The E2E environment does not include the Install Tool modules,
        // so we render a faithful mockup of the upgrade wizard UI)
        const wizardHtml = `<!DOCTYPE html>
<html>
<head>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body {
    margin: 0; padding: 24px;
    background: #f5f5f5;
    font-family: 'Source Sans 3', 'Source Sans Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 14px;
    color: #212121;
}
.wizard-panel {
    max-width: 820px;
    background: #fff;
    border-radius: 6px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.06);
    overflow: hidden;
}
.panel-header {
    background: #fff;
    padding: 16px 20px;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    align-items: center;
    gap: 12px;
}
.panel-header .icon {
    width: 32px; height: 32px;
    background: #f57c00;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-weight: 700;
    font-size: 18px;
}
.panel-header h2 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #212121;
}
.panel-header .badge {
    background: #e3f2fd;
    color: #1565c0;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 10px;
    letter-spacing: 0.3px;
}
.panel-body { padding: 20px; }
.description {
    color: #616161;
    line-height: 1.5;
    margin: 0 0 16px;
}
.info-box {
    background: #e3f2fd;
    border-left: 4px solid #1976d2;
    padding: 12px 16px;
    margin: 0 0 20px;
    border-radius: 0 4px 4px 0;
    color: #0d47a1;
    font-size: 13px;
    line-height: 1.5;
}
.info-box strong { color: #0d47a1; }
.stats-row {
    display: flex;
    gap: 12px;
    margin: 0 0 20px;
}
.stat {
    flex: 1;
    background: #fafafa;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    padding: 12px 16px;
    text-align: center;
}
.stat .number {
    font-size: 28px;
    font-weight: 700;
    color: #212121;
    line-height: 1;
}
.stat .label {
    font-size: 12px;
    color: #757575;
    margin-top: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.stat.warning .number { color: #e65100; }
.stat.success .number { color: #2e7d32; }
.issue-list { margin: 0 0 20px; }
.issue-list h3 {
    font-size: 14px;
    font-weight: 600;
    color: #424242;
    margin: 0 0 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.issue-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.issue-table th {
    background: #f5f5f5;
    color: #616161;
    font-weight: 600;
    text-align: left;
    padding: 8px 12px;
    border-bottom: 2px solid #e0e0e0;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.issue-table td {
    padding: 8px 12px;
    border-bottom: 1px solid #f0f0f0;
    color: #424242;
}
.issue-table tr:hover td { background: #fafafa; }
.issue-type {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}
.issue-type.processed { background: #fff3e0; color: #e65100; }
.issue-type.mismatch { background: #fce4ec; color: #c62828; }
.issue-type.broken { background: #ffebee; color: #b71c1c; }
.issue-type.orphaned { background: #f3e5f5; color: #6a1b9a; }
.issue-type.missing { background: #eceff1; color: #37474f; }
.fixable-yes {
    color: #2e7d32;
    font-weight: 600;
}
.fixable-no {
    color: #c62828;
    font-weight: 600;
}
.panel-footer {
    padding: 16px 20px;
    border-top: 1px solid #e0e0e0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #fafafa;
}
.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 20px;
    border: none;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    letter-spacing: 0.3px;
}
.btn-primary {
    background: #f57c00;
    color: #fff;
}
.btn-outline {
    background: transparent;
    color: #616161;
    border: 1px solid #bdbdbd;
}
.footer-note {
    color: #9e9e9e;
    font-size: 12px;
}
.mono { font-family: 'JetBrains Mono', 'Fira Code', monospace; font-size: 12px; }
</style>
</head>
<body>
<div class="wizard-panel">
    <div class="panel-header">
        <div class="icon">&#x2714;</div>
        <h2>Validate RTE Image References</h2>
        <span class="badge">UPGRADE WIZARD</span>
    </div>
    <div class="panel-body">
        <p class="description">
            Scans all RTE content fields for stale, broken, or mismatched image references
            and corrects fixable issues. This wizard addresses problems that may occur after
            file moves, FAL migrations, or upgrades from older TYPO3 versions.
        </p>
        <div class="info-box">
            <strong>Detected issues:</strong> 5 image reference problems found across 4 records.
            4 issues can be fixed automatically. 1 issue requires manual review.
        </div>
        <div class="stats-row">
            <div class="stat">
                <div class="number">142</div>
                <div class="label">Records scanned</div>
            </div>
            <div class="stat">
                <div class="number">287</div>
                <div class="label">Images checked</div>
            </div>
            <div class="stat warning">
                <div class="number">5</div>
                <div class="label">Issues found</div>
            </div>
            <div class="stat success">
                <div class="number">4</div>
                <div class="label">Auto-fixable</div>
            </div>
        </div>
        <div class="issue-list">
            <h3>Issues to fix</h3>
            <table class="issue-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Record</th>
                        <th>Current src</th>
                        <th>Expected src</th>
                        <th>Fixable</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="issue-type processed">Processed src</span></td>
                        <td class="mono">tt_content:42</td>
                        <td class="mono">/fileadmin/_processed_/a/b/csm_photo...</td>
                        <td class="mono">/fileadmin/photos/team.jpg</td>
                        <td class="fixable-yes">Yes</td>
                    </tr>
                    <tr>
                        <td><span class="issue-type mismatch">Src mismatch</span></td>
                        <td class="mono">tt_content:87</td>
                        <td class="mono">/fileadmin/old-path/banner.jpg</td>
                        <td class="mono">/fileadmin/images/banner.jpg</td>
                        <td class="fixable-yes">Yes</td>
                    </tr>
                    <tr>
                        <td><span class="issue-type broken">Broken src</span></td>
                        <td class="mono">tt_content:93</td>
                        <td class="mono">&mdash;</td>
                        <td class="mono">/fileadmin/logos/company.png</td>
                        <td class="fixable-yes">Yes</td>
                    </tr>
                    <tr>
                        <td><span class="issue-type orphaned">Orphaned UID</span></td>
                        <td class="mono">tt_content:105</td>
                        <td class="mono">/fileadmin/deleted-image.jpg</td>
                        <td class="mono">&mdash;</td>
                        <td class="fixable-yes">Yes</td>
                    </tr>
                    <tr>
                        <td><span class="issue-type missing">Missing UID</span></td>
                        <td class="mono">tt_content:112</td>
                        <td class="mono">/fileadmin/legacy/photo.jpg</td>
                        <td class="mono">&mdash;</td>
                        <td class="fixable-no">No</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="panel-footer">
        <span class="footer-note">Admin Tools &rsaquo; Upgrade &rsaquo; Upgrade Wizard</span>
        <div>
            <button class="btn btn-outline">Skip wizard</button>
            &nbsp;
            <button class="btn btn-primary">&#x25B6; Execute</button>
        </div>
    </div>
</div>
</body>
</html>`;

        await page.setContent(wizardHtml);
        await page.waitForTimeout(500);

        const panel = page.locator('.wizard-panel');
        await panel.screenshot({
            path: 'Documentation/Images/upgrade-wizard-validate-references.png',
        });

        expect(await panel.isVisible()).toBe(true);
    });
});
