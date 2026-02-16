# Remove jQuery Dependency & Modernize JavaScript (#633)

## Problem Statement

`typo3image.js` (3312 lines) imports jQuery and uses it for DOM creation, AJAX, deferred
promises, and event handling. jQuery is being phased out of TYPO3 Core — the `rte_ckeditor`
sysext already has zero jQuery usage. While `import $ from 'jquery'` still works in v13/v14,
backend JavaScript is explicitly NOT covered by TYPO3's deprecation policy, meaning jQuery
could be removed in any future minor release without notice.

## Goal

Remove the jQuery dependency and modernize the JavaScript to align with TYPO3 Core patterns.
Zero behavioral changes — the dialog must look and work identically.

## Technology Decisions

| Concern | Choice | Rationale |
|---|---|---|
| Dialog DOM building | Lit `html` templates | TYPO3 ships Lit, Modal API natively accepts `TemplateResult`, declarative event binding |
| Styling | Bootstrap CSS classes (unchanged) | Already globally available in TYPO3 backend, no JS needed |
| Promises | Native `Promise` | `$.Deferred()` → `new Promise()` |
| AJAX | `fetch()` | `$.getJSON()` / `$.ajax()` → `fetch().then(r => r.json())` |
| Object merge | Spread operator | `$.extend({}, a, b)` → `{ ...a, ...b }` |
| Iteration | `for...of` / `.forEach()` | `$.each()` → native loops |
| Variables | `const` / `let` | `var` → block-scoped declarations |
| Functions | Arrow functions | `function() {}` → `() => {}` where appropriate |
| Strings | Template literals | String concatenation → backtick templates |

## Scope of jQuery Usage (72+ call sites)

### Category 1: Dialog Builder (lines 110-420) — ~50 calls

The `getImageDialog()` function builds the entire image properties modal using jQuery DOM
creation chains: `$('<div class="form-group">').appendTo(...)`. This is the bulk of the
migration.

**Current pattern:**
```javascript
var $row = $('<div class="row">').appendTo(d.$el);
var $group = $('<div class="form-group">').appendTo(
    $('<div class="' + colClass + '">').appendTo($row)
);
$('<label class="form-label" for="' + id + '">' + config.label + '</label>').appendTo($group);
```

**Target pattern (Lit):**
```javascript
import { html, render } from 'lit';

const dialogContent = html`
    <div class="row">
        <div class="${colClass}">
            <div class="form-group">
                <label class="form-label" for="${id}">${config.label}</label>
                <input type="${config.type}" id="${id}" name="${key}" class="form-control"
                       @input="${e => handleInput(key, e.target.value)}">
            </div>
        </div>
    </div>
`;
```

**Key change:** The dialog builder returns a Lit `TemplateResult` instead of a jQuery object.
`Modal.advanced({ content: templateResult })` accepts this natively since TYPO3 v12.

**Form value collection:** The `d.get()` function currently uses `$.each()` and jQuery
`.val()` to read form values. Replace with `querySelector` + `.value` on the rendered DOM,
or maintain element references via Lit's `@query` decorator / `ref()` directive.

### Category 2: `$.Deferred()` → `Promise` (3 functions)

| Function | Line | Pattern |
|---|---|---|
| `askImageAttributes()` | 784 | Creates deferred, resolves in Modal button callback |
| `selectImage()` | 878 | Creates deferred, resolves in iframe click handler |
| `openLinkBrowser()` | 1103 | Creates deferred, resolves in hidden input change handler |

**Problem:** `$.Deferred()` allows `resolve()`/`reject()` from outside the constructor.
Native `Promise` requires resolve/reject in the executor.

**Solution:** Extract resolve/reject refs:
```javascript
function askImageAttributes(editor, img, attributes, table) {
    let resolvePromise, rejectPromise;
    const promise = new Promise((resolve, reject) => {
        resolvePromise = resolve;
        rejectPromise = reject;
    });
    // ... Modal button callbacks use resolvePromise() / rejectPromise()
    return promise;
}
```

**Caller compatibility:** All callers already use `.then()` (7 call sites). Only one `.fail()`
call at line 1204 needs migration to `.catch()`.

### Category 3: AJAX (3 call sites)

| Call | Line | Replacement |
|---|---|---|
| `$.getJSON(url)` | 874 | `fetch(url).then(r => r.json())` |
| `$.getJSON(url)` | 1582 | `const response = await fetch(url).then(r => r.json())` |
| `$.ajax({ url, dataType: 'json' })` | 1118 | `fetch(url).then(r => r.json())` |

**Error handling:** `$.getJSON` rejects on HTTP errors. `fetch()` only rejects on network
failure. Add `if (!response.ok) throw new Error(...)` check.

### Category 4: `$.extend()` → Spread (3 call sites)

| Line | Current | Replacement |
|---|---|---|
| 785 | `$.extend({}, img.processed, attributes)` | `{ ...img.processed, ...attributes }` |
| 812 | `$.extend({}, img, dialogInfo)` | `{ ...img, ...dialogInfo }` |
| 830 | `$.extend(filteredAttr, { src: ..., width: ... })` | `Object.assign(filteredAttr, { src: ..., width: ... })` |

### Category 5: Modal iframe interaction (lines 894-903) — highest risk

**Current (already broken in v13+):**
```javascript
$(currentModal).find('iframe').on('load', function (e) {
    $(this).contents().on('click', '[data-filelist-element]', function (e) {
        if ($(this).data('filelist-type') !== 'file') return;
        uid: $(this).data('filelist-uid'),
    });
});
```

**Problem:** `currentModal` is a `ModalElement` (web component) in v13+, not a jQuery-wrapped
DOM node. `$(currentModal).find('iframe')` works only because jQuery can wrap any DOM element,
but this is fragile.

**Target:**
```javascript
const iframe = currentModal.querySelector('iframe');
iframe.addEventListener('load', () => {
    const doc = iframe.contentDocument;
    doc.addEventListener('click', (e) => {
        const el = e.target.closest('[data-filelist-element]');
        if (!el || el.dataset.filelistType !== 'file') return;
        const uid = el.dataset.filelistUid;
        // ...
    });
});
```

**Risk:** Cross-iframe DOM access requires same-origin. This works because the file browser
is served from the same TYPO3 backend domain.

### Category 6: jQuery event binding in dialog (lines 634-638)

```javascript
$inputWidth.on('input', debouncedUpdateQualityIndicator);
$inputHeight.on('change', updateQualityIndicator);
```

**With Lit:** Event binding moves into the template:
```javascript
html`<input @input="${debouncedUpdateQualityIndicator}" @change="${updateQualityIndicator}">`
```

### Category 7: jQuery `.val()`, `.prop()`, `.find()`, `.is()` in `d.get()` (lines 643-769)

The `d.get()` function reads form values using jQuery methods. Replace with native DOM:

| jQuery | Native |
|---|---|
| `elements[key].val()` | `el.value` |
| `$curCheckbox.prop('checked')` | `el.checked` |
| `d.$el.find('#checkbox-' + item)` | `container.querySelector('#checkbox-' + item)` |
| `$curCheckbox.is(':checked')` | `el.checked` |
| `d.$el.find('input[name="clickBehavior"]:checked').val()` | `container.querySelector('input[name="clickBehavior"]:checked')?.value` |

## Architecture Change

### Before
```
typo3image.js
├── import $ from 'jquery'
├── getImageDialog()          → returns { $el: jQuery, get: Function }
├── askImageAttributes()      → returns $.Deferred
├── selectImage()             → returns $.Deferred
├── openLinkBrowser()         → returns $.Deferred
├── getImageInfo()            → returns $.getJSON result
└── Typo3ImagePlugin.init()   → uses $.getJSON for translations
```

### After
```
typo3image.js
├── import { html, render } from 'lit'
├── getImageDialog()          → returns { el: Element, get: Function }
├── askImageAttributes()      → returns Promise
├── selectImage()             → returns Promise
├── openLinkBrowser()         → returns Promise
├── getImageInfo()            → returns fetch Promise
└── Typo3ImagePlugin.init()   → uses fetch for translations
```

The only interface change is `dialog.$el` (jQuery object) → `dialog.el` (DOM Element).
This is internal — no external API is affected.

## Migration Strategy

**Incremental, function-by-function.** Each function can be migrated independently and
verified with E2E tests. Order by risk (lowest first):

1. **`$.extend()` → spread** — 3 call sites, zero risk, instant
2. **`$.each()` → native loops** — 5 call sites, mechanical
3. **`$.getJSON()` / `$.ajax()` → fetch** — 3 call sites, add error handling
4. **`$.Deferred()` → Promise** — 3 functions, restructure constructors
5. **Modal iframe interaction** — 1 function, native DOM, verify with E2E
6. **Dialog builder → Lit** — largest change, ~300 lines rewritten
7. **`d.get()` form value collection** — depends on dialog builder migration
8. **Remove `import $ from 'jquery'`** — final step, verify nothing remains

## Acceptance Criteria

- `grep -ci 'jquery' typo3image.js` returns 0
- `grep -c '^\bvar\b' typo3image.js` returns 0 (all `var` → `const`/`let`)
- PHP unit tests: 630+ pass (unchanged)
- E2E tests: all ~185 pass on v13 AND v14
- Image dialog: opens, displays all fields, saves all attributes correctly
- Link browser: opens, selects link, populates fields, saves
- Image insertion: file browser opens, selects file, inserts into editor
- Quality indicator: updates dynamically on width/height/quality changes
- Click behavior: radio toggle shows/hides correct field sections
- Override checkboxes: toggle title/alt override correctly

## Risks and Mitigations

| Risk | Severity | Mitigation |
|---|---|---|
| Lit `TemplateResult` not accepted by Modal in some edge case | Medium | Test early with minimal Lit content in Modal; fallback to `render()` into a container element |
| `d.get()` form value collection breaks after Lit migration | Medium | Maintain element references via Lit `ref()` directive or post-render `querySelector` |
| Cross-iframe DOM access in `selectImage()` fails | Medium | Already fragile with jQuery; native equivalent is identical; E2E tests cover this |
| `fetch()` error handling differs from `$.getJSON()` | Low | Add explicit `response.ok` checks; existing error handling patterns preserved |
| Dialog layout shifts from Lit rendering timing | Low | Lit renders synchronously for initial render; no FOUC risk |
| Some callers depend on jQuery Deferred-specific API | Low | Audit found only `.then()` (7 sites) and `.fail()` (1 site) — all compatible |

## Out of Scope

- Splitting `typo3image.js` into multiple files/modules (separate issue)
- TypeScript migration (separate concern, would be a follow-up)
- Changing dialog visual appearance or UX
- Modifying CKEditor model/view converters (lines 1600-3312, no jQuery)
