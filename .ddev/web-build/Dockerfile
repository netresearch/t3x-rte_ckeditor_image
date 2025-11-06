ARG BASE_IMAGE
FROM $BASE_IMAGE

ENV EXTENSION_KEY "rte_ckeditor_image"
ENV DDEV_SITENAME "rte-ckeditor-image"
ENV EXTENSION_TITLE "RTE CKEditor Image"
ENV EXTENSION_DESCRIPTION "Image support in CKEditor for the TYPO3 ecosystem"
ENV GITHUB_URL "https://github.com/netresearch/t3x-rte_ckeditor_image"

# Get git info from build context (runs on host during build)
ARG GIT_BRANCH="unknown"
ARG GIT_COMMIT="unknown"
ARG GIT_PR="unknown"

# Create PHP-based landing page with git info from build args
RUN cat > /var/www/html/index.php << PHPEOF
<?php
// Get git info from environment (set during build)
\$gitBranch = '${GIT_BRANCH}';
\$gitCommit = '${GIT_COMMIT}';
\$prNumber = '${GIT_PR}' !== 'unknown' ? '${GIT_PR}' : null;

\$githubUrl = 'https://github.com/netresearch/t3x-rte_ckeditor_image';
\$branchUrl = \$githubUrl . '/tree/' . urlencode(\$gitBranch);
\$prUrl = \$prNumber ? \$githubUrl . '/pull/' . \$prNumber : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>RTE CKEditor Image - DDEV Development Environment</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:'Open Sans',sans-serif;line-height:1.6;color:#585961;background:#2F99A4;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}.container{max-width:1200px;width:100%;background:#fff;border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden}.header{background:#fff;color:#585961;padding:40px 40px 30px 40px;border-bottom:3px solid #2F99A4}.title-row{display:flex;align-items:center;gap:20px;margin-bottom:15px}.logo{width:80px;height:80px;flex-shrink:0;display:flex;align-items:center;justify-content:center}.logo svg{width:100%;height:100%}.title-text h1{font-family:'Raleway',sans-serif;font-size:2.2em;margin:0;font-weight:700;color:#585961}.title-text p{font-family:'Open Sans',sans-serif;font-size:1.1em;margin:5px 0 0 0;color:#585961}.git-info{display:flex;gap:12px;align-items:center;margin-top:12px;padding:10px 0;font-size:.9em}.git-info a{color:#2F99A4;text-decoration:none;font-weight:600;display:inline-flex;align-items:center;gap:4px;padding:4px 12px;background:#f7fafc;border-radius:6px;border:1px solid #e2e8f0;transition:all .2s}.git-info a:hover{background:#2F99A4;color:#fff;border-color:#2F99A4}.git-info .separator{color:#cbd5e0;font-weight:300}.badges{display:flex;gap:10px;flex-wrap:wrap;margin-top:8px}.badge{background:#2F99A4;color:#fff;padding:6px 14px;border-radius:20px;font-size:.85em;font-weight:600}.content{padding:40px}.section{margin-bottom:40px}.section h2{font-family:'Raleway',sans-serif;color:#585961;font-size:1.6em;margin-bottom:20px;padding-bottom:10px;border-bottom:3px solid #2F99A4}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-bottom:30px}.card{background:#f7fafc;padding:24px;border-radius:10px;transition:transform .2s,box-shadow .2s;border:2px solid #e2e8f0}.card:hover{transform:translateY(-5px);box-shadow:0 10px 25px rgba(47,153,164,.3);border-color:#2F99A4}.card h3{font-family:'Raleway',sans-serif;color:#2F99A4;font-size:1.2em;margin-bottom:10px;font-weight:600}.card p{color:#585961;margin-bottom:15px;font-size:.95em}.card a{display:inline-block;background:#2F99A4;color:#fff;text-decoration:none;padding:12px 24px;border-radius:6px;font-weight:600;transition:all .2s;font-size:.9em}.card a:hover{background:#267883;transform:scale(1.05)}.credentials{background:#fef5e7;padding:24px;border-radius:10px;border-left:4px solid #FF4D00;margin-bottom:30px}.credentials h3{font-family:'Raleway',sans-serif;color:#92400e;margin-bottom:12px;font-size:1.1em;font-weight:600}.credentials p{margin:6px 0}.credentials code{background:#fefce8;padding:3px 10px;border-radius:4px;font-family:'Courier New',monospace;color:#92400e}.footer{background:#585961;color:#fff;padding:30px;text-align:center;font-size:.9em}.footer a{color:#2F99A4;text-decoration:none;font-weight:600}.footer a:hover{text-decoration:underline}</style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="title-row">
            <div class="logo">
                <svg viewBox="-75 -75 440 440" version="1.2">
                    <title>Netresearch DTT GmbH</title>
                    <g>
                        <path fill="#2999a4" d="M209.6,0V31.62h32.77a26.38,26.38,0,0,1,26.44,26.43V242a26.38,26.38,0,0,1-26.44,26.44H209.6V300h47.93a42.77,42.77,0,0,0,42.86-42.86V42.89A42.76,42.76,0,0,0,257.53,0ZM43.25,0A42.76,42.76,0,0,0,.39,42.89V257.18A42.76,42.76,0,0,0,43.25,300H91.18V268.46H58.4A26.38,26.38,0,0,1,32,242v-184A26.37,26.37,0,0,1,58.4,31.62H91.18V0Z" transform="translate(-0.39 -0.04)"/>
                        <path fill="#595a62" d="M221.44,120.41c0-34.48-13.94-57.82-48.93-57.82-26.62,0-48.54,7.74-64.17,26.56l-.7-22.06-28.31.06V232.94h31.59V124.69c7.14-18.38,32.14-34.8,53-34.5,27.38.4,25.2,26.24,26,45.81v96.94h31.58" transform="translate(-0.39 -0.04)"/>
                    </g>
                </svg>
            </div>
            <div class="title-text">
                <h1>RTE CKEditor Image</h1>
                <p>Image support in CKEditor for the TYPO3 ecosystem</p>
                <div class="git-info">
                    <a href="<?= htmlspecialchars(\$branchUrl) ?>" target="_blank" title="View branch on GitHub">
                        🌿 <?= htmlspecialchars(\$gitBranch) ?> @ <?= htmlspecialchars(\$gitCommit) ?>
                    </a>
                    <?php if (\$prUrl): ?>
                        <span class="separator">|</span>
                        <a href="<?= htmlspecialchars(\$prUrl) ?>" target="_blank" title="View pull request">
                            🔀 PR #<?= htmlspecialchars(\$prNumber) ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="badges">
            <span class="badge">TYPO3 13.4+</span>
            <span class="badge">PHP 8.2/8.3/8.4</span>
            <span class="badge">DDEV Development</span>
        </div>
    </div>
    <div class="content">
        <div class="grid">
            <div class="card">
                <h3>📖 Documentation</h3>
                <p>Browse the complete extension documentation</p>
                <a href="https://docs.rte-ckeditor-image.ddev.site/" target="_blank">View Documentation →</a>
            </div>
            <div class="card">
                <h3>🌐 TYPO3 Frontend</h3>
                <p>View the TYPO3 demo site with example content</p>
                <a href="https://v13.rte-ckeditor-image.ddev.site/" target="_blank">Open Frontend →</a>
            </div>
            <div class="card">
                <h3>⚙️ TYPO3 Backend</h3>
                <p>Access the TYPO3 backend administration panel</p>
                <a href="https://v13.rte-ckeditor-image.ddev.site/typo3/" target="_blank">Open Backend →</a>
            </div>
        </div>
        <div class="section">
            <div class="credentials">
                <h3>🔑 TYPO3 Backend Credentials</h3>
                <p><strong>Username:</strong> <code>admin</code></p>
                <p><strong>Password:</strong> <code>Password:joh316</code></p>
            </div>
        </div>
    </div>
    <div class="footer">
        <p>Developed by <a href="https://www.netresearch.de/" target="_blank">Netresearch DTT GmbH</a></p>
        <p style="margin-top:10px;opacity:.8">License: AGPL-3.0-or-later</p>
    </div>
</div>
</body>
</html>
PHPEOF

# Setup TYPO3 v13 placeholder pages
RUN mkdir -p /var/www/html/v13/public/typo3
RUN echo "<h1>Perform this first</h1> <code>ddev install-v13</code>" > /var/www/html/v13/public/index.html
RUN echo "<h1>Perform this first</h1> <code>ddev install-v13</code>" > /var/www/html/v13/public/typo3/index.html

ARG uid
ARG gid
RUN chown -R $uid:$gid /var/www/html
