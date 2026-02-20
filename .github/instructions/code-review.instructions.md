# GitHub Copilot Custom Instructions

## Project Overview

**Project:** rte_ckeditor_image - TYPO3 CKEditor 5 Image Extension
**Organization:** Netresearch DTT GmbH
**License:** AGPL-3.0-or-later

This extension provides advanced image handling for CKEditor 5 in TYPO3, including:
- Block and inline image support with `typo3image` and `typo3imageInline` model elements
- Click-to-enlarge (lightbox) functionality
- Image linking with custom attributes
- Style integration via StyleUtils and GeneralHtmlSupport
- FAL (File Abstraction Layer) integration

## Code Review Focus Areas

### PHP Code (Classes/)

**Required Patterns:**
- `declare(strict_types=1);` must be first statement after `<?php`
- All parameters and return types must have type declarations
- Constructor-based dependency injection (no `new ClassName()` or `GeneralUtility::makeInstance()`)
- Use `readonly` for immutable constructor properties
- PSR-7 Request/Response interfaces for controllers
- FAL for all file operations (never direct file system access)

**Check for:**
- Missing type hints on parameters or return types
- Direct file system access instead of FAL/ResourceFactory
- Manual instantiation instead of DI
- Global state access (`$GLOBALS`, superglobals)
- Missing validation of input parameters
- XSS vulnerabilities (unescaped output)

**Example of correct controller pattern:**
```php
<?php
declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Resource\ResourceFactory;

final class ExampleController
{
    public function __construct(
        private readonly ResourceFactory $resourceFactory
    ) {}

    public function action(ServerRequestInterface $request): ResponseInterface
    {
        $fileUid = (int)($request->getQueryParams()['fileId'] ?? 0);
        // Always validate before use
        if ($fileUid <= 0) {
            return new JsonResponse(['error' => 'Invalid file ID'], 400);
        }
        // Use FAL for file access
        $file = $this->resourceFactory->getFileObject($fileUid);
        return new JsonResponse(['uid' => $file->getUid()]);
    }
}
```

### JavaScript Code (Resources/Public/JavaScript/)

**CKEditor 5 Plugin Requirements:**
- `static pluginName` must be defined
- `static get requires()` must include `['StyleUtils', 'GeneralHtmlSupport']` for style support
- Both upcast (HTML→model) and downcast (model→HTML) conversions required
- Model schema must define `allowAttributes` explicitly

**Check for:**
- Missing `requires()` method (breaks style integration)
- Incomplete conversions (causes data loss)
- Missing attribute handling in downcast (e.g., `imageLinkParams` → `data-link-params`)
- Incorrect class matching (use exact match, not substring includes)
- Missing element consumption in upcast converters (causes duplicate wrappers)
- Hardcoded URLs instead of using editor config routes

**Common CKEditor Issues:**
1. **Linked images losing attributes:** Ensure upcast extracts ALL attributes from both `<a>` and `<img>` elements
2. **Inline vs block detection:** Use `viewElement.hasClass('image-inline')` or split class string into array, NOT `className.includes('image-inline')` which matches substrings
3. **Caption handling:** Inline images (`typo3imageInline`) cannot have captions - check for figcaption before creating inline element
4. **Link parameters:** `imageLinkParams` must be serialized to `data-link-params` in downcast

**Example of correct upcast pattern:**
```javascript
editor.conversion.for('upcast').elementToElement({
    view: {
        name: 'img',
        attributes: ['data-htmlarea-file-uid']
    },
    model: (viewElement, { writer, consumable }) => {
        // Check class for inline vs block
        const className = viewElement.getAttribute('class') || '';
        const classes = className.split(/\s+/);
        const isInline = classes.includes('image-inline');

        // Consume the element to prevent duplicate processing
        consumable.consume(viewElement, { name: true, attributes: true });

        return writer.createElement(
            isInline ? 'typo3imageInline' : 'typo3image',
            { /* attributes */ }
        );
    }
});
```

### Testing (Tests/)

**Requirements:**
- Use `#[Test]` attribute (not `@test` annotation)
- Follow AAA pattern: Arrange, Act, Assert
- Descriptive test names: `methodNameExpectedBehaviorWhenCondition`
- Functional tests require database via ddev
- All tests must pass before merge

**Check for:**
- Missing assertions (test that doesn't assert anything)
- Unused imports or functions
- Missing `declare(strict_types=1);`
- Tests that depend on external state

### TypoScript (Configuration/TypoScript/)

**Check for:**
- Proper parseFunc_RTE configuration for image processing
- Correct preUserFunc assignments to ImageRenderingAdapter methods
- externalBlocks configuration for figure and link elements

## Security Checklist

1. **Input Validation:** All user input must be validated and type-cast
2. **FAL Usage:** Never access files directly; use ResourceFactory
3. **XSS Prevention:** Use Fluid templates (auto-escape) or proper encoding
4. **URL Encoding:** Use `encodeURIComponent()` for URL parameters
5. **Route URLs:** Get from editor config, never hardcode

## Common Pitfalls to Flag

### PHP
- `@var` annotations without actual type hints
- Catching `\Exception` without re-throwing or proper handling
- Missing `final` keyword on classes that shouldn't be extended
- PHPDoc that doesn't match actual types

### JavaScript
- Missing semicolons (inconsistent with codebase style)
- Using `includes()` for exact class name matching
- Not consuming elements in upcast converters
- Missing attribute mappings in downcast (data loss)
- Event listeners without cleanup

### General
- PRs with >300 lines changed (should be split)
- Missing test coverage for new features
- Commits not following conventional format (`type(scope): message`)

## Quality Gates

All code must pass before merge:
```bash
composer ci:test           # Full CI suite
composer ci:test:php:unit  # Unit tests
composer ci:test:php:phpstan  # Static analysis (level 9)
composer ci:cgl            # Code style
```

## Architecture Notes

### Image Model Elements
- `typo3image` - Block images (can have captions, figure wrapper)
- `typo3imageInline` - Inline images (flow with text, no captions)

### Image Rendering Flow
1. CKEditor saves HTML with `data-htmlarea-file-uid` attributes
2. parseFunc_RTE processes content via ImageRenderingAdapter
3. `renderImageAttributes()` handles standalone `<img>` tags
4. `renderFigure()` handles `<figure>` wrapped images
5. `renderInlineLink()` handles `<a>` tags (resolves t3:// URLs, validates protocols)

### Link Attributes
Model attributes use `imageLink*` prefix:
- `imageLinkHref` → `href`
- `imageLinkTarget` → `target`
- `imageLinkTitle` → `title`
- `imageLinkClass` → `class`
- `imageLinkParams` → `data-link-params`
