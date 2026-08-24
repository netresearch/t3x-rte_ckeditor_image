<?php
// Create test image
$im = imagecreatetruecolor(800, 600);
$blue = imagecolorallocate($im, 0, 100, 200);
$white = imagecolorallocate($im, 255, 255, 255);
imagefill($im, 0, 0, $blue);
imagestring($im, 5, 300, 280, 'E2E Test Image', $white);
imagejpeg($im, 'public/fileadmin/user_upload/example.jpg', 90);
imagedestroy($im);
echo "Test image created\n";

// Connect to MariaDB
$pdo = new PDO(
    'mysql:host=mariadb-e2e;port=3306;dbname=e2e_test',
    'root',
    'root',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$now = time();

// Create or update sys_file entry
$identifierHash = sha1('/user_upload/example.jpg');
$folderHash = sha1('/user_upload/');
$pdo->exec("INSERT INTO sys_file (uid, storage, identifier, identifier_hash, folder_hash, name, extension, mime_type, size, tstamp, creation_date)
            VALUES (1, 1, '/user_upload/example.jpg', '$identifierHash', '$folderHash', 'example.jpg', 'jpg', 'image/jpeg', 48000, $now, $now)
            ON DUPLICATE KEY UPDATE storage = 1, identifier = '/user_upload/example.jpg', identifier_hash = '$identifierHash', folder_hash = '$folderHash'");
echo "sys_file record created\n";

// Create sys_file_metadata with dimensions, alt, and title
// width/height are TCA columns on sys_file_metadata — used by getImageInfo() for dialog constraints
// alternative/title provide FAL metadata defaults — enables override checkbox in image dialog
// Use DELETE + INSERT to ensure our values win over any auto-indexed metadata
$pdo->exec("DELETE FROM sys_file_metadata WHERE file = 1");
$pdo->exec("INSERT INTO sys_file_metadata (uid, file, title, description, alternative, width, height, tstamp, crdate)
            VALUES (1, 1, 'Example Image Title', 'Test image for E2E', 'Example Alt from Metadata', 800, 600, $now, $now)");
echo "sys_file_metadata record created\n";

// Insert test content with RTE image (no caption)
$bodytext = '<p>This is a test page with an RTE image:</p><p><img src="fileadmin/user_upload/example.jpg" alt="Example" width="800" height="600" data-htmlarea-zoom="true" data-htmlarea-file-uid="1" /></p><p>Click the image to see click-to-enlarge.</p>';
$stmt = $pdo->prepare("INSERT INTO tt_content (pid, CType, header, bodytext, hidden, deleted, tstamp, crdate, colPos, sorting) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([1, 'text', 'RTE CKEditor Image Demo', $bodytext, 0, 0, $now, $now, 0, 256]);
echo "tt_content record created\n";

// Insert test content with RTE image WITH CAPTION (to test for <p>&nbsp;</p> artifacts)
// Uses <figure><figcaption> markup which triggers the WithCaption.html Fluid template
$bodytextCaption = '<p>Image with caption test:</p>'
    . '<figure class="image"><img src="fileadmin/user_upload/example.jpg" alt="Caption Test" width="400" height="300" data-htmlarea-file-uid="1" /><figcaption>Test Caption Text</figcaption></figure>';
$stmt->execute([1, 'text', 'Caption Test', $bodytextCaption, 0, 0, $now, $now, 0, 512]);
echo "tt_content record with caption created\n";

// Insert test content with LINKED IMAGE (issue #565 - duplicate links)
// This tests that linked images render with a single <a> tag, not duplicated
$bodytextLinked = '<p>Test linked image (should have single link wrapper):</p><a href="https://example.com" target="_blank" title="Example Link" class="test-linked-image"><img src="fileadmin/user_upload/example.jpg" alt="Linked Image" width="400" height="300" data-htmlarea-file-uid="1" /></a><p>The image above should link to example.com with a single &lt;a&gt; tag.</p>';
$stmt->execute([1, 'text', 'Linked Image Test (#565)', $bodytextLinked, 0, 0, $now, $now, 0, 768]);
echo "tt_content record with linked image created\n";

// Insert test content with LINKED IMAGE with CAPTION using data-caption attribute
// This tests that linked images with captions render correctly via the renderImages handler
// Note: We DON'T use raw <figure> because parseFunc_RTE.tags.figure.preUserFunc handles
// figure-wrapped images, but linked images inside figures have complex processing
// The simpler case tests link + data-caption combination
$bodytextFigureLinked = '<p>Test linked image with caption:</p><p><a href="https://netresearch.de" target="_blank" class="test-figure-linked"><img src="fileadmin/user_upload/example.jpg" alt="Captioned Linked Image" width="400" height="300" data-htmlarea-file-uid="1" /></a></p>';
$stmt->execute([1, 'text', 'Figure Linked Image Test', $bodytextFigureLinked, 0, 0, $now, $now, 0, 1024]);
echo "tt_content record with linked image created\n";

// Insert test content with standalone linked image (no figure, no caption) for regression test
$bodytextSimpleLinked = '<p>Simple linked image without caption:</p><p><a href="https://typo3.org" class="test-simple-link"><img src="fileadmin/user_upload/example.jpg" alt="Simple Link" width="300" height="225" data-htmlarea-file-uid="1" /></a></p>';
$stmt->execute([1, 'text', 'Simple Linked Image', $bodytextSimpleLinked, 0, 0, $now, $now, 0, 1280]);
echo "tt_content record with simple linked image created\n";

// UID 6: Styled/Alignment Images (needed by image-styles.spec.ts)
$bodytextStyles = '<p>Images with alignment classes:</p>'
    . '<p><img class="image-left" src="fileadmin/user_upload/example.jpg" alt="Left Aligned" width="300" height="225" data-htmlarea-file-uid="1" /></p>'
    . '<p><img class="image-right" src="fileadmin/user_upload/example.jpg" alt="Right Aligned" width="300" height="225" data-htmlarea-file-uid="1" /></p>'
    . '<p><img class="image-center" src="fileadmin/user_upload/example.jpg" alt="Center Aligned" width="400" height="300" data-htmlarea-file-uid="1" /></p>'
    . '<p><img class="image-block" src="fileadmin/user_upload/example.jpg" alt="Block Image" width="400" height="300" data-htmlarea-file-uid="1" /></p>'
    . '<figure class="image-center"><img src="fileadmin/user_upload/example.jpg" alt="Centered Figure" width="400" height="300" data-htmlarea-file-uid="1" /><figcaption>Centered figure with caption</figcaption></figure>';
$stmt->execute([1, 'text', 'Styled/Alignment Images', $bodytextStyles, 0, 0, $now, $now, 0, 1536]);
echo "tt_content record with styled/alignment images created\n";

// UID 7: Inline Images (needed by inline-images.spec.ts, inline-image-editing.spec.ts, inline-image-issues.spec.ts)
// Line 1: Plain inline (no zoom, no link) — used by "no indicators" test
// Line 2: Linked inline — used by linked inline tests
// Line 3: Multiple inline images in one paragraph
// Line 4: Inline with zoom — demonstrates #639 fix (zoom indicator)
// Line 5: Inline with link + zoom — demonstrates combined indicators
// Line 6: Block image coexistence
$bodytextInline = '<p>Text before <img class="image-inline" src="fileadmin/user_upload/example.jpg" alt="Inline Example" width="100" height="75" data-htmlarea-file-uid="1" /> text after.</p>'
    . '<p>A linked inline image: <a href="https://example.com"><img class="image-inline" src="fileadmin/user_upload/example.jpg" alt="Linked Inline" width="80" height="60" data-htmlarea-file-uid="1" /></a> in text.</p>'
    . '<p>Multiple inline images: <img class="image-inline" src="fileadmin/user_upload/example.jpg" alt="First Inline" width="50" height="38" data-htmlarea-file-uid="1" /> and <img class="image-inline" src="fileadmin/user_upload/example.jpg" alt="Second Inline" width="50" height="38" data-htmlarea-file-uid="1" /> in one paragraph.</p>'
    . '<p>Inline with zoom: <img class="image-inline" src="fileadmin/user_upload/example.jpg" alt="Zoom Inline" width="80" height="60" data-htmlarea-zoom="true" data-htmlarea-file-uid="1" /> click to enlarge.</p>'
    . '<p>Inline with link and zoom: <a href="https://example.com"><img class="image-inline" src="fileadmin/user_upload/example.jpg" alt="Link+Zoom Inline" width="80" height="60" data-htmlarea-zoom="true" data-htmlarea-file-uid="1" /></a> both indicators.</p>'
    . '<figure class="image"><img src="fileadmin/user_upload/example.jpg" alt="Block in Inline CE" width="400" height="300" data-htmlarea-file-uid="1" /></figure>';
$stmt->execute([1, 'text', 'Inline Images', $bodytextInline, 0, 0, $now, $now, 0, 1792]);
echo "tt_content record with inline images created\n";

// UID 8: Inline Image Complex Patterns (needed by inline-image-patterns.spec.ts)
$bodytextInlinePatterns = '<p>Link with inline image at start:</p>'
    . '<p><a href="https://docs.example.com"><img class="image-inline" src="fileadmin/user_upload/example.jpg" alt="docs" width="16" height="16" data-htmlarea-file-uid="1" /> Documentation</a></p>'
    . '<p>Link with inline image at end:</p>'
    . '<p><a href="https://download.example.com">Get the latest version <img class="image-inline" src="fileadmin/user_upload/example.jpg" alt="download" width="20" height="20" data-htmlarea-file-uid="1" /></a></p>'
    . '<p>Link with text before and after image:</p>'
    . '<p><a href="https://example.com">Check our <img class="image-inline" src="fileadmin/user_upload/example.jpg" alt="icon" width="16" height="16" data-htmlarea-file-uid="1" /> documentation</a></p>'
    . '<table><tr><td>Feature <img class="image-inline" src="fileadmin/user_upload/example.jpg" alt="feature" width="24" height="24" data-htmlarea-file-uid="1" /></td><td>Works great</td></tr></table>'
    . '<ul><li>Support for <img class="image-inline" src="fileadmin/user_upload/example.jpg" alt="feature" width="20" height="20" data-htmlarea-file-uid="1" /> inline images</li></ul>'
    . '<h3>Features <img class="image-inline" src="fileadmin/user_upload/example.jpg" alt="feature" width="24" height="24" data-htmlarea-file-uid="1" /></h3>';
$stmt->execute([1, 'text', 'Inline Image Complex Patterns', $bodytextInlinePatterns, 0, 0, $now, $now, 0, 2048]);
echo "tt_content record with inline image complex patterns created\n";

// UID 9: Multiple Popup/Zoom Images (needed by click-to-enlarge.spec.ts "multiple images all have popup functionality")
$bodytextMultiZoom = '<p>Multiple images with click-to-enlarge:</p>'
    . '<p><img src="fileadmin/user_upload/example.jpg" alt="popup1" width="300" height="225" data-htmlarea-zoom="true" data-htmlarea-file-uid="1" /></p>'
    . '<p><img src="fileadmin/user_upload/example.jpg" alt="popup2" width="300" height="225" data-htmlarea-zoom="true" data-htmlarea-file-uid="1" /></p>'
    . '<p><img src="fileadmin/user_upload/example.jpg" alt="popup3" width="300" height="225" data-htmlarea-zoom="true" data-htmlarea-file-uid="1" /></p>';
$stmt->execute([1, 'text', 'Multiple Popup Images', $bodytextMultiZoom, 0, 0, $now, $now, 0, 2304]);
echo "tt_content record with multiple popup images created\n";

// UID 10: Mixed Content with Text Links (needed by linked-image-backend.spec.ts "regular text links still show link balloon")
$bodytextMixed = '<p>Visit our <a href="https://example.com">website</a> for more info.</p><p><img src="fileadmin/user_upload/example.jpg" alt="Mixed Content" width="400" height="300" data-htmlarea-file-uid="1" /></p>';
$stmt->execute([1, 'text', 'Mixed Content with Text Links', $bodytextMixed, 0, 0, $now, $now, 0, 2560]);
echo "tt_content record with mixed content created\n";

// UID 11: t3:// link image (needed by t3-link-resolution.spec.ts — regression test for #594)
$bodytextT3Link = '<p>Image linked with t3:// protocol:</p>'
    . '<p><a href="t3://page?uid=1" class="test-t3-link"><img src="fileadmin/user_upload/example.jpg" alt="T3 Linked Image" width="400" height="300" data-htmlarea-file-uid="1" /></a></p>';
$stmt->execute([1, 'text', 'T3 Link Image (#594)', $bodytextT3Link, 0, 0, $now, $now, 0, 2816]);
echo "tt_content record with t3:// link created\n";

// UID 12: Alignment WITHOUT caption (needed by alignment-no-caption.spec.ts — regression test for #595)
// These should render as bare <img class="..."> NOT wrapped in <figure>
$bodytextAlignNoCaption = '<p>Alignment classes without caption:</p>'
    . '<p><img class="image-left" src="fileadmin/user_upload/example.jpg" alt="Align Left No Caption" width="300" height="225" data-htmlarea-file-uid="1" /></p>'
    . '<p><img class="image-center" src="fileadmin/user_upload/example.jpg" alt="Align Center No Caption" width="300" height="225" data-htmlarea-file-uid="1" /></p>'
    . '<p><img class="image-right" src="fileadmin/user_upload/example.jpg" alt="Align Right No Caption" width="300" height="225" data-htmlarea-file-uid="1" /></p>';
$stmt->execute([1, 'text', 'Alignment Without Caption (#595)', $bodytextAlignNoCaption, 0, 0, $now, $now, 0, 3072]);
echo "tt_content record with alignment-no-caption created\n";

// UID 13: Alignment WITH caption (needed by alignment-no-caption.spec.ts — contrast test)
// These should render as <figure class="image-..."><img><figcaption>
$bodytextAlignWithCaption = '<p>Alignment classes with caption:</p>'
    . '<figure class="image image-left"><img src="fileadmin/user_upload/example.jpg" alt="Align Left With Caption" width="300" height="225" data-htmlarea-file-uid="1" /><figcaption>Left caption</figcaption></figure>'
    . '<figure class="image image-center"><img src="fileadmin/user_upload/example.jpg" alt="Align Center With Caption" width="300" height="225" data-htmlarea-file-uid="1" /><figcaption>Center caption</figcaption></figure>'
    . '<figure class="image image-right"><img src="fileadmin/user_upload/example.jpg" alt="Align Right With Caption" width="300" height="225" data-htmlarea-file-uid="1" /><figcaption>Right caption</figcaption></figure>';
$stmt->execute([1, 'text', 'Alignment With Caption (#595)', $bodytextAlignWithCaption, 0, 0, $now, $now, 0, 3328]);
echo "tt_content record with alignment-with-caption created\n";

// UIDs 14-19: Template rendering matrix (one CE per Fluid template)
// Each has identifiable alt text for precise assertion in rendering-template-matrix.spec.ts

// UID 14: Standalone template — bare <img> without link or caption
$bodytextTplStandalone = '<p><img src="fileadmin/user_upload/example.jpg" alt="Template Standalone" width="400" height="300" data-htmlarea-file-uid="1" /></p>';
$stmt->execute([1, 'text', 'Template: Standalone', $bodytextTplStandalone, 0, 0, $now, $now, 0, 3584]);

// UID 15: WithCaption template — <figure><img><figcaption>
$bodytextTplCaption = '<figure class="image"><img src="fileadmin/user_upload/example.jpg" alt="Template WithCaption" width="400" height="300" data-htmlarea-file-uid="1" /><figcaption>Template caption text</figcaption></figure>';
$stmt->execute([1, 'text', 'Template: WithCaption', $bodytextTplCaption, 0, 0, $now, $now, 0, 3840]);

// UID 16: Link template — <a href="..."><img>
$bodytextTplLink = '<p><a href="https://example.com/template-link" class="test-template-link"><img src="fileadmin/user_upload/example.jpg" alt="Template Link" width="400" height="300" data-htmlarea-file-uid="1" /></a></p>';
$stmt->execute([1, 'text', 'Template: Link', $bodytextTplLink, 0, 0, $now, $now, 0, 4096]);

// UID 17: LinkWithCaption template — <figure><a><img></a><figcaption>
$bodytextTplLinkCaption = '<figure class="image"><a href="https://example.com/template-link-caption" class="test-template-link-caption"><img src="fileadmin/user_upload/example.jpg" alt="Template LinkWithCaption" width="400" height="300" data-htmlarea-file-uid="1" /></a><figcaption>Linked caption text</figcaption></figure>';
$stmt->execute([1, 'text', 'Template: LinkWithCaption', $bodytextTplLinkCaption, 0, 0, $now, $now, 0, 4352]);

// UID 18: Popup template — <img data-htmlarea-zoom="true">
$bodytextTplPopup = '<p><img src="fileadmin/user_upload/example.jpg" alt="Template Popup" width="400" height="300" data-htmlarea-zoom="true" data-htmlarea-file-uid="1" /></p>';
$stmt->execute([1, 'text', 'Template: Popup', $bodytextTplPopup, 0, 0, $now, $now, 0, 4608]);

// UID 19: PopupWithCaption template — <figure><img data-htmlarea-zoom="true"><figcaption>
$bodytextTplPopupCaption = '<figure class="image"><img src="fileadmin/user_upload/example.jpg" alt="Template PopupWithCaption" width="400" height="300" data-htmlarea-zoom="true" data-htmlarea-file-uid="1" /><figcaption>Popup caption text</figcaption></figure>';
$stmt->execute([1, 'text', 'Template: PopupWithCaption', $bodytextTplPopupCaption, 0, 0, $now, $now, 0, 4864]);

echo "Template matrix content elements (UIDs 14-19) created\n";

// UIDs 20-25: Error handling & edge cases — on PAGE 2 to isolate from main page
// These CEs test error handling and security edge cases that could affect page rendering
$stmtP2 = $pdo->prepare("INSERT INTO tt_content (pid, CType, header, bodytext, hidden, deleted, tstamp, crdate, colPos, sorting) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

// UID 20: Error handling — missing file UID (references non-existent sys_file)
$bodytextMissingFile = '<p>Image with missing file:</p>'
    . '<p><img src="fileadmin/user_upload/nonexistent.jpg" alt="Missing File" width="300" height="225" data-htmlarea-file-uid="9999" /></p>';
$stmtP2->execute([2, 'text', 'Error: Missing File', $bodytextMissingFile, 0, 0, $now, $now, 0, 256]);

// UID 21: Error handling — XSS in alt/title/caption
$bodytextXss = '<p>XSS test content:</p>'
    . '<p><img src="fileadmin/user_upload/example.jpg" alt="&lt;script&gt;alert(1)&lt;/script&gt;" title="&lt;img onerror=alert(1)&gt;" width="300" height="225" data-htmlarea-file-uid="1" /></p>'
    . '<figure class="image"><img src="fileadmin/user_upload/example.jpg" alt="XSS Caption Test" width="300" height="225" data-htmlarea-file-uid="1" /><figcaption>&lt;script&gt;alert("xss")&lt;/script&gt;</figcaption></figure>';
$stmtP2->execute([2, 'text', 'Error: XSS Payloads', $bodytextXss, 0, 0, $now, $now, 0, 512]);

// UID 22: Error handling — special characters in alt/title
$bodytextSpecialChars = '<p>Special characters in attributes:</p>'
    . '<p><img src="fileadmin/user_upload/example.jpg" alt="Quotes &quot;double&quot; and &apos;single&apos;" title="Ampersand &amp; entities" width="300" height="225" data-htmlarea-file-uid="1" /></p>'
    . '<p><img src="fileadmin/user_upload/example.jpg" alt="Unicode: äöü éàè 日本語" width="300" height="225" data-htmlarea-file-uid="1" /></p>';
$stmtP2->execute([2, 'text', 'Error: Special Characters', $bodytextSpecialChars, 0, 0, $now, $now, 0, 768]);

// UID 23: Error handling — empty alt text
$bodytextEmptyAlt = '<p>Image with empty alt:</p>'
    . '<p><img src="fileadmin/user_upload/example.jpg" alt="" width="300" height="225" data-htmlarea-file-uid="1" /></p>';
$stmtP2->execute([2, 'text', 'Error: Empty Alt', $bodytextEmptyAlt, 0, 0, $now, $now, 0, 1024]);

// UID 24: Error handling — whitespace-only caption (should NOT render <figure>)
$bodytextWhitespaceCaption = '<figure class="image"><img src="fileadmin/user_upload/example.jpg" alt="Whitespace Caption" width="300" height="225" data-htmlarea-file-uid="1" /><figcaption>   </figcaption></figure>';
$stmtP2->execute([2, 'text', 'Error: Whitespace Caption', $bodytextWhitespaceCaption, 0, 0, $now, $now, 0, 1280]);

// UID 25: Click behavior — image with both link and zoom (popup takes priority per selectTemplate())
$bodytextLinkPriority = '<p>Link + zoom conflict:</p>'
    . '<p><a href="https://example.com/priority-test"><img src="fileadmin/user_upload/example.jpg" alt="Link Priority Test" width="300" height="225" data-htmlarea-zoom="true" data-htmlarea-file-uid="1" /></a></p>';
$stmtP2->execute([2, 'text', 'Popup Priority over Link', $bodytextLinkPriority, 0, 0, $now, $now, 0, 1536]);

echo "Error handling & edge case content elements (UIDs 20-25) created on page 2\n";

// UIDs 26-33: Isolated CEs for backend tests that SAVE content
// Each saving spec gets its own CE to prevent cross-file pollution (#621)
// (Parallel test execution with fullyParallel=true means save order is random)
// IMPORTANT: CKEditor needs surrounding text paragraphs — bare <p><img></p>
// renders as a block widget where double-click doesn't trigger the image dialog.

// UID 26: For image-dialog-dimensions.spec.ts (saves dimension changes)
$bodytextDimensions = '<p>Dimensions test content:</p><p><img src="fileadmin/user_upload/example.jpg" alt="Dimensions Test" width="800" height="600" data-htmlarea-file-uid="1" /></p><p>End of dimensions test.</p>';
$stmt->execute([1, 'text', 'Dimensions Test CE', $bodytextDimensions, 0, 0, $now, $now, 0, 6656]);

// UID 27: For image-dialog-quality.spec.ts (saves quality changes)
$bodytextQuality = '<p>Quality test content:</p><p><img src="fileadmin/user_upload/example.jpg" alt="Quality Test" width="800" height="600" data-htmlarea-file-uid="1" /></p><p>End of quality test.</p>';
$stmt->execute([1, 'text', 'Quality Test CE', $bodytextQuality, 0, 0, $now, $now, 0, 6912]);

// UID 28: For image-dialog-overrides.spec.ts (saves override state)
// data-alt-override="false" and data-title-override="false" ensure override checkboxes
// start UNCHECKED — alt/title inputs are disabled, showing FAL metadata as placeholder.
// Without these attributes, typo3image.js defaults to override=checked (inputs enabled).
$bodytextOverrides = '<p>Overrides test content:</p><p><img src="fileadmin/user_upload/example.jpg" alt="" data-alt-override="false" data-title-override="false" width="800" height="600" data-htmlarea-file-uid="1" /></p><p>End of overrides test.</p>';
$stmt->execute([1, 'text', 'Overrides Test CE', $bodytextOverrides, 0, 0, $now, $now, 0, 7168]);

// UID 29: For image-dialog-click-behavior.spec.ts (saves link/zoom changes)
$bodytextClickBehavior = '<p>Click behavior test content:</p><p><img src="fileadmin/user_upload/example.jpg" alt="Click Behavior Test" width="800" height="600" data-htmlarea-file-uid="1" /></p><p>End of click behavior test.</p>';
$stmt->execute([1, 'text', 'Click Behavior Test CE', $bodytextClickBehavior, 0, 0, $now, $now, 0, 7424]);

// UID 30: For image-dialog-click-behavior.spec.ts zoom tests (has zoom pre-set)
$bodytextClickZoom = '<p>Click zoom test content:</p><p><img src="fileadmin/user_upload/example.jpg" alt="Click Zoom Test" width="800" height="600" data-htmlarea-zoom="true" data-htmlarea-file-uid="1" /></p><p>End of click zoom test.</p>';
$stmt->execute([1, 'text', 'Click Zoom Test CE', $bodytextClickZoom, 0, 0, $now, $now, 0, 7680]);

// UID 31: For image-dialog-apply-changes.spec.ts (saves alt/title/dimension/link changes)
// No data-htmlarea-zoom: zoom is explicitly set by the "click-to-enlarge" test,
// and having it pre-set makes confirmImageDialog() less reliable.
$bodytextApply = '<p>Apply changes test content:</p><p><img src="fileadmin/user_upload/example.jpg" alt="Apply Test" width="800" height="600" data-htmlarea-file-uid="1" /></p><p>End of apply changes test.</p>';
$stmt->execute([1, 'text', 'Apply Changes Test CE', $bodytextApply, 0, 0, $now, $now, 0, 7936]);

// UID 32: For link-attributes-roundtrip.spec.ts (saves link attribute changes)
$bodytextRoundtrip = '<p>Roundtrip test content:</p><p><img src="fileadmin/user_upload/example.jpg" alt="Roundtrip Test" width="800" height="600" data-htmlarea-zoom="true" data-htmlarea-file-uid="1" /></p><p>End of roundtrip test.</p>';
$stmt->execute([1, 'text', 'Roundtrip Test CE', $bodytextRoundtrip, 0, 0, $now, $now, 0, 8192]);

// UID 33: For image-insertion.spec.ts (read-only verification of image attributes)
$bodytextInsertion = '<p>Insertion test content:</p><p><img src="fileadmin/user_upload/example.jpg" alt="Insertion Test" width="800" height="600" data-htmlarea-zoom="true" data-htmlarea-file-uid="1" /></p><p>End of insertion test.</p>';
$stmt->execute([1, 'text', 'Insertion Test CE', $bodytextInsertion, 0, 0, $now, $now, 0, 8448]);

// UID 34: For link-attributes-roundtrip.spec.ts alignment test (saves link + alignment)
// Separate from CE 32 to prevent parallel test pollution with fullyParallel=true
$bodytextAlignRoundtrip = '<p>Alignment roundtrip test:</p><p><img src="fileadmin/user_upload/example.jpg" alt="Alignment Roundtrip Test" width="800" height="600" data-htmlarea-file-uid="1" /></p><p>End of alignment roundtrip test.</p>';
$stmt->execute([1, 'text', 'Alignment Roundtrip Test CE', $bodytextAlignRoundtrip, 0, 0, $now, $now, 0, 8704]);

// UID 35: For save-render-roundtrip.spec.ts zoom test (saves zoom toggle)
// Needs surrounding text to avoid CKEditor block widget rendering.
$bodytextZoomRoundtrip = '<p>Zoom roundtrip test:</p><p><img src="fileadmin/user_upload/example.jpg" alt="Zoom Roundtrip Test" width="800" height="600" data-htmlarea-file-uid="1" /></p><p>End of zoom roundtrip test.</p>';
$stmt->execute([1, 'text', 'Zoom Roundtrip Test CE', $bodytextZoomRoundtrip, 0, 0, $now, $now, 0, 8960]);

// UID 36: For save-render-roundtrip.spec.ts "save unchanged" test (saves CE without changes)
// Dedicated CE to avoid corrupting CE 1 which is used by read-only tests.
$bodytextSaveRoundtrip = '<p>Save roundtrip test:</p><p><img src="fileadmin/user_upload/example.jpg" alt="Save Roundtrip" width="800" height="600" data-htmlarea-zoom="true" data-htmlarea-file-uid="1" /></p><p>End of save roundtrip test.</p>';
$stmt->execute([1, 'text', 'Save Roundtrip Test CE', $bodytextSaveRoundtrip, 0, 0, $now, $now, 0, 9216]);

// UID 37: For save-render-roundtrip.spec.ts "preserves attributes" test
$bodytextAttrRoundtrip = '<p>Attribute roundtrip test:</p><p><img src="fileadmin/user_upload/example.jpg" alt="Attr Roundtrip" width="800" height="600" data-htmlarea-file-uid="1" /></p><p>End of attribute roundtrip test.</p>';
$stmt->execute([1, 'text', 'Attr Roundtrip Test CE', $bodytextAttrRoundtrip, 0, 0, $now, $now, 0, 9472]);

// UID 38: For save-render-roundtrip.spec.ts "modify alt text" test
$bodytextAltRoundtrip = '<p>Alt text roundtrip test:</p><p><img src="fileadmin/user_upload/example.jpg" alt="Alt Roundtrip" width="800" height="600" data-htmlarea-file-uid="1" /></p><p>End of alt text roundtrip test.</p>';
$stmt->execute([1, 'text', 'Alt Roundtrip Test CE', $bodytextAltRoundtrip, 0, 0, $now, $now, 0, 9728]);

echo "Isolated test CEs (UIDs 26-38) created for saving specs\n";

// UIDs 39-41: Inline image issues (#636, #637, #638, #639)
// These CEs provide test data for inline image bug fixes.
// CE 39: Double-link corrupted inline image — tests upcast recovery (#638)
// CE 40: Inline image with zoom — tests zoom indicator in editor (#639)
// CE 41: Inline image with link — tests link indicator in editor (#639)

// UID 39: Double-link corrupted inline image (<a><a><img class="image-inline"></a></a>)
// This simulates content corrupted by previous save cycles where the double-link
// recovery upcast would incorrectly create a block element instead of inline.
$bodytextDoubleLinkInline = '<p>Double-link inline image recovery test:</p>'
    . '<p>Text before <a href="https://example.com"><a href="https://example.com"><img class="image-inline" src="fileadmin/user_upload/example.jpg" alt="Double Link Inline" width="100" height="75" data-htmlarea-file-uid="1" /></a></a> text after.</p>'
    . '<p>End of double-link inline test.</p>';
// Page 2: corrupted data should not appear on the main frontend page
$stmtP2->execute([2, 'text', 'Double-Link Inline (#638)', $bodytextDoubleLinkInline, 0, 0, $now, $now, 0, 1792]);

// UID 40: Inline image with zoom — should show zoom indicator in CKEditor editing view
$bodytextInlineZoom = '<p>Inline zoom indicator test:</p>'
    . '<p>Text before <img class="image-inline" src="fileadmin/user_upload/example.jpg" alt="Inline Zoom" width="100" height="75" data-htmlarea-zoom="true" data-htmlarea-file-uid="1" /> text after zoom image.</p>'
    . '<p>End of inline zoom test.</p>';
$stmt->execute([1, 'text', 'Inline Zoom (#639)', $bodytextInlineZoom, 0, 0, $now, $now, 0, 10240]);

// UID 41: Inline image with link — should show link indicator in CKEditor editing view
$bodytextInlineLink = '<p>Inline link indicator test:</p>'
    . '<p>Text before <a href="https://example.com/inline-link"><img class="image-inline" src="fileadmin/user_upload/example.jpg" alt="Inline Link" width="100" height="75" data-htmlarea-file-uid="1" /></a> text after linked image.</p>'
    . '<p>End of inline link test.</p>';
$stmt->execute([1, 'text', 'Inline Link (#639)', $bodytextInlineLink, 0, 0, $now, $now, 0, 10496]);

echo "Inline image issue CEs (UIDs 39-41) created for #636/#637/#638/#639\n";

// Content Blocks demo page and CEs (only when Content Blocks is installed)
// The package registers CTypes from ContentBlocks/ definitions; we create
// a demo page and CEs so the E2E content-blocks-preview spec has data.
if (is_dir('/var/www/html/vendor/friendsoftypo3/content-blocks')) {
    echo "Content Blocks detected — creating demo page and content elements...\n";

    // Page uid=3: Content Blocks Demo (child of root page)
    $pdo->exec("INSERT IGNORE INTO pages (uid, pid, title, slug, doktype, is_siteroot, hidden, deleted, tstamp, crdate, sorting) VALUES (3, 1, 'Content Blocks Demo', '/content-blocks-demo', 1, 0, 0, 0, $now, $now, 768)");
    echo "Content Blocks demo page (uid=3) inserted\n";

    // UID 42: Content Block with block image and caption
    $bodytextCB1 = '<p>This content uses a Content Block type with our ViewHelper for backend preview.</p>'
        . '<p><img src="fileadmin/user_upload/example.jpg" alt="Content Block Demo" width="400" height="300" data-htmlarea-file-uid="1" /></p>'
        . '<p>Image rendered via Content Block with RteImagePreview ViewHelper.</p>';
    $pdo->prepare("INSERT INTO tt_content (pid, CType, header, bodytext, hidden, deleted, tstamp, crdate, colPos, sorting) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([3, 'netresearch_rteimagedemo', 'Content Block: Block Image', $bodytextCB1, 0, 0, $now, $now, 0, 256]);

    // UID 43: Content Block with inline images
    $bodytextCB2 = '<p>Inline images work in Content Blocks too: here is one '
        . '<img class="image-inline" src="fileadmin/user_upload/example.jpg" alt="inline demo" width="50" height="38" data-htmlarea-file-uid="1" /> embedded in text.</p>'
        . '<p>And a second paragraph with another inline <img class="image-inline" src="fileadmin/user_upload/example.jpg" alt="second inline" width="50" height="38" data-htmlarea-file-uid="1" /> for good measure.</p>';
    $pdo->prepare("INSERT INTO tt_content (pid, CType, header, bodytext, hidden, deleted, tstamp, crdate, colPos, sorting) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([3, 'netresearch_rteimagedemo', 'Content Block: Inline Images', $bodytextCB2, 0, 0, $now, $now, 0, 512]);

    echo "Content Block CEs (UIDs 42-43) created on page 3\n";
} else {
    echo "Content Blocks not installed — skipping demo page/CEs\n";
}

// Table with nested image figures (#698 regression from #692)
// CKEditor 5 wraps tables in <figure class="table">, which our externalBlocks.figure captures.
// The inner <figure class="image"> must be re-processed through parseFunc_RTE.
// Tests: max-width on figure, zoom popup link, linked image, and image src resolution.
$bodytextTableImage = '<figure class="table"><table><tbody>'
    . '<tr><td>'
    . '<figure class="image"><img src="fileadmin/user_upload/example.jpg" alt="Table Image Zoom" width="400" height="300" data-htmlarea-file-uid="1" data-htmlarea-zoom="true" /><figcaption>Zoomable image in table</figcaption></figure>'
    . '</td><td>This cell has a zoomable image with caption</td></tr>'
    . '<tr><td>'
    . '<figure class="image"><img src="fileadmin/user_upload/example.jpg" alt="Table Image Plain" width="300" height="225" data-htmlarea-file-uid="1" /></figure>'
    . '</td><td>This cell has a plain image without caption</td></tr>'
    . '<tr><td>'
    . '<figure class="image"><a href="https://typo3.org" target="_blank"><img src="fileadmin/user_upload/example.jpg" alt="Table Image Linked" width="300" height="225" data-htmlarea-file-uid="1" /></a><figcaption>Linked image in table</figcaption></figure>'
    . '</td><td>This cell has a linked image with caption</td></tr>'
    . '</tbody></table></figure>';
$stmt->execute([1, 'text', 'Table Image (#698)', $bodytextTableImage, 0, 0, $now, $now, 0, 10752]);
echo "Table image CE created for #698\n";

// CE for #790 regression: plain RTE bodytext with NO images. Reporter
// states the symptom occurs "regardless of whether I have an image in
// the text" — so the test fixture must be image-free to faithfully
// reproduce the bug class. The bug is parseFunc_RTE.allowTags being
// set to a restrictive whitelist via addToList(...) when the default
// (in TYPO3 v13.2+) is empty. With a whitelist of just "a,figure,
// figcaption", the <p> tag isn't allowed → htmlspecialchars'd to
// &lt;p&gt; → encapsLines wraps the encoded "text" in real <p>,
// producing literal "<p>...</p>" text inside actual paragraphs.
$bodytext790 = '<p>Lorem ipsum dolor sit amet.</p><p>Another paragraph here for the regression check.</p>';
$stmt->execute([1, 'text', 'Plain RTE Bodytext (#790)', $bodytext790, 0, 0, $now, $now, 0, 11008]);
echo "Plain bodytext CE created for #790\n";

// CE for #863: CKEditor 5 image resize. The resize handles store the chosen
// size as a `width` declaration on the <figure> (class image_resized) and leave
// the <img> at its intrinsic pixel size. The frontend used to replace that
// declaration with the computed max-width, so every resized image rendered full
// width. Covers percentage, pixel, no-caption and linked variants, plus a figure
// whose style carries declarations that must never reach the output.
$bodytextResize = '<figure class="image image_resized" style="width:25%;">'
    . '<img src="fileadmin/user_upload/example.jpg" alt="Resize Percent Caption" width="400" height="300" data-htmlarea-file-uid="1" />'
    . '<figcaption>Resized to 25 percent</figcaption></figure>'
    . '<figure class="image image_resized" style="width:25%;">'
    . '<img src="fileadmin/user_upload/example.jpg" alt="Resize Percent No Caption" width="400" height="300" data-htmlarea-file-uid="1" />'
    . '</figure>'
    . '<figure class="image image_resized" style="width:120px;">'
    . '<img src="fileadmin/user_upload/example.jpg" alt="Resize Pixels Caption" width="400" height="300" data-htmlarea-file-uid="1" />'
    . '<figcaption>Resized to 120 pixels</figcaption></figure>'
    . '<figure class="image image_resized" style="width:25%;">'
    . '<a href="https://typo3.org" target="_blank"><img src="fileadmin/user_upload/example.jpg" alt="Resize Linked" width="400" height="300" data-htmlarea-file-uid="1" /></a>'
    . '<figcaption>Resized and linked</figcaption></figure>'
    . '<figure class="image image_resized" style="width:expression(alert(1));background:url(//evil.example/x);">'
    . '<img src="fileadmin/user_upload/example.jpg" alt="Resize Unsafe Style" width="400" height="300" data-htmlarea-file-uid="1" />'
    . '<figcaption>Unsafe declarations</figcaption></figure>'
    . '<figure class="image">'
    . '<img src="fileadmin/user_upload/example.jpg" alt="Resize Never Applied" width="400" height="300" data-htmlarea-file-uid="1" />'
    . '<figcaption>Never resized</figcaption></figure>';
$stmt->execute([1, 'text', 'Image Resize (#863)', $bodytextResize, 0, 0, $now, $now, 0, 11264]);
echo "Image resize CE created for #863\n";

