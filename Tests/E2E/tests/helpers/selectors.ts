/**
 * Centralized CSS selectors for E2E tests.
 *
 * Single source of truth for selectors used across multiple spec files.
 * Avoids duplication and makes maintenance easier when HTML structure changes.
 */

/** Popup/click-to-enlarge link selector */
export const POPUP_LINK = 'a[data-popup="true"]';

/** Image inside a popup link */
export const POPUP_IMAGE = `${POPUP_LINK} img`;

/** All styled images (with alignment classes) */
export const STYLED_IMAGES = [
    'img.image-left',
    'img.image-right',
    'img.image-center',
    'img.image-inline',
    'img.image-block',
].join(', ');

/** All styled figures (with alignment classes) */
export const STYLED_FIGURES = [
    'figure.image-left',
    'figure.image-right',
    'figure.image-center',
    'figure.image-inline',
    'figure.image-block',
].join(', ');

/** Combined styled images and figures */
export const STYLED_ELEMENTS = `${STYLED_IMAGES}, ${STYLED_FIGURES}`;

/** Inline images */
export const INLINE_IMAGE = 'img.image-inline';

/** Linked inline image (inside a link) */
export const LINKED_INLINE_IMAGE = 'a > img.image-inline';

/** Figure elements */
export const FIGURE = 'figure';

/** Figure with caption */
export const FIGURE_WITH_CAPTION = 'figure:has(figcaption)';

/** Test content selectors (specific to CI test data) */
export const TEST_LINKED_IMAGE = 'a.test-linked-image';
export const TEST_SIMPLE_LINK = 'a.test-simple-link';
export const TEST_FIGURE_LINKED = 'a.test-figure-linked';
export const TEST_T3_LINK = 'a.test-t3-link';

/** Backend CKEditor selectors */
export const CK_EDITABLE = '.ck-editor__editable';
export const CK_IMAGE = `${CK_EDITABLE} img`;
export const CK_FIGURE = `${CK_EDITABLE} figure`;
export const CK_INLINE_WIDGET = '.ck-widget_inline-image';

/** Template matrix test selectors (by alt text) */
export function templateTestImage(templateName: string): string {
    return `img[alt="Template ${templateName}"]`;
}
