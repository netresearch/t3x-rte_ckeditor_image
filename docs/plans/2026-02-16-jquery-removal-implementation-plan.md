# jQuery Removal & JavaScript Modernization Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Remove all jQuery dependency from `typo3image.js` and modernize to ES2020+ patterns (Lit templates, native Promise, fetch API, const/let, arrow functions, template literals).

**Architecture:** Incremental function-by-function migration. Each task targets one jQuery pattern category, verified by the existing E2E test suite (~185 tests). The dialog builder (largest change) is migrated last after all supporting functions are jQuery-free. The final task removes `import $ from 'jquery'`.

**Tech Stack:** Lit `html` templates (TYPO3-shipped), native `Promise`, `fetch()` API, ES2020+ JavaScript

**Design Doc:** `docs/plans/2026-02-16-jquery-removal-js-modernization-design.md`

---

## File Map

| File | Role |
|---|---|
| `Resources/Public/JavaScript/Plugins/typo3image.js` | **Primary target** — all changes happen here |
| `Tests/E2E/tests/*.spec.ts` (24 spec files) | **Verification** — run after every task |

## Verification Commands

After **every** task, run:

```bash
# Quick local syntax check (ensures no parse errors)
node --check Resources/Public/JavaScript/Plugins/typo3image.js

# Full E2E verification (CI will run this; local optional)
Build/Scripts/runTests.sh -s e2e -t 13 -p 8.5
```

---

### Task 1: Create feature branch and replace `$.extend()` with spread/Object.assign

**Files:**
- Modify: `Resources/Public/JavaScript/Plugins/typo3image.js:785,812,830`

**Step 1: Create feature branch**

```bash
cd /home/cybot/projects/t3x-rte_ckeditor_image
git checkout -b feat/remove-jquery-633
```

**Step 2: Replace 3 `$.extend()` call sites**

Line 785 — merging processed attributes with user attributes:
```javascript
// BEFORE:
var dialog = getImageDialog(editor, img, $.extend({}, img.processed, attributes));

// AFTER:
var dialog = getImageDialog(editor, img, { ...img.processed, ...attributes });
```

Line 812 — merging image data with dialog info:
```javascript
// BEFORE:
attributesNew = $.extend({}, img, dialogInfo);

// AFTER:
attributesNew = { ...img, ...dialogInfo };
```

Line 830 — mutating filteredAttr in-place:
```javascript
// BEFORE:
$.extend(filteredAttr, {
    src: urlToRelative(getImg.url, getImg.storageDriver),
    width: userWidth || getImg.processed.width || getImg.width,
    height: userHeight || getImg.processed.height || getImg.height,
    fileUid: img.uid,
    fileTable: table
});

// AFTER:
Object.assign(filteredAttr, {
    src: urlToRelative(getImg.url, getImg.storageDriver),
    width: userWidth || getImg.processed.width || getImg.width,
    height: userHeight || getImg.processed.height || getImg.height,
    fileUid: img.uid,
    fileTable: table
});
```

**Step 3: Verify no parse errors**

```bash
node --check Resources/Public/JavaScript/Plugins/typo3image.js
```
Expected: No output (success).

**Step 4: Commit**

```bash
git add Resources/Public/JavaScript/Plugins/typo3image.js
git commit -S -m "refactor: replace $.extend() with spread/Object.assign (#633)

Migrate 3 call sites:
- Line 785: $.extend({}, a, b) -> { ...a, ...b }
- Line 812: $.extend({}, a, b) -> { ...a, ...b }
- Line 830: $.extend(target, source) -> Object.assign(target, source)"
```

---

### Task 2: Replace `$.each()` with native loops

**Files:**
- Modify: `Resources/Public/JavaScript/Plugins/typo3image.js:112,116,241,644,645`

**Step 1: Replace 5 `$.each()` call sites**

Lines 112-116 — outer and inner field iteration in `getImageDialog()`:
```javascript
// BEFORE:
$.each(fields, function () {
    var $row = $('<div class="row">').appendTo(d.$el);
    $rows.push($row);
    $.each(this, function (key, config) {
        // ... body uses `key` and `config`
    });
});

// AFTER:
for (const fieldGroup of fields) {
    var $row = $('<div class="row">').appendTo(d.$el);
    $rows.push($row);
    for (const [key, config] of Object.entries(fieldGroup)) {
        // ... body uses `key` and `config` (identical)
    }
}
```

**IMPORTANT:** Inside the inner loop, `this` referred to the current value (the config object). With `Object.entries`, the destructured `config` variable replaces `this`. Verify no other references to `this` exist inside these loops — there are none (checked: `this` only appears in the jQuery callback context).

Line 241 — quality options iteration:
```javascript
// BEFORE:
$.each(qualityOptions, function(i, option) {
    var $option = $('<option>')
        .val(option.value)
        .text(option.marker + ' ' + option.label)
        .data('multiplier', option.multiplier)
        .data('color', option.color)
        .css('color', option.color);
    $option.appendTo($el);
});

// AFTER:
for (const option of qualityOptions) {
    var $option = $('<option>')
        .val(option.value)
        .text(option.marker + ' ' + option.label)
        .data('multiplier', option.multiplier)
        .data('color', option.color)
        .css('color', option.color);
    $option.appendTo($el);
}
```

Lines 644-645 — form value collection in `d.get()`:
```javascript
// BEFORE:
$.each(fields, function () {
    $.each(this, function (key) {
        var value = elements[key].val();
        if (typeof value !== 'undefined') {
            attributes[key] = value;
        }
    });
});

// AFTER:
for (const fieldGroup of fields) {
    for (const key of Object.keys(fieldGroup)) {
        var value = elements[key].val();
        if (typeof value !== 'undefined') {
            attributes[key] = value;
        }
    }
}
```

**Step 2: Verify no parse errors**

```bash
node --check Resources/Public/JavaScript/Plugins/typo3image.js
```

**Step 3: Commit**

```bash
git add Resources/Public/JavaScript/Plugins/typo3image.js
git commit -S -m "refactor: replace $.each() with native for...of loops (#633)

Migrate 5 call sites in getImageDialog() and d.get():
- $.each(array, fn) -> for (const item of array)
- $.each(object, fn(key, val)) -> for (const [key, val] of Object.entries(obj))
- Inner $.each(this, fn) -> for (const key of Object.keys(fieldGroup))"
```

---

### Task 3: Replace `$.getJSON()` and `$.ajax()` with `fetch()`

**Files:**
- Modify: `Resources/Public/JavaScript/Plugins/typo3image.js:858-874,1117-1121,1573-1592`

**Step 1: Replace `getImageInfo()` (line 874)**

```javascript
// BEFORE (line 858-874):
function getImageInfo(editor, table, uid, params) {
    let url = editor.config.get('typo3image').routeUrl + '&action=info&fileId=' + encodeURIComponent(uid) + '&table=' + encodeURIComponent(table);
    // ... url param building ...
    return $.getJSON(url);
}

// AFTER:
function getImageInfo(editor, table, uid, params) {
    let url = editor.config.get('typo3image').routeUrl + '&action=info&fileId=' + encodeURIComponent(uid) + '&table=' + encodeURIComponent(table);
    // ... url param building unchanged ...
    return fetch(url).then(response => {
        if (!response.ok) {
            throw new Error(`Image info request failed: ${response.status}`);
        }
        return response.json();
    });
}
```

**Why `throw` on `!response.ok`:** `$.getJSON()` rejects on HTTP 4xx/5xx. `fetch()` only rejects on network failure. The explicit check preserves the same error behavior for callers using `.then()`.

**Step 2: Replace `$.ajax()` in `openLinkBrowser()` (lines 1117-1121)**

```javascript
// BEFORE:
$.ajax({
    url: linkBrowserActionUrl,
    dataType: 'json'
}).then(function(response) {
    // ... handler body unchanged ...
}).fail(function(xhr, status, error) {
    console.error('Failed to get link browser URL:', error);
    deferred.reject('Failed to get link browser URL');
});

// AFTER:
fetch(linkBrowserActionUrl).then(response => {
    if (!response.ok) {
        throw new Error(`Link browser request failed: ${response.status}`);
    }
    return response.json();
}).then(function(response) {
    // ... handler body unchanged ...
}).catch(function(error) {
    console.error('Failed to get link browser URL:', error);
    deferred.reject('Failed to get link browser URL');
});
```

**Key change:** `.fail()` (jQuery-specific) becomes `.catch()` (standard Promise).

**Step 3: Replace `$.getJSON()` in `getTranslations()` (line 1582)**

```javascript
// BEFORE:
const response = await $.getJSON(url);

// AFTER:
const fetchResponse = await fetch(url);
if (!fetchResponse.ok) {
    throw new Error(`Translations request failed: ${fetchResponse.status}`);
}
const response = await fetchResponse.json();
```

**Step 4: Verify no parse errors**

```bash
node --check Resources/Public/JavaScript/Plugins/typo3image.js
```

**Step 5: Commit**

```bash
git add Resources/Public/JavaScript/Plugins/typo3image.js
git commit -S -m "refactor: replace $.getJSON() and $.ajax() with fetch() (#633)

Migrate 3 AJAX call sites:
- getImageInfo(): $.getJSON(url) -> fetch(url).then(r => r.json())
- openLinkBrowser(): $.ajax({url, dataType}) -> fetch(url).then(r => r.json())
- getTranslations(): await $.getJSON(url) -> await fetch(url).then(r => r.json())

All replacements add response.ok check to match jQuery's HTTP error rejection."
```

---

### Task 4: Replace `$.Deferred()` with native `Promise`

**Files:**
- Modify: `Resources/Public/JavaScript/Plugins/typo3image.js` — functions `askImageAttributes` (783-847), `selectImage` (877-914), `openLinkBrowser` (1102-1210)

This is the most structurally complex migration. `$.Deferred()` allows calling `resolve()`/`reject()` from outside the constructor. Native `Promise` requires resolve/reject refs to be extracted.

**Step 1: Migrate `askImageAttributes()` (lines 783-847)**

```javascript
// BEFORE:
function askImageAttributes(editor, img, attributes, table) {
    var deferred = $.Deferred();
    var dialog = getImageDialog(editor, img, { ...img.processed, ...attributes });

    const modal = Modal.advanced({
        // ...
        buttons: [
            {
                // Cancel button
                trigger: function () {
                    modal.hideModal();
                    deferred.reject();
                }
            },
            {
                // Save button
                trigger: function () {
                    // ... builds filteredAttr ...
                    getImageInfo(editor, table, img.uid, filteredAttr)
                        .then(function (getImg) {
                            // ...
                            modal.hideModal('hide');
                            deferred.resolve(filteredAttr);
                        });
                }
            }
        ]
    });

    return deferred;
}

// AFTER:
function askImageAttributes(editor, img, attributes, table) {
    let resolvePromise, rejectPromise;
    const promise = new Promise((resolve, reject) => {
        resolvePromise = resolve;
        rejectPromise = reject;
    });

    var dialog = getImageDialog(editor, img, { ...img.processed, ...attributes });

    const modal = Modal.advanced({
        // ...
        buttons: [
            {
                // Cancel button
                trigger: function () {
                    modal.hideModal();
                    rejectPromise();
                }
            },
            {
                // Save button
                trigger: function () {
                    // ... builds filteredAttr (unchanged) ...
                    getImageInfo(editor, table, img.uid, filteredAttr)
                        .then(function (getImg) {
                            // ...
                            modal.hideModal('hide');
                            resolvePromise(filteredAttr);
                        });
                }
            }
        ]
    });

    return promise;
}
```

**Step 2: Migrate `selectImage()` (lines 877-914)**

```javascript
// BEFORE:
function selectImage(editor) {
    const deferred = $.Deferred();
    // ...
    const modal = Modal.advanced({
        // ...
        callback: function (currentModal) {
            $(currentModal).find('iframe').on('load', function (e) {
                $(this).contents().on('click', '[data-filelist-element]', function (e) {
                    // ...
                    deferred.resolve(selectedItem);
                });
            });
        }
    });
    return deferred;
}

// AFTER:
function selectImage(editor) {
    let resolvePromise;
    const promise = new Promise((resolve, reject) => {
        resolvePromise = resolve;
    });
    // ...
    const modal = Modal.advanced({
        // ...
        callback: function (currentModal) {
            // NOTE: iframe jQuery migration happens in Task 5
            $(currentModal).find('iframe').on('load', function (e) {
                $(this).contents().on('click', '[data-filelist-element]', function (e) {
                    // ...
                    resolvePromise(selectedItem);
                });
            });
        }
    });
    return promise;
}
```

**Step 3: Migrate `openLinkBrowser()` (lines 1102-1210)**

```javascript
// BEFORE:
function openLinkBrowser(editor, currentValue) {
    const deferred = $.Deferred();

    // Early rejection
    if (!baseUrl) {
        deferred.reject('Link browser route not configured');
        return deferred;
    }

    // ... fetch().then(response => { ...
    //     deferred.resolve(linkData);
    // }).catch(error => {
    //     deferred.reject('Failed to get link browser URL');
    // });

    // Modal close handler:
    if (deferred.state() === 'pending') {
        deferred.reject();
    }

    return deferred;
}

// AFTER:
function openLinkBrowser(editor, currentValue) {
    let resolvePromise, rejectPromise;
    let settled = false;  // Replaces deferred.state() === 'pending'
    const promise = new Promise((resolve, reject) => {
        resolvePromise = (value) => { settled = true; resolve(value); };
        rejectPromise = (reason) => { settled = true; reject(reason); };
    });

    const baseUrl = editor.config.get('typo3image').routeUrl;
    if (!baseUrl) {
        console.error('typo3image.routeUrl not configured');
        rejectPromise('Link browser route not configured');
        return promise;
    }

    // ... fetch().then(response => { ...
    //     resolvePromise(linkData);    // was: deferred.resolve(linkData)
    // }).catch(error => {
    //     rejectPromise('Failed...');  // was: deferred.reject('Failed...')
    // });

    // Modal close handler:
    // BEFORE: if (deferred.state() === 'pending') { deferred.reject(); }
    // AFTER:
    if (!settled) {
        rejectPromise();
    }

    return promise;
}
```

**Key detail:** `deferred.state() === 'pending'` has no native equivalent. We use a `settled` boolean flag, wrapped into the resolve/reject functions so it's automatically tracked.

**Step 4: Verify no parse errors**

```bash
node --check Resources/Public/JavaScript/Plugins/typo3image.js
```

**Step 5: Commit**

```bash
git add Resources/Public/JavaScript/Plugins/typo3image.js
git commit -S -m "refactor: replace $.Deferred() with native Promise (#633)

Migrate 3 functions:
- askImageAttributes(): extract resolve/reject refs from Promise constructor
- selectImage(): extract resolve ref from Promise constructor
- openLinkBrowser(): extract resolve/reject refs + settled flag for state check

All callers already use .then() -- no caller changes needed.
deferred.state() === 'pending' -> settled boolean flag."
```

---

### Task 5: Replace iframe jQuery with native DOM in `selectImage()`

**Files:**
- Modify: `Resources/Public/JavaScript/Plugins/typo3image.js:893-909`

This is the highest-risk migration — cross-iframe DOM access with jQuery.

**Step 1: Replace jQuery iframe interaction**

```javascript
// BEFORE (lines 893-909):
callback: function (currentModal) {
    $(currentModal).find('iframe').on('load', function (e) {
        $(this).contents().on('click', '[data-filelist-element]', function (e) {
            e.stopImmediatePropagation();

            if ($(this).data('filelist-type') !== 'file') {
                return;
            }

            const selectedItem = {
                uid: $(this).data('filelist-uid'),
                table: 'sys_file',
            }
            currentModal.hideModal();
            resolvePromise(selectedItem);
        });
    });
}

// AFTER:
callback: function (currentModal) {
    const iframe = currentModal.querySelector('iframe');
    if (!iframe) return;

    iframe.addEventListener('load', () => {
        const doc = iframe.contentDocument;
        if (!doc) return;

        doc.addEventListener('click', (e) => {
            const el = e.target.closest('[data-filelist-element]');
            if (!el || el.dataset.filelistType !== 'file') return;

            e.stopImmediatePropagation();

            const selectedItem = {
                uid: el.dataset.filelistUid,
                table: 'sys_file',
            };
            currentModal.hideModal();
            resolvePromise(selectedItem);
        });
    });
}
```

**Key changes:**
- `$(currentModal).find('iframe')` becomes `currentModal.querySelector('iframe')` — `currentModal` is a `ModalElement` (web component), which supports `querySelector` natively
- `$(this).contents().on('click', ...)` becomes `iframe.contentDocument.addEventListener('click', ...)` — delegated click handler on the document instead of directly binding to elements
- `$(this).data('filelist-type')` becomes `el.dataset.filelistType` — native `dataset` API (note: camelCase conversion from `data-filelist-type`)
- `$(this).data('filelist-uid')` becomes `el.dataset.filelistUid`
- Event delegation via `e.target.closest('[data-filelist-element]')` replaces jQuery's delegated `.on(selector, handler)` pattern

**Step 2: Verify no parse errors**

```bash
node --check Resources/Public/JavaScript/Plugins/typo3image.js
```

**Step 3: Commit**

```bash
git add Resources/Public/JavaScript/Plugins/typo3image.js
git commit -S -m "refactor: replace iframe jQuery with native DOM in selectImage() (#633)

Replace cross-iframe jQuery interaction with native DOM API:
- $(modal).find('iframe') -> modal.querySelector('iframe')
- $(iframe).contents().on() -> iframe.contentDocument.addEventListener()
- $(el).data('key') -> el.dataset.camelCaseKey
- jQuery delegated event -> e.target.closest() pattern"
```

---

### Task 6: Push and verify E2E in CI

**Step 1: Push feature branch**

```bash
git push -u origin feat/remove-jquery-633
```

**Step 2: Create draft PR**

```bash
gh pr create --draft --title "refactor: remove jQuery dependency and modernize JS (#633)" --body "$(cat <<'EOF'
## Summary
- Replace `$.extend()` with spread operator / `Object.assign()`
- Replace `$.each()` with native `for...of` loops
- Replace `$.getJSON()` / `$.ajax()` with `fetch()` API
- Replace `$.Deferred()` with native `Promise`
- Replace iframe jQuery with native DOM API

WIP -- dialog builder Lit migration and var->const/let pending.

## Test plan
- [ ] E2E v13: all ~185 tests pass
- [ ] E2E v14: all tests pass
- [ ] Image dialog opens and saves correctly
- [ ] Link browser opens and returns link data
- [ ] File browser opens and selects files
- [ ] Quality indicator updates dynamically
EOF
)"
```

**Step 3: Wait for CI to pass**

All 24 E2E spec files must pass on both v13 and v14. If any fail, debug before proceeding — these first 5 tasks are prerequisite for the dialog builder migration.

**Step 4: Commit checkpoint note (no code change)**

If CI passes, continue to Task 7. If CI fails, fix and amend.

---

### Task 7: Migrate dialog builder to Lit `html` templates

**Files:**
- Modify: `Resources/Public/JavaScript/Plugins/typo3image.js:16-21,79-772`

This is the largest single change (~700 lines). The `getImageDialog()` function currently builds DOM imperatively with jQuery chains. It will be rewritten to use Lit `html` tagged template literals.

**Step 1: Add Lit import, keep jQuery import for now**

```javascript
// BEFORE (lines 16-21):
import { Plugin, Command } from '@ckeditor/ckeditor5-core';
import { ButtonView } from '@ckeditor/ckeditor5-ui';
import { DomEventObserver } from '@ckeditor/ckeditor5-engine';
import { toWidget, toWidgetEditable, WidgetToolbarRepository } from '@ckeditor/ckeditor5-widget';
import { default as Modal } from '@typo3/backend/modal.js';
import $ from 'jquery';

// AFTER:
import { Plugin, Command } from '@ckeditor/ckeditor5-core';
import { ButtonView } from '@ckeditor/ckeditor5-ui';
import { DomEventObserver } from '@ckeditor/ckeditor5-engine';
import { toWidget, toWidgetEditable, WidgetToolbarRepository } from '@ckeditor/ckeditor5-widget';
import { default as Modal } from '@typo3/backend/modal.js';
import { html, render } from 'lit';
import $ from 'jquery';  // TEMPORARY: still used in d.get() until Task 8
```

**Step 2: Rewrite `getImageDialog()` — return structure change**

The function currently returns `{ $el: jQuery, get: Function }`. After migration it returns `{ el: Element, get: Function }`.

**Strategy:** Build the entire dialog as a Lit `TemplateResult`, render it into a container `<div>`, and store element references via `querySelector` after rendering. This is necessary because Lit renders synchronously on first call to `render()`, so all elements are immediately available.

```javascript
function getImageDialog(editor, img, attributes) {
    const elements = {};
    const rows = [];

    const fields = [
        { width: { label: 'Display width in px', type: 'number' },
          height: { label: 'Display height in px', type: 'number' },
          quality: { label: 'Scaling', type: 'select' } },
        { title: { label: 'Advisory Title', type: 'text' } },
        { alt: { label: 'Alternative Text', type: 'text' } },
        { caption: { label: 'Caption', type: 'textarea', rows: 2 } }
    ];

    // ... config extraction unchanged (maxConfigWidth, maxConfigHeight, isSvg) ...

    // Render into a container element
    const container = document.createElement('div');
    container.className = 'rteckeditorimage';

    // Build template using Lit html tagged template literals
    // (Full template implementation below)
    const template = html`
        ${fields.map(fieldGroup => {
            // ... field rendering ...
        })}
        <!-- quality indicator -->
        <div class="image-quality-indicator" style="margin: 12px 0; font-size: 13px; line-height: 1.6;"></div>
        <!-- click behavior section -->
        <!-- ... -->
    `;

    render(template, container);

    // Collect element references after render
    for (const fieldGroup of fields) {
        for (const key of Object.keys(fieldGroup)) {
            elements[key] = container.querySelector(`[name="${key}"]`)
                || container.querySelector(`#rteckeditorimage-${key}`);
        }
    }

    // Wire up event handlers on actual DOM elements
    // ... (constrainDimensions, quality indicator, click behavior toggle) ...

    return {
        el: container,  // Changed from $el (jQuery) to el (Element)
        get: function () { /* ... */ }
    };
}
```

**CRITICAL implementation details for the Lit template:**

1. **Form fields template:** Each field group becomes a `<div class="row">` with field entries:

```javascript
const renderField = (key, config) => {
    const id = `rteckeditorimage-${key}`;
    const colClass = (key === 'title' || key === 'alt' || key === 'caption')
        ? 'col-xs-12' : 'col-xs-12 col-sm-4';
    const placeholder = (config.type === 'text' ? (img[key] || '') : img.processed[key]) + '';
    const value = ((attributes[key] || '') + '').trim();

    return html`
        <div class="${colClass}">
            <div class="form-group">
                <label class="form-label" for="${id}">${config.label}</label>
                ${config.type === 'select' ? html`
                    <select id="${id}" name="${key}" class="form-select"></select>
                ` : config.type === 'textarea' ? html`
                    <textarea id="${id}" name="${key}" class="form-control"
                        rows="${config.rows || 3}" placeholder="${placeholder}">${value}</textarea>
                ` : html`
                    <input type="${config.type}" id="${id}" name="${key}" class="form-control"
                        placeholder="${placeholder}" .value="${value}">
                `}
                ${(config.type === 'text' || config.type === 'textarea')
                    ? renderOverrideCheckbox(key, config, value)
                    : config.type === 'number'
                        ? '' /* number constraints added post-render */
                        : config.type === 'select' && key === 'quality'
                            ? '' /* quality options added post-render */
                            : ''}
            </div>
        </div>
    `;
};
```

2. **Override checkbox template:** For title/alt fields:

```javascript
const renderOverrideCheckbox = (key, config, value) => {
    const hasDefault = img[key] && img[key].trim();
    const isChecked = !!value || !hasDefault;
    const noDefaultMsg = img.lang.noDefaultMetadata.replace('%s', key);

    return html`
        <div class="form-check form-check-type-toggle" style="margin: 0 0 6px;">
            <input type="checkbox" class="form-check-input" id="checkbox-${key}"
                ?checked="${isChecked}" ?disabled="${!hasDefault}"
                title="${!hasDefault ? noDefaultMsg : ''}">
            <label class="form-check-label" for="checkbox-${key}"
                style="${!hasDefault ? 'cursor: not-allowed' : ''}"
                title="${!hasDefault ? noDefaultMsg : ''}">
                ${hasDefault ? img.lang.override.replace('%s', img[key]) : img.lang.overrideNoDefault}
            </label>
        </div>
    `;
};
```

3. **Click behavior section template:**

```javascript
const renderClickBehaviorSection = () => {
    const initialBehavior = (attributes['data-htmlarea-zoom'] || attributes['data-htmlarea-clickenlarge'])
        ? 'enlarge'
        : (attributes.linkHref && attributes.linkHref.trim() !== '') ? 'link' : 'none';

    return html`
        <div class="row" style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #dee2e6;">
            <div class="col-xs-12" style="margin-bottom: 12px;">
                <strong>${img.lang.clickBehavior || 'Click Behavior'}</strong>
            </div>
            <div class="col-xs-12" style="margin-bottom: 12px;">
                ${renderRadioOption('none', initialBehavior, img.lang.clickBehaviorNone || 'None - image is not clickable')}
                ${renderRadioOption('enlarge', initialBehavior, img.lang.clickBehaviorEnlarge || 'Enlarge - opens full-size in lightbox')}
                ${renderRadioOption('link', initialBehavior, img.lang.clickBehaviorLink || 'Link - opens custom URL')}
                ${attributes.isInsideExternalLink ? html`
                    <div class="alert alert-info" style="margin-top: 12px; padding: 10px; font-size: 13px;">
                        <strong>${img.lang.imageInsideLinkTitle || 'Image is inside a link'}</strong><br>
                        ${img.lang.imageInsideLinkMessage || 'Click behavior options are disabled because this image is inside a link...'}
                    </div>
                ` : ''}
            </div>
            <div class="col-xs-12" id="clickBehavior-fields">
                ${renderEnlargeFields(initialBehavior)}
                ${renderLinkFields()}
            </div>
        </div>
    `;
};
```

4. **Post-render event wiring:** After `render(template, container)`, wire up:
   - Number input constraints (`constrainDimensions`)
   - Quality dropdown options population and event handlers
   - Click behavior radio toggle
   - Override checkbox click handlers
   - Browse button handler
   - Quality indicator initial render

**Step 3: Update `askImageAttributes()` to use `dialog.el` instead of `dialog.$el`**

```javascript
// BEFORE:
const modal = Modal.advanced({
    title: img.lang.imageProperties,
    content: dialog.$el,
    // ...
});

// AFTER:
const modal = Modal.advanced({
    title: img.lang.imageProperties,
    content: dialog.el,
    // ...
});
```

**Note:** `Modal.advanced({ content: ... })` accepts both jQuery objects, DOM Elements, and Lit `TemplateResult`. We're passing a DOM Element here (the container div).

**Step 4: Verify no parse errors**

```bash
node --check Resources/Public/JavaScript/Plugins/typo3image.js
```

**Step 5: Commit**

```bash
git add Resources/Public/JavaScript/Plugins/typo3image.js
git commit -S -m "refactor: migrate dialog builder to Lit html templates (#633)

Rewrite getImageDialog() from jQuery DOM creation chains to Lit html
tagged template literals rendered into a container element.

- ~50 jQuery DOM creation calls -> Lit html templates
- dialog.\$el (jQuery) -> dialog.el (Element)
- Post-render event wiring via querySelector on rendered DOM
- Modal.advanced({ content: dialog.el }) accepts DOM Element natively"
```

---

### Task 8: Migrate `d.get()` form value collection to native DOM

**Files:**
- Modify: `Resources/Public/JavaScript/Plugins/typo3image.js` — `d.get()` function and all jQuery `.val()`, `.prop()`, `.find()`, `.is()` calls within `getImageDialog()`

After Task 7, the dialog uses native DOM elements but `d.get()` still uses jQuery methods on them. This task replaces those.

**Step 1: Replace form value reads in `d.get()`**

```javascript
// BEFORE:
d.get = function () {
    for (const fieldGroup of fields) {
        for (const key of Object.keys(fieldGroup)) {
            var value = elements[key].val();    // jQuery .val()
            // ...
        }
    }
    var selectedClickBehavior = d.$el.find('input[name="clickBehavior"]:checked').val();
    var enlargeCssVal = $inputCssClassEnlarge.val();
    var qualityValue = $qualityDropdown.val();
    if ($checkboxTitle.length && !$checkboxTitle.is(":checked")) { ... }
    var $curCheckbox = d.$el.find('#checkbox-' + item);
    attributes['data-' + item + '-override'] = $curCheckbox.prop('checked');
};

// AFTER:
d.get = function () {
    for (const fieldGroup of fields) {
        for (const key of Object.keys(fieldGroup)) {
            const value = elements[key].value;   // native .value
            // ...
        }
    }
    const selectedRadio = container.querySelector('input[name="clickBehavior"]:checked');
    const selectedClickBehavior = selectedRadio ? selectedRadio.value : 'none';
    const enlargeCssVal = enlargeCssClassInput.value;
    const qualityValue = qualityDropdown.value;
    const titleCheckbox = container.querySelector('#checkbox-title');
    if (titleCheckbox && !titleCheckbox.checked) { ... }
    const curCheckbox = container.querySelector('#checkbox-' + item);
    attributes['data-' + item + '-override'] = curCheckbox ? curCheckbox.checked : false;
};
```

**Step 2: Replace all remaining jQuery method calls in getImageDialog()**

Within the post-render event wiring and other handlers, replace:

| jQuery | Native |
|---|---|
| `$el.val()` | `el.value` |
| `$el.val(newVal)` | `el.value = newVal` |
| `$el.prop('checked')` | `el.checked` |
| `$el.prop('checked', true)` | `el.checked = true` |
| `$el.prop('disabled', val)` | `el.disabled = val` |
| `$el.attr('max', val)` | `el.max = val` |
| `$el.attr('min', val)` | `el.min = val` |
| `$el.attr('placeholder', val)` | `el.placeholder = val` |
| `$el.is(':checked')` | `el.checked` |
| `$el.focus()` | `el.focus()` |
| `$el.on('input', fn)` | `el.addEventListener('input', fn)` |
| `$el.on('change', fn)` | `el.addEventListener('change', fn)` |
| `$el.on('mousewheel', fn)` | `el.addEventListener('wheel', fn)` |
| `$el.on('click', fn)` | `el.addEventListener('click', fn)` |
| `$el.hide()` | `el.style.display = 'none'` |
| `$el.show()` | `el.style.display = ''` |
| `$el.html(str)` | Use Lit `render(html\`...\`, el)` or `el.textContent` |
| `$el.text(str)` | `el.textContent = str` |
| `$el.css('cursor', val)` | `el.style.cursor = val` |
| `$el.find(selector)` | `container.querySelector(selector)` |
| `$el.find('option:selected')` | `el.options[el.selectedIndex]` |
| `$option.data('multiplier')` | `option.dataset.multiplier` |
| `$option.data('color')` | `option.dataset.color` |
| `e.originalEvent.wheelDelta` | `e.deltaY` (wheel event, sign inverted) |

**Mousewheel event note:** jQuery normalizes `mousewheel` to include `originalEvent`. Native `wheel` event uses `deltaY` where positive = scroll down (opposite of `wheelDelta`). The delta check becomes:
```javascript
// BEFORE:
$el.on('mousewheel', function (e) {
    constrainDimensions(min, e.originalEvent.wheelDelta > 0 ? 1 : -1);
});

// AFTER:
el.addEventListener('wheel', (e) => {
    constrainDimensions(min, e.deltaY < 0 ? 1 : -1);  // deltaY < 0 = scroll up
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
}, { passive: false });
```

**Quality dropdown data attributes:** jQuery's `.data()` stores on an internal cache. Native `dataset` reads from DOM `data-*` attributes. Ensure quality options store multiplier/color as `data-*` attributes:
```javascript
// In quality options rendering:
const optionEl = document.createElement('option');
optionEl.value = option.value;
optionEl.textContent = option.marker + ' ' + option.label;
optionEl.dataset.multiplier = option.multiplier;
optionEl.dataset.color = option.color;
optionEl.style.color = option.color;
```

**Step 3: Change `d.$el` to `d.el` reference in `d.get()` and all internal references**

Replace all internal `d.$el` references with the `container` variable (which is already in scope from Task 7).

**Step 4: Verify no parse errors**

```bash
node --check Resources/Public/JavaScript/Plugins/typo3image.js
```

**Step 5: Commit**

```bash
git add Resources/Public/JavaScript/Plugins/typo3image.js
git commit -S -m "refactor: migrate d.get() and event handlers to native DOM (#633)

Replace all jQuery method calls in getImageDialog():
- .val() -> .value
- .prop('checked') -> .checked
- .find(sel) -> querySelector(sel)
- .on(event, fn) -> addEventListener(event, fn)
- .data(key) -> dataset.key
- mousewheel -> wheel event (inverted deltaY)"
```

---

### Task 9: Remove jQuery import and modernize variables

**Files:**
- Modify: `Resources/Public/JavaScript/Plugins/typo3image.js:1-2,21`

**Step 1: Remove jQuery import**

```javascript
// DELETE line 21:
import $ from 'jquery';

// DELETE line 2 (or update globals comment):
/*global $, $$, jquery, window, document, require, CKEDITOR*/
// becomes:
/*global window, document, CKEDITOR*/
```

**Step 2: Verify zero jQuery references remain**

```bash
grep -ci 'jquery\|\$[.(]' Resources/Public/JavaScript/Plugins/typo3image.js
```
Expected: `0`

If any remain, they are bugs from Tasks 1-8 — fix them before proceeding.

**Step 3: Verify no parse errors**

```bash
node --check Resources/Public/JavaScript/Plugins/typo3image.js
```

**Step 4: Commit**

```bash
git add Resources/Public/JavaScript/Plugins/typo3image.js
git commit -S -m "refactor: remove jQuery import (#633)

Remove import $ from 'jquery' -- zero jQuery references remain.
grep -ci 'jquery' typo3image.js returns 0."
```

---

### Task 10: Modernize `var` to `const`/`let` throughout `getImageDialog()` and helpers

**Files:**
- Modify: `Resources/Public/JavaScript/Plugins/typo3image.js` — 109 `var` declarations

**Step 1: Replace `var` with `const`/`let`**

Rules:
- `const` for values never reassigned (most cases)
- `let` for values that are reassigned (loop counters, accumulators, mutable state)
- **Do NOT change** variables in the CKEditor converter section (lines 1600-3312) — those are out of scope per the design doc

**Key patterns:**

```javascript
// Variables that need `let` (reassigned):
let value, startVal, ratio, max, min, opposite;
let message, messageColor;
let defaultQuality, initialBehavior;

// Everything else becomes `const`:
const fields = [...];
const container = document.createElement('div');
const id = `rteckeditorimage-${key}`;
// etc.
```

**Step 2: Count remaining `var` declarations (should only be in lines 1600+)**

```bash
grep -n '^\s*var\s' Resources/Public/JavaScript/Plugins/typo3image.js | head -5
```
Expected: Only lines >= 1600 (CKEditor converters, out of scope).

**Step 3: Verify no parse errors**

```bash
node --check Resources/Public/JavaScript/Plugins/typo3image.js
```

**Step 4: Commit**

```bash
git add Resources/Public/JavaScript/Plugins/typo3image.js
git commit -S -m "refactor: var -> const/let in dialog and helper functions (#633)

Replace 109 var declarations with const/let in getImageDialog(),
askImageAttributes(), selectImage(), openLinkBrowser(), and helpers.
CKEditor model/view converter code (lines 1600+) is out of scope."
```

---

### Task 11: Modernize string concatenation to template literals

**Files:**
- Modify: `Resources/Public/JavaScript/Plugins/typo3image.js` — string concatenation in `renderQualityIndicator()` and remaining helpers

**Step 1: Convert string concatenation to template literals**

Focus on the `renderQualityIndicator()` function (lines 502-624) which has the most string concatenation:

```javascript
// BEFORE:
message = '<strong>Processing Info:</strong> Image ' + intrinsicWidth + 'x' + intrinsicHeight + ' px ' +
          'will be displayed at ' + displayWidth + 'x' + displayHeight + ' px = ' +
          '<span style="color: ' + expectedQualityColor + '; font-weight: bold;">' +
          expectedQualityName + ' Quality (' + qualityRatio.toFixed(1) + 'x scaling)</span>';

// AFTER:
message = `<strong>Processing Info:</strong> Image ${intrinsicWidth}x${intrinsicHeight} px ` +
          `will be displayed at ${displayWidth}x${displayHeight} px = ` +
          `<span style="color: ${expectedQualityColor}; font-weight: bold;">` +
          `${expectedQualityName} Quality (${qualityRatio.toFixed(1)}x scaling)</span>`;
```

Also convert in: `encodeTypoLink()`, `parseTypoLink()` error messages, URL building in `getImageInfo()` and `openLinkBrowser()`.

**Do NOT convert** in the CKEditor converter section (lines 1600+).

**Step 2: Verify no parse errors**

```bash
node --check Resources/Public/JavaScript/Plugins/typo3image.js
```

**Step 3: Commit**

```bash
git add Resources/Public/JavaScript/Plugins/typo3image.js
git commit -S -m "refactor: string concatenation -> template literals (#633)

Convert string building to ES6 template literals in:
- renderQualityIndicator() (quality indicator HTML)
- URL construction in getImageInfo(), openLinkBrowser()
- Error messages throughout dialog functions"
```

---

### Task 12: Convert `function` expressions to arrow functions where appropriate

**Files:**
- Modify: `Resources/Public/JavaScript/Plugins/typo3image.js`

**Step 1: Convert anonymous functions to arrow functions**

Rules:
- Convert `function() {}` to `() => {}` where `this` is not used
- Keep `function` for named functions (hoisted declarations)
- Keep `function` where `this` binding matters (none expected after jQuery removal)

Key conversions:
```javascript
// Event handlers:
// BEFORE: .addEventListener('input', function() { ... });
// AFTER:  .addEventListener('input', () => { ... });

// Promise chains:
// BEFORE: .then(function(response) { ... });
// AFTER:  .then((response) => { ... });

// Callbacks:
// BEFORE: callback: function (currentModal) { ... }
// AFTER:  callback: (currentModal) => { ... }
```

**Do NOT convert** top-level function declarations (`function getImageDialog(...)`, `function askImageAttributes(...)`, etc.) — keep as declarations for hoisting.

**Step 2: Verify no parse errors**

```bash
node --check Resources/Public/JavaScript/Plugins/typo3image.js
```

**Step 3: Commit**

```bash
git add Resources/Public/JavaScript/Plugins/typo3image.js
git commit -S -m "refactor: anonymous function expressions -> arrow functions (#633)

Convert callback/handler function expressions to arrow functions
where this-binding is not used. Keep function declarations for hoisting."
```

---

### Task 13: Final verification and PR update

**Step 1: Full jQuery audit**

```bash
# Zero jQuery references
grep -ci 'jquery' Resources/Public/JavaScript/Plugins/typo3image.js
# Expected: 0 (or 1 if the JSDoc comment on line 2 remains -- remove it)

# Zero var in dialog functions (only in CKEditor converters 1600+)
grep -n '^\s*var\s' Resources/Public/JavaScript/Plugins/typo3image.js | awk -F: '$2 < 1600'
# Expected: no output

# Syntax check
node --check Resources/Public/JavaScript/Plugins/typo3image.js
# Expected: no output (success)
```

**Step 2: Push all commits**

```bash
git push
```

**Step 3: Update PR description from draft to ready**

```bash
gh pr ready
```

Update PR body with final summary of all changes.

**Step 4: Wait for CI**

All E2E tests must pass on v13 and v14. This is the final gate.

---

## Risk Checkpoints

| After Task | Risk Check |
|---|---|
| Task 5 | Push to CI -- validates all non-dialog jQuery removal |
| Task 7 | Push to CI -- validates dialog builder Lit migration (highest risk) |
| Task 9 | Push to CI -- validates jQuery import fully removed |
| Task 13 | Final CI run -- all modernization complete |

## Acceptance Criteria

- [ ] `grep -ci 'jquery' typo3image.js` returns 0
- [ ] `grep -c '^\s*var\s' typo3image.js` counts only in lines 1600+ (CKEditor converters)
- [ ] `node --check typo3image.js` passes
- [ ] E2E tests: all ~185 pass on v13 AND v14
- [ ] Image dialog: opens, displays all fields, saves all attributes correctly
- [ ] Link browser: opens, selects link, populates fields, saves
- [ ] Image insertion: file browser opens, selects file, inserts into editor
- [ ] Quality indicator: updates dynamically on width/height/quality changes
- [ ] Click behavior: radio toggle shows/hides correct field sections
- [ ] Override checkboxes: toggle title/alt override correctly
