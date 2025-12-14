# Security Validation Checklist: Fluid Templates Refactoring

**Created:** 2025-11-24
**Related RFC:** RFC-Fluid-Templates-Refactoring.md
**Security Review Required:** YES (Before v13.1.0 release)

## Overview

This checklist ensures all security measures from the current implementation are preserved during the Fluid Templates refactoring. **All items MUST be validated** before merging to production.

## Pre-Template Validation (ImageResolverService)

### ✅ File Visibility Checks

**Risk:** Privilege escalation - low-privilege backend editors exposing non-public files

**Current Implementation:** `AbstractImageRenderingController::validateFileVisibility()` (Classes/Controller/AbstractImageRenderingController.php:198)

**Validation:**
- [ ] Check `$file->getStorage()->isPublic()` returns true
- [ ] Throw `FileDoesNotExistException` if non-public
- [ ] Log warning with file UID, storage UID, storage name
- [ ] Test: Attempt to render image from non-public storage (must fail)
- [ ] Test: Verify proper error logging without exposing sensitive info

**Code Location:** ImageResolverService::validateFileVisibility()

**Test Cases:**
```php
// Test: Non-public file blocked
$fileUid = 123; // Points to file in non-public storage
$this->expectException(FileDoesNotExistException::class);
$dto = $imageResolverService->resolve(['data-htmlarea-file-uid' => $fileUid], ...);

// Test: Public file allowed
$fileUid = 456; // Points to file in public storage (fileadmin)
$dto = $imageResolverService->resolve(['data-htmlarea-file-uid' => $fileUid], ...);
$this->assertInstanceOf(ImageRenderingDto::class, $dto);
```

### ✅ Caption XSS Prevention

**Risk:** Cross-site scripting via user-supplied caption text

**Current Implementation:** `ImageRenderingController::wrapImageWithCaption()` (Classes/Controller/ImageRenderingController.php:391-407)
- Uses `htmlspecialchars($caption, ENT_QUOTES | ENT_HTML5, 'UTF-8')`

**Validation:**
- [ ] Caption sanitized with `htmlspecialchars()` BEFORE DTO construction
- [ ] Flags: `ENT_QUOTES | ENT_HTML5` (encodes both single/double quotes)
- [ ] Encoding: UTF-8 explicitly specified
- [ ] Test: Inject `<script>alert('XSS')</script>` in caption (must be encoded)
- [ ] Test: Inject `'"><script>` in caption (must be encoded)
- [ ] Test: Verify Fluid template does NOT use `f:format.raw()` on caption

**Code Location:** ImageResolverService::sanitizeCaption()

**Test Cases:**
```php
// Test: Script tag encoded
$maliciousCaption = '<script>alert("XSS")</script>';
$dto = $imageResolverService->resolve(['data-caption' => $maliciousCaption], ...);
$this->assertStringContainsString('&lt;script&gt;', $dto->caption);
$this->assertStringNotContainsString('<script>', $dto->caption);

// Test: Quote breaking encoded
$maliciousCaption = '"><img src=x onerror=alert(1)>';
$dto = $imageResolverService->resolve(['data-caption' => $maliciousCaption], ...);
$this->assertStringContainsString('&quot;', $dto->caption);

// Test: Unicode encoding preserved
$unicodeCaption = 'Café ☕ 中文';
$dto = $imageResolverService->resolve(['data-caption' => $unicodeCaption], ...);
$this->assertSame('Café ☕ 中文', $dto->caption); // Should NOT be double-encoded
```

### ✅ ReDoS Protection (Regex Patterns)

**Risk:** Regular expression denial of service via catastrophic backtracking

**Current Implementation:** `ImageLinkRenderingController::renderImages()` (Classes/Controller/ImageLinkRenderingController.php:82-89)
- Uses atomic groups `(?:...)` and possessive quantifiers `*+`
- Sets PCRE limits: `ini_set('pcre.backtrack_limit', '100000')`

**Validation:**
- [ ] Regex patterns use atomic groups or possessive quantifiers
- [ ] PCRE backtrack limit configured: 100,000
- [ ] PCRE recursion limit configured: 100,000
- [ ] Test: Process large HTML with nested structures (must not hang)
- [ ] Test: Verify regex completes in < 1 second for 10KB HTML

**Code Location:** ImageAttributeParser::parseAttributes()

**Patterns to Validate:**
```php
// OLD (vulnerable): /<img(.*)src(.*)\/>/
// NEW (safe): /<img(?>[^>]*)src(?>[^>]*)\/>/

// Atomic group (?>) prevents backtracking
// Possessive quantifier *+ prevents backtracking
```

**Test Cases:**
```php
// Test: Nested HTML structures
$nestedHtml = str_repeat('<p>', 1000) . '<img src="test.jpg" />' . str_repeat('</p>', 1000);
$startTime = microtime(true);
$parser->parse($nestedHtml);
$duration = microtime(true) - $startTime;
$this->assertLessThan(1.0, $duration, 'ReDoS detected: parsing took > 1 second');

// Test: Malicious backtracking pattern
$malicious = '<img ' . str_repeat('a', 10000) . '>';
$startTime = microtime(true);
$parser->parse($malicious);
$duration = microtime(true) - $startTime;
$this->assertLessThan(1.0, $duration);
```

### ✅ Type Validation

**Risk:** Type confusion attacks via unexpected attribute types

**Current Implementation:** Type guards throughout controllers (e.g., `is_numeric()` checks)

**Validation:**
- [ ] All numeric attributes validated with `is_numeric()` before casting
- [ ] String attributes validated before assignment
- [ ] DTO enforces types via constructor type hints
- [ ] Test: Pass non-numeric value for width (must handle gracefully)
- [ ] Test: Pass array instead of string for src (must handle gracefully)

**Code Location:** ImageResolverService::validateTypes()

**Test Cases:**
```php
// Test: Non-numeric width
$dto = $imageResolverService->resolve(['width' => 'invalid'], ...);
$this->assertSame(0, $dto->width); // Default to 0, don't throw

// Test: Array as string attribute
$dto = $imageResolverService->resolve(['alt' => ['array', 'value']], ...);
$this->assertSame('', $dto->alt); // Default to empty string

// Test: NULL handling
$dto = $imageResolverService->resolve(['title' => null], ...);
$this->assertNull($dto->title); // NULL allowed for optional attributes
```

### ✅ URL/Source Validation

**Risk:** SSRF (Server-Side Request Forgery) via malicious image URLs

**Current Implementation:** `ImageRenderingController::isExternalImage()` (Classes/Controller/ImageRenderingController.php:326-337)

**Validation:**
- [ ] External URLs validated (http/https/protocol-relative)
- [ ] Internal processing URLs detected (`/typo3/image/process?token`)
- [ ] File paths validated (no directory traversal)
- [ ] Test: Inject `file:///etc/passwd` as src (must reject)
- [ ] Test: Inject `../../../sensitive.jpg` (must sanitize)

**Code Location:** ImageResolverService::validateImageSource()

**Test Cases:**
```php
// Test: File protocol blocked
$this->expectException(InvalidArgumentException::class);
$dto = $imageResolverService->resolve(['src' => 'file:///etc/passwd'], ...);

// Test: Directory traversal blocked
$dto = $imageResolverService->resolve(['src' => '../../../config.php'], ...);
$this->assertStringNotContainsString('..', $dto->src);

// Test: Valid external URL allowed
$dto = $imageResolverService->resolve(['src' => 'https://example.com/image.jpg'], ...);
$this->assertStringContainsString('https://', $dto->src);
```

## Template Security (Fluid Layer)

### ✅ Auto-Escaping Enabled

**Risk:** XSS if templates use raw output format

**Validation:**
- [ ] NO use of `{variable -> f:format.raw()}` for user-supplied data
- [ ] Caption variable `{image.caption}` uses default escaping
- [ ] All attribute variables auto-escaped by Fluid
- [ ] Manual review: Check ALL `.html` templates in Resources/Private/Templates/Image/
- [ ] Test: Inject HTML in caption variable (must be escaped in output)

**Templates to Review:**
- [ ] Image/Standalone.html
- [ ] Image/WithCaption.html
- [ ] Image/Link.html
- [ ] Image/LinkWithCaption.html
- [ ] Image/Popup.html
- [ ] Image/PopupWithCaption.html

**Test Cases:**
```php
// Test: Template escaping
$maliciousCaption = '<b>Bold</b> <script>alert(1)</script>';
$dto = new ImageRenderingDto(caption: htmlspecialchars($maliciousCaption, ENT_QUOTES | ENT_HTML5));
$html = $imageRenderingService->render($dto, $request);

// Fluid should NOT double-encode (caption already escaped in DTO)
$this->assertStringContainsString('&lt;b&gt;Bold&lt;/b&gt;', $html);
$this->assertStringNotContainsString('&amp;lt;', $html); // No double-encoding
```

### ✅ Attribute Context Escaping

**Risk:** Attribute-based XSS attacks

**Validation:**
- [ ] Attributes in HTML tags properly quoted
- [ ] No user data in `<script>` or `<style>` tags
- [ ] No user data in event handlers (`onclick`, etc.)
- [ ] Test: Inject `" onload="alert(1)` in alt attribute (must be escaped)

**Test Cases:**
```php
// Test: Attribute injection blocked
$maliciousAlt = 'Image" onload="alert(1)';
$dto = new ImageRenderingDto(alt: $maliciousAlt);
$html = $imageRenderingService->render($dto, $request);
$this->assertStringNotContainsString('onload=', $html);
$this->assertStringContainsString('&quot;', $html);
```

## Integration Security

### ✅ ContentObjectRenderer Security

**Risk:** Unsafe usage of ContentObjectRenderer methods

**Current Implementation:** Uses `$this->cObj->imageLinkWrap()` for popup rendering

**Validation:**
- [ ] ContentObjectRenderer passed explicitly (not global state)
- [ ] `imageLinkWrap()` configuration validated
- [ ] No user-controlled data in typolink targets without validation
- [ ] Test: Popup configuration with malicious JS (must be sanitized by TYPO3)

**Code Location:** ImageResolverService::createPopupLink()

### ✅ TypoScript Injection Prevention

**Risk:** User-controlled TypoScript configuration

**Validation:**
- [ ] TypoScript config read from system (not user input)
- [ ] No `eval()` or dynamic TypoScript execution
- [ ] Configuration values properly type-validated
- [ ] Test: Inject malicious config (must be ignored/validated)

## Penetration Testing Scenarios

### Required Tests (Before Release)

1. **XSS via Caption:**
   ```
   Caption: <img src=x onerror=alert(document.cookie)>
   Expected: Encoded output, no script execution
   ```

2. **Privilege Escalation:**
   ```
   Action: Render image from non-public storage as non-admin user
   Expected: FileDoesNotExistException, security log entry
   ```

3. **ReDoS Attack:**
   ```
   HTML: <img aaaaa[...10000 chars]aaaa>
   Expected: Parse completes in < 1 second
   ```

4. **SSRF via External URL:**
   ```
   src: file:///etc/passwd
   Expected: Rejected with validation error
   ```

5. **Template Injection:**
   ```
   Caption: {system('whoami')}
   Expected: Literal text output, no code execution
   ```

6. **SQL Injection (Paranoid Check):**
   ```
   data-htmlarea-file-uid: 1' OR '1'='1
   Expected: Type validation prevents SQL (fileUid cast to int)
   ```

## Security Audit Checklist

### Pre-Merge Requirements
- [ ] All security validation tests passing (100% coverage)
- [ ] Penetration testing scenarios executed
- [ ] No `@codingStandardsIgnore` comments bypassing security checks
- [ ] Security review by second developer
- [ ] PHPStan level 9 passing (type safety)
- [ ] No deprecation warnings about security functions

### Documentation Requirements
- [ ] Security model documented in RFC
- [ ] Migration guide warns about custom security implementations
- [ ] Example of secure template override provided
- [ ] Changelog entry mentions security preservation

### Regression Prevention
- [ ] Security tests added to CI/CD pipeline
- [ ] Pre-commit hook runs security tests
- [ ] Automated SAST (Static Application Security Testing) configured
- [ ] Dependabot/Renovate monitors security updates

## Sign-Off

**Security Reviewer:** _________________________ Date: _________

**Lead Developer:** _________________________ Date: _________

**Release Manager:** _________________________ Date: _________

---

**CRITICAL:** This refactoring touches security-critical code. **All items must be validated** before v13.1.0 release. No exceptions.
