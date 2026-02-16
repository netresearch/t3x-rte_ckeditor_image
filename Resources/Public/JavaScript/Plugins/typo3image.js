/*jslint browser: true, this: true, multivar: true, white: true, devel: true*/
/*global $, $$, jquery, window, document, require, CKEDITOR*/

/**
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */
import { Plugin, Command } from '@ckeditor/ckeditor5-core';
import { ButtonView } from '@ckeditor/ckeditor5-ui';
import { DomEventObserver } from '@ckeditor/ckeditor5-engine';
import { toWidget, toWidgetEditable, WidgetToolbarRepository } from '@ckeditor/ckeditor5-widget';
import { default as Modal } from '@typo3/backend/modal.js';
import $ from 'jquery';


class Typo3ImageDoubleClickObserver extends DomEventObserver {
    constructor(view) {
        super(view);

        this.domEventType = 'dblclick';
    }

    onDomEvent(domEvent) {
        this.fire('dblclick:typo3image', domEvent);
    }
}


/**
 * Convert URL to relative format for local storage only.
 * Remote storage URLs (S3, Azure, CDN) must remain absolute.
 *
 * @param url
 * @param storageDriver The TYPO3 storage driver type (e.g., 'Local', 'S3')
 * @return relativeUrl for local storage, absolute URL for remote storage
 */
function urlToRelative(url, storageDriver) {

    if (!url) {
        return '';
    }

    // Only convert to relative for Local storage
    // Remote storages (S3, Azure, CDN) must remain absolute
    if (storageDriver && storageDriver !== 'Local') {
        return url;
    }

    // Convert local storage URLs to relative for site portability
    if (url.indexOf("http://") !== -1 || url.indexOf("https://") !== -1) {
        var u = new URL(url);
        return u.pathname + u.search;
    } else {
        if (url[0] !== "/") {
            return "/" + url;
        }
    }

    return url;
}


/**
 * Get the image attributes dialog
 *
 * @param {Object} editor
 * @param {Object} img
 * @param {Object} attributes
 * @return {{$el: {jquery}, get: {function}}}
 */
function getImageDialog(editor, img, attributes) {
    var d = {},
        $rows = [],
        elements = {};

    const fields = [
        {
            width: { label: 'Display width in px', type: 'number' },
            height: { label: 'Display height in px', type: 'number' },
            quality: { label: 'Scaling', type: 'select' }
        },
        {
            title: { label: 'Advisory Title', type: 'text' }
        },
        {
            alt: { label: 'Alternative Text', type: 'text' }
        },
        {
            caption: { label: 'Caption', type: 'textarea', rows: 2 }
        }
    ];

    // Get maxWidth and maxHeight from editor configuration (from TSConfig)
    const styleConfig = editor.config.get('style') || {};
    const typo3imageConfig = styleConfig.typo3image || {};
    const maxConfigWidth = typo3imageConfig.maxWidth || 1920;
    const maxConfigHeight = typo3imageConfig.maxHeight || 9999;

    // Check if the image is SVG
    const isSvg = img.url && (img.url.endsWith('.svg') || img.url.includes('.svg?')) || false;

    d.$el = $('<div class="rteckeditorimage">');

    for (const fieldGroup of fields) {
        var $row = $('<div class="row">').appendTo(d.$el);

        $rows.push($row);
        for (const [key, config] of Object.entries(fieldGroup)) {
            // Use full width for title, alt, and caption fields, otherwise use col-sm-4
            var colClass = (key === 'title' || key === 'alt' || key === 'caption') ? 'col-xs-12' : 'col-xs-12 col-sm-4';
            var $group = $('<div class="form-group">').appendTo($('<div class="' + colClass + '">').appendTo($row));
            var id = 'rteckeditorimage-' + key;
            $('<label class="form-label" for="' + id + '">' + config.label + '</label>').appendTo($group);

            var $el;
            if (config.type === 'select') {
                $el = $('<select id="' + id + '" name="' + key + '" class="form-select"></select>');
            } else if (config.type === 'textarea') {
                $el = $('<textarea id="' + id + '" name="' + key + '" class="form-control" rows="' + (config.rows || 3) + '"></textarea>');
            } else {
                $el = $('<input type="' + config.type + '" id ="' + id + '" name="' + key + '" class="form-control">');
            }

            var placeholder = (config.type === 'text' ? (img[key] || '') : img.processed[key]) + '';
            var value = ((attributes[key] || '') + '').trim();

            if (config.type !== 'select') {
                $el.attr('placeholder', placeholder);
                $el.val(value);
            }

            if (config.type === 'text' || config.type === 'textarea') {
                var startVal = value,
                    hasDefault = img[key] && img[key].trim(),
                    cbox = $('<input type="checkbox" class="form-check-input">')
                        .attr('id', 'checkbox-' + key)
                        .prop('checked', !!value || !hasDefault)
                        .prop('disabled', !hasDefault),
                    cboxLabel = $('<label class="form-check-label"></label>')
                        .attr('for', 'checkbox-' + key)
                        .text(hasDefault ? img.lang.override.replace('%s', img[key]) : img.lang.overrideNoDefault);

                // Add tooltip when checkbox is disabled (no default value from file)
                if (!hasDefault) {
                    const noDefaultMsg = img.lang.noDefaultMetadata.replace('%s', key);
                    cbox.attr('title', noDefaultMsg);
                    cboxLabel.css('cursor', 'not-allowed').attr('title', noDefaultMsg);
                }

                $el.prop('disabled', hasDefault && !value);

                var $checkboxContainer = $('<div class="form-check form-check-type-toggle" style="margin: 0 0 6px;">').appendTo($group);
                cbox.appendTo($checkboxContainer);
                cboxLabel.appendTo($checkboxContainer);

                cboxLabel.on('click', function () {
                    $el.prop('disabled', !cbox.prop('checked'));
                    startVal = $el.val() || startVal;

                    // Clear value or set to startvalue when clicking checkbox
                    if (!cbox.prop('checked')) {
                        $el.val('');
                    } else {
                        $el.val(startVal);
                        $el.focus();
                    }
                });

                // Initally read/set title/alt attributes and check if override is enabled
                if (key === 'title' || key === 'alt') {
                    if (attributes['data-' + key + '-override'] === 'false') {
                        cbox.prop('checked', false);
                        $el.prop('disabled', true);
                        $el.val('');
                        attributes['data-' + key + '-override'] = false;
                        delete attributes[key];
                    } else {
                        cbox.prop('checked', true);
                        $el.prop('disabled', false);
                    }
                }
            } else if (config.type === 'number') {
                var ratio = img.width / img.height;
                if (key === 'height') {
                    ratio = 1 / ratio;
                }
                var opposite = 1;
                var max = img[key];
                var min = Math.ceil(opposite * ratio);
                $el.attr('max', max);
                $el.attr('min', min);

                var constrainDimensions = function (currentMin, delta) {
                    value = parseInt($el.val().replace(/[^0-9]/g, '') || max);
                    if (delta) {
                        value += delta;
                    }
                    value = Math.max(currentMin, Math.min(value, max));
                    var $opposite = elements[key === 'width' ? 'height' : 'width'],
                        oppositeMax = parseInt($opposite.attr('max')),
                        ratio = oppositeMax / max;

                    $opposite.val(value === max ? oppositeMax : Math.ceil(value * ratio));
                    $el.val(value);
                };

                $el.on('input', function () {
                    // Allow empty input during typing (fixes #140)
                    var val = $el.val().replace(/[^0-9]/g, '');
                    if (val !== '') {
                        constrainDimensions(1);
                    }
                });
                $el.on('change', function () {
                    constrainDimensions(min);
                });
                $el.on('mousewheel', function (e) {
                    constrainDimensions(min, e.originalEvent.wheelDelta > 0 ? 1 : -1);
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                });
            } else if (config.type === 'select' && key === 'quality') {
                // Image Processing quality dropdown - sorted by quality ascending
                var qualityOptions = [
                    { value: 'none', label: img.lang.qualityNone || 'No Scaling', multiplier: 1.0, color: '#6c757d', marker: '●' },
                    { value: 'standard', label: img.lang.qualityStandard || 'Standard (1.0x)', multiplier: 1.0, color: '#ffc107', marker: '●' },
                    { value: 'retina', label: img.lang.qualityRetina || 'Retina (2.0x)', multiplier: 2.0, color: '#28a745', marker: '●' },
                    { value: 'ultra', label: img.lang.qualityUltra || 'Ultra (3.0x)', multiplier: 3.0, color: '#17a2b8', marker: '●' },
                    { value: 'print', label: img.lang.qualityPrint || 'Print (6.0x)', multiplier: 6.0, color: '#007bff', marker: '●' }
                ];

                for (const option of qualityOptions) {
                    var $option = $('<option>')
                        .val(option.value)
                        .text(option.marker + ' ' + option.label)
                        .data('multiplier', option.multiplier)
                        .data('color', option.color)
                        .css('color', option.color);
                    $option.appendTo($el);
                }

                // Determine default quality based on image type and existing attributes
                // Priority: 1) data-quality 2) data-noscale→'none' 3) SVG→'print' 4) default→'retina'
                var defaultQuality;
                if (attributes['data-quality']) {
                    defaultQuality = attributes['data-quality'];
                } else if (attributes['data-noscale']) {
                    // Backward compatibility: data-noscale should map to 'none' quality
                    defaultQuality = 'none';
                } else if (isSvg) {
                    defaultQuality = 'print';
                } else {
                    defaultQuality = 'retina';
                }
                $el.val(defaultQuality);

                // Disable for SVG (vector images don't need quality processing)
                if (isSvg) {
                    $el.prop('disabled', true);
                }
            }

            $group.append($el);
            elements[key] = $el;
        }
    }

    // Create quality indicator container (inserted after first row with dimensions)
    var $qualityIndicator = $('<div class="image-quality-indicator" style="margin: 12px 0; font-size: 13px; line-height: 1.6;">');
    $qualityIndicator.insertAfter($rows[0]);

    var $checkboxTitle = d.$el.find('#checkbox-title'),
        $checkboxAlt = d.$el.find('#checkbox-alt'),
        $inputWidth = d.$el.find('#rteckeditorimage-width'),
        $inputHeight = d.$el.find('#rteckeditorimage-height'),
        $qualityDropdown = d.$el.find('#rteckeditorimage-quality'),
        $noScale = $('<input id="checkbox-noscale" type="checkbox">');

    // Check for existing noScale attribute (for backward compatibility)
    if (attributes['data-noscale']) {
        $noScale.prop('checked', true);
    }

    // ========================================
    // Click Behavior Section - unified handling of image click actions
    // Implements fix for issue #565: prevents conflict between
    // "Click to Enlarge" and "Link" (both create <a> wrappers)
    // ========================================
    var $clickBehaviorSection = $('<div class="row" style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #dee2e6;">').insertAfter($rows[3]);
    var $clickBehaviorHeader = $('<div class="col-xs-12" style="margin-bottom: 12px;"><strong>' + (img.lang.clickBehavior || 'Click Behavior') + '</strong></div>').appendTo($clickBehaviorSection);

    // Radio button container
    var $radioContainer = $('<div class="col-xs-12" style="margin-bottom: 12px;">').appendTo($clickBehaviorSection);

    // Extract non-alignment CSS classes for the CSS class input fields
    // Alignment classes (image-left, image-center, etc.) are controlled via bubble toolbar
    var alignmentClassList = ['image-left', 'image-center', 'image-right', 'image-block', 'image-inline'];
    var allClasses = (attributes.class || '').split(' ').filter(function(c) { return c.trim() !== ''; });
    var nonAlignmentClasses = allClasses.filter(function(c) {
        return alignmentClassList.indexOf(c) === -1;
    }).join(' ');

    // Determine initial selection based on existing attributes
    var initialBehavior = 'none';
    if (attributes['data-htmlarea-zoom'] || attributes['data-htmlarea-clickenlarge']) {
        initialBehavior = 'enlarge';
    } else if (attributes.linkHref && attributes.linkHref.trim() !== '') {
        initialBehavior = 'link';
    }

    // Radio: None
    var $radioNoneWrapper = $('<div class="form-check" style="margin-bottom: 8px;">').appendTo($radioContainer);
    var $radioNone = $('<input type="radio" name="clickBehavior" id="clickBehavior-none" value="none" class="form-check-input">')
        .prop('checked', initialBehavior === 'none')
        .appendTo($radioNoneWrapper);
    $('<label class="form-check-label" for="clickBehavior-none">').text(img.lang.clickBehaviorNone || 'None - image is not clickable').appendTo($radioNoneWrapper);

    // Radio: Enlarge
    var $radioEnlargeWrapper = $('<div class="form-check" style="margin-bottom: 8px;">').appendTo($radioContainer);
    var $radioEnlarge = $('<input type="radio" name="clickBehavior" id="clickBehavior-enlarge" value="enlarge" class="form-check-input">')
        .prop('checked', initialBehavior === 'enlarge')
        .appendTo($radioEnlargeWrapper);
    $('<label class="form-check-label" for="clickBehavior-enlarge">').text(img.lang.clickBehaviorEnlarge || 'Enlarge - opens full-size in lightbox').appendTo($radioEnlargeWrapper);

    // Radio: Link
    var $radioLinkWrapper = $('<div class="form-check" style="margin-bottom: 8px;">').appendTo($radioContainer);
    var $radioLink = $('<input type="radio" name="clickBehavior" id="clickBehavior-link" value="link" class="form-check-input">')
        .prop('checked', initialBehavior === 'link')
        .appendTo($radioLinkWrapper);
    $('<label class="form-check-label" for="clickBehavior-link">').text(img.lang.clickBehaviorLink || 'Link - opens custom URL').appendTo($radioLinkWrapper);

    // ========================================
    // Disable click behavior when image is inside an external link
    // (e.g., inline image inside a link that wraps text + image)
    // ========================================
    if (attributes.isInsideExternalLink) {
        // Disable all radio buttons
        $radioNone.prop('disabled', true);
        $radioEnlarge.prop('disabled', true);
        $radioLink.prop('disabled', true);

        // Add info message explaining why options are disabled
        var $externalLinkInfo = $('<div class="alert alert-info" style="margin-top: 12px; padding: 10px; font-size: 13px;">')
            .html('<strong>' + (img.lang.imageInsideLinkTitle || 'Image is inside a link') + '</strong><br>' +
                  (img.lang.imageInsideLinkMessage || 'Click behavior options are disabled because this image is inside a link that was created around the text. To change link settings, edit the link directly in the editor.'))
            .appendTo($radioContainer);
    }

    // ========================================
    // Dynamic fields container (shown/hidden based on radio selection)
    // ========================================
    var $dynamicFieldsContainer = $('<div class="col-xs-12" id="clickBehavior-fields">').appendTo($clickBehaviorSection);

    // --- Enlarge fields (only CSS class) ---
    var $enlargeFields = $('<div id="enlargeFields" style="display: none;">').appendTo($dynamicFieldsContainer);
    var $enlargeCssGroup = $('<div class="form-group">').appendTo($enlargeFields);
    $('<label class="form-label" for="input-linkCssClass-enlarge">').text(img.lang.linkCssClass || 'Link CSS Class').appendTo($enlargeCssGroup);
    var $inputCssClassEnlarge = $('<input type="text" id="input-linkCssClass-enlarge" class="form-control" placeholder="e.g., lightbox-trigger">')
        .val(initialBehavior === 'enlarge' ? nonAlignmentClasses : '')
        .appendTo($enlargeCssGroup);

    // --- Link fields (URL, Target, Title, CSS class) ---
    var $linkFields = $('<div id="linkFields" style="display: none;">').appendTo($dynamicFieldsContainer);

    // Link URL with Browse button
    var $linkUrlGroup = $('<div class="form-group">').appendTo($linkFields);
    $('<label class="form-label" for="rteckeditorimage-linkHref">').text(img.lang.linkUrl || 'Link URL').appendTo($linkUrlGroup);
    var $linkUrlInputGroup = $('<div class="input-group">').appendTo($linkUrlGroup);
    var $inputLinkHref = $('<input type="text" id="rteckeditorimage-linkHref" name="linkHref" class="form-control" placeholder="https://example.com or t3://page?uid=123">')
        .val(attributes.linkHref || '')
        .appendTo($linkUrlInputGroup);
    var $browseButton = $('<button type="button" class="btn btn-default">')
        .text(img.lang.browse || 'Browse...')
        .appendTo($linkUrlInputGroup);

    // Link Target and Title row
    var $linkOptionsRow = $('<div class="row">').appendTo($linkFields);
    var $linkTargetCol = $('<div class="col-xs-12 col-sm-6">').appendTo($linkOptionsRow);
    var $linkTitleCol = $('<div class="col-xs-12 col-sm-6">').appendTo($linkOptionsRow);

    // Link Target input with datalist for common values
    // Changed from <select> to <input> to support free text targets like "nav_frame"
    var $linkTargetGroup = $('<div class="form-group">').appendTo($linkTargetCol);
    $('<label class="form-label" for="rteckeditorimage-linkTarget">').text(img.lang.linkTarget || 'Link Target').appendTo($linkTargetGroup);
    var $inputLinkTarget = $('<input type="text" id="rteckeditorimage-linkTarget" name="linkTarget" class="form-control" list="rteckeditorimage-linkTarget-options" placeholder="' + (img.lang.linkTargetPlaceholder || 'e.g. _blank, _top, nav_frame') + '">')
        .val(attributes.linkTarget || '')
        .appendTo($linkTargetGroup);
    // Datalist provides suggestions but allows any value
    var $linkTargetDatalist = $('<datalist id="rteckeditorimage-linkTarget-options">').appendTo($linkTargetGroup);
    $('<option>').attr('value', '_blank').text(img.lang.linkTargetBlank || 'New window').appendTo($linkTargetDatalist);
    $('<option>').attr('value', '_top').text(img.lang.linkTargetTop || 'Top frame').appendTo($linkTargetDatalist);
    $('<option>').attr('value', '_self').text(img.lang.linkTargetSelf || 'Same window').appendTo($linkTargetDatalist);
    $('<option>').attr('value', '_parent').text(img.lang.linkTargetParent || 'Parent frame').appendTo($linkTargetDatalist);

    // Link Title input
    var $linkTitleGroup = $('<div class="form-group">').appendTo($linkTitleCol);
    $('<label class="form-label" for="rteckeditorimage-linkTitle">').text(img.lang.linkTitle || 'Link Title').appendTo($linkTitleGroup);
    var $inputLinkTitle = $('<input type="text" id="rteckeditorimage-linkTitle" name="linkTitle" class="form-control">')
        .val(attributes.linkTitle || '')
        .appendTo($linkTitleGroup);

    // Link CSS Class (separate from image class - stored on <a> element)
    var $linkCssGroup = $('<div class="form-group">').appendTo($linkFields);
    $('<label class="form-label" for="input-linkCssClass">').text(img.lang.linkCssClass || 'Link CSS Class').appendTo($linkCssGroup);
    var $inputCssClassLink = $('<input type="text" id="input-linkCssClass" class="form-control">')
        .val(attributes.linkClass || '')
        .appendTo($linkCssGroup);

    // Additional Link Parameters (for advanced use cases like &L=1, &type=123, etc.)
    var $linkParamsGroup = $('<div class="form-group">').appendTo($linkFields);
    $('<label class="form-label" for="rteckeditorimage-linkParams">').text(img.lang.linkParams || 'Additional Parameters').appendTo($linkParamsGroup);
    var $inputLinkParams = $('<input type="text" id="rteckeditorimage-linkParams" name="linkParams" class="form-control" placeholder="' + (img.lang.linkParamsPlaceholder || 'e.g. &L=1&type=123') + '">')
        .val(attributes.linkParams || '')
        .appendTo($linkParamsGroup);

    // Store elements for d.get()
    elements.linkHref = $inputLinkHref;
    elements.linkTarget = $inputLinkTarget;
    elements.linkTitle = $inputLinkTitle;
    elements.linkClass = $inputCssClassLink;
    elements.linkParams = $inputLinkParams;

    // ========================================
    // Field visibility toggle function
    // ========================================
    function updateClickBehaviorFields() {
        // Scope selector to dialog container to avoid conflicts with multiple editors
        var selectedBehavior = d.$el.find('input[name="clickBehavior"]:checked').val();

        // Hide all dynamic fields first
        $enlargeFields.hide();
        $linkFields.hide();

        // Show relevant fields based on selection
        if (selectedBehavior === 'enlarge') {
            $enlargeFields.show();
        } else if (selectedBehavior === 'link') {
            $linkFields.show();
        }
    }

    // Bind radio button change events (scoped to dialog container)
    d.$el.find('input[name="clickBehavior"]').on('change', updateClickBehaviorFields);

    // Initial field visibility
    updateClickBehaviorFields();

    // Browse button click handler - opens TYPO3 link browser
    $browseButton.on('click', function() {
        // Construct full TypoLink from all current dialog fields
        // This ensures target, class, title, additionalParams are preserved when reopening the browser
        var currentLinkData = {
            href: $inputLinkHref.val() || '',
            target: $inputLinkTarget.val() || '',
            class: $inputCssClassLink.val() || '',
            title: $inputLinkTitle.val() || '',
            additionalParams: $inputLinkParams.val() || ''
        };
        var currentLinkValue = encodeTypoLink(currentLinkData);
        openLinkBrowser(editor, currentLinkValue).then(function(linkData) {
            if (linkData && linkData.href) {
                // Always update all fields to ensure stale values are cleared
                // when the user selects a link without certain attributes
                $inputLinkHref.val(linkData.href);
                $inputLinkTarget.val(linkData.target || '');
                $inputLinkTitle.val(linkData.title || '');
                $inputCssClassLink.val(linkData.class || '');
                $inputLinkParams.val(linkData.additionalParams || '');
            }
        });
    });


    /**
     * Debounce utility function to delay execution
     *
     * @param {Function} func - Function to debounce
     * @param {number} wait - Delay in milliseconds
     * @return {Function} Debounced function
     */
    function debounce(func, wait) {
        var timeout;
        return function() {
            var context = this;
            var args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                func.apply(context, args);
            }, wait);
        };
    }

    function renderQualityIndicator(displayWidth, displayHeight) {
        var intrinsicWidth = img.width;
        var intrinsicHeight = img.height;

        // Guard against zero dimensions to prevent division by zero
        if (displayWidth === 0 || displayHeight === 0) {
            $qualityIndicator.html(
                '<div style="color: #dc3545; font-size: 13px; line-height: 1.5;">' +
                '<strong>Error:</strong> Display dimensions cannot be zero.' +
                '</div>'
            ).show();
            return;
        }

        // Handle SVG files
        if (img.extension === 'svg') {
            $qualityIndicator.html(
                '<div style="color: #666; font-size: 13px; line-height: 1.5;">' +
                '<strong>Processing Info:</strong> Vector image will not be processed (scales perfectly at any resolution).' +
                '</div>'
            ).show();
            return;
        }

        // Get selected quality multiplier and color from dropdown
        var selectedQuality = $qualityDropdown.val();
        var $selectedOption = $qualityDropdown.find('option:selected');
        var selectedMultiplier = $selectedOption.data('multiplier');
        var selectedColor = $selectedOption.data('color');

        // Calculate requested source dimensions for selected quality (BEFORE capping)
        var requestedWidth = displayWidth * selectedMultiplier;
        var requestedHeight = displayHeight * selectedMultiplier;

        // Calculate required source dimensions (capped at original size)
        // IMPORTANT: Never upscale beyond original image dimensions
        var requiredWidth = Math.min(requestedWidth, intrinsicWidth);
        var requiredHeight = Math.min(requestedHeight, intrinsicHeight);

        // Check if dimensions match exactly
        var dimensionsMatch = (displayWidth === intrinsicWidth && displayHeight === intrinsicHeight);

        // Check if display size exceeds image size
        var displayExceedsImage = (displayWidth > intrinsicWidth || displayHeight > intrinsicHeight);

        // Calculate expected quality (ratio of image pixels to display pixels)
        // Quality = Image / Display (higher = better quality)
        var qualityRatio = Math.min(intrinsicWidth / displayWidth, intrinsicHeight / displayHeight);
        var expectedQualityName = '';
        var expectedQualityColor = '';

        // Determine quality level based on ratio
        if (qualityRatio >= 6.0) {
            expectedQualityName = 'Print';
            expectedQualityColor = '#007bff';
        } else if (qualityRatio >= 3.0) {
            expectedQualityName = 'Ultra';
            expectedQualityColor = '#17a2b8';
        } else if (qualityRatio >= 2.0) {
            expectedQualityName = 'Retina';
            expectedQualityColor = '#28a745';
        } else if (qualityRatio >= 0.95) {
            expectedQualityName = 'Standard';
            expectedQualityColor = '#ffc107';
        } else {
            expectedQualityName = 'Poor';
            expectedQualityColor = '#dc3545';
        }

        // Calculate actual achievable quality
        // If requested processing size exceeds original, we can only achieve what the original provides
        var actualQuality = Math.min(intrinsicWidth / displayWidth, intrinsicHeight / displayHeight);
        var requestedQuality = selectedMultiplier;
        // Check UNCAPPED requested size against original (not the capped requiredWidth/Height)
        var canAchieveRequested = (requestedWidth <= intrinsicWidth && requestedHeight <= intrinsicHeight);

        // Build processing info message
        var message = '';
        var messageColor = '#666';

        // Handle "No Scaling" option
        if (selectedQuality === 'none') {
            // No processing - show actual quality based on image/display ratio
            message = '<strong>Processing Info:</strong> Image ' + intrinsicWidth + '×' + intrinsicHeight + ' px ' +
                      'will be displayed at ' + displayWidth + '×' + displayHeight + ' px = ' +
                      '<span style="color: ' + expectedQualityColor + '; font-weight: bold;">● ' +
                      expectedQualityName + ' Quality (' + qualityRatio.toFixed(1) + 'x scaling)</span>';
            messageColor = expectedQualityColor;
        } else if (!canAchieveRequested) {
            // Cannot achieve requested quality - show what will actually happen
            message = '<strong>Processing Info:</strong> Image ' + intrinsicWidth + '×' + intrinsicHeight + ' px ' +
                      'will be displayed at ' + displayWidth + '×' + displayHeight + ' px = ' +
                      '<span style="color: ' + expectedQualityColor + '; font-weight: bold;">● ' +
                      expectedQualityName + ' Quality (' + actualQuality.toFixed(1) + 'x scaling)</span>';
            messageColor = expectedQualityColor;
        } else {
            // Can achieve requested quality - normal processing
            var qualityName = selectedQuality.charAt(0).toUpperCase() + selectedQuality.slice(1);

            // Check if resized dimensions match original (no need to mention "resized to" if same size)
            var resizeMatchesOriginal = (Math.round(requiredWidth) === intrinsicWidth && Math.round(requiredHeight) === intrinsicHeight);

            if (resizeMatchesOriginal) {
                // No need to mention resize when it matches original
                message = '<strong>Processing Info:</strong> Image ' + intrinsicWidth + '×' + intrinsicHeight + ' px ' +
                          'will be displayed at ' + displayWidth + '×' + displayHeight + ' px = ' +
                          '<span style="color: ' + selectedColor + '; font-weight: bold;">● ' +
                          qualityName + ' Quality (' + selectedMultiplier.toFixed(1) + 'x scaling)</span>';
            } else {
                // Different resize size - mention it
                message = '<strong>Processing Info:</strong> Image ' + intrinsicWidth + '×' + intrinsicHeight + ' px ' +
                          'will be resized to ' + Math.round(requiredWidth) + '×' + Math.round(requiredHeight) + ' px and displayed at ' +
                          displayWidth + '×' + displayHeight + ' px = ' +
                          '<span style="color: ' + selectedColor + '; font-weight: bold;">● ' +
                          qualityName + ' Quality (' + selectedMultiplier.toFixed(1) + 'x scaling)</span>';
            }
            messageColor = selectedColor;
        }

        var html = '<div style="color: ' + messageColor + '; font-size: 13px; line-height: 1.5;">' + message + '</div>';

        $qualityIndicator.html(html).show();
    }

    function updateQualityIndicator() {
        var displayWidth = parseInt($inputWidth.val(), 10) || 0;
        var displayHeight = parseInt($inputHeight.val(), 10) || 0;
        renderQualityIndicator(displayWidth, displayHeight);
    }

    // Wire up quality indicator event handlers
    var debouncedUpdateQualityIndicator = debounce(updateQualityIndicator, 250);
    $inputWidth.on('input', debouncedUpdateQualityIndicator);
    $inputHeight.on('input', debouncedUpdateQualityIndicator);
    $inputWidth.on('change', updateQualityIndicator);
    $inputHeight.on('change', updateQualityIndicator);
    $qualityDropdown.on('change', updateQualityIndicator);

    // Initial quality indicator update
    updateQualityIndicator();

    d.get = function () {
        for (const fieldGroup of fields) {
            for (const key of Object.keys(fieldGroup)) {
                var value = elements[key].val();

                if (typeof value !== 'undefined') {
                    attributes[key] = value;
                }
            }
        }

        // Extract and preserve alignment classes from original class attribute
        // These are set via the bubble toolbar and must be preserved when saving
        var alignmentClasses = ['image-left', 'image-center', 'image-right', 'image-block', 'image-inline'];
        var originalClasses = (attributes.class || '').split(' ').filter(function(c) { return c.trim() !== ''; });
        var preservedAlignmentClasses = originalClasses.filter(function(c) {
            return alignmentClasses.indexOf(c) !== -1;
        });

        // Handle Click Behavior radio button selection
        // IMPORTANT: Scope selector to dialog container to avoid conflicts with multiple editors
        var selectedClickBehavior = d.$el.find('input[name="clickBehavior"]:checked').val();

        if (selectedClickBehavior === 'enlarge') {
            // Enlarge mode: set zoom attribute, clear link attributes
            attributes['data-htmlarea-zoom'] = true;
            delete attributes.linkHref;
            delete attributes.linkTarget;
            delete attributes.linkTitle;
            delete attributes.linkParams;
            // Set CSS class from enlarge field (sanitized to valid CSS class characters)
            var enlargeCssVal = $inputCssClassEnlarge.val();
            var enlargeCss = enlargeCssVal ? enlargeCssVal.trim().replace(/[^a-zA-Z0-9_\-\s]/g, '') : '';
            // Combine preserved alignment classes with enlarge CSS class
            var combinedEnlargeClasses = preservedAlignmentClasses.slice();
            if (enlargeCss) {
                combinedEnlargeClasses.push(enlargeCss);
            }
            attributes.class = combinedEnlargeClasses.join(' ');
        } else if (selectedClickBehavior === 'link') {
            // Link mode: clear zoom attribute, set link attributes
            delete attributes['data-htmlarea-zoom'];
            delete attributes['data-htmlarea-clickenlarge'];
            // Collect link field values
            var linkHrefVal = $inputLinkHref.val().trim();
            var linkTargetVal = $inputLinkTarget.val().trim();
            var linkTitleVal = $inputLinkTitle.val().trim();
            var linkParamsVal = $inputLinkParams.val().trim();

            if (linkHrefVal !== '') {
                attributes.linkHref = linkHrefVal;
            } else {
                delete attributes.linkHref;
            }
            if (linkTargetVal !== '') {
                attributes.linkTarget = linkTargetVal;
            } else {
                delete attributes.linkTarget;
            }
            if (linkTitleVal !== '') {
                attributes.linkTitle = linkTitleVal;
            } else {
                delete attributes.linkTitle;
            }
            if (linkParamsVal !== '') {
                attributes.linkParams = linkParamsVal;
            } else {
                delete attributes.linkParams;
            }
            // Set link CSS class (stored on <a> element, separate from image class)
            var linkCssVal = $inputCssClassLink.val();
            var linkCss = linkCssVal ? linkCssVal.trim().replace(/[^a-zA-Z0-9_\-\s]/g, '') : '';
            if (linkCss) {
                attributes.linkClass = linkCss;
            } else {
                delete attributes.linkClass;
            }
            // Preserve alignment classes on the image (not mixed with link class)
            attributes.class = preservedAlignmentClasses.join(' ');
        } else {
            // None mode: clear both zoom and link attributes
            delete attributes['data-htmlarea-zoom'];
            delete attributes['data-htmlarea-clickenlarge'];
            delete attributes.linkHref;
            delete attributes.linkTarget;
            delete attributes.linkTitle;
            delete attributes.linkClass;
            delete attributes.linkParams;
            // Preserve alignment classes even in "none" mode
            attributes.class = preservedAlignmentClasses.join(' ');
        }

        // Save quality attribute and sync with noScale
        var qualityValue = $qualityDropdown.val();
        if (qualityValue && qualityValue !== '') {
            attributes['data-quality'] = qualityValue;
            // Sync noScale attribute: set to true when quality is 'none' (No Scaling)
            if (qualityValue === 'none') {
                attributes['data-noscale'] = true;
            } else {
                // Remove noScale when using quality processing
                delete attributes['data-noscale'];
            }
        } else if (attributes['data-quality']) {
            delete attributes['data-quality'];
            delete attributes['data-noscale'];
        }

        if ($checkboxTitle.length && !$checkboxTitle.is(":checked")) {
            delete attributes.title;
        }

        // When saving, check title/alt for override mode
        ['title', 'alt'].forEach(function (item) {
            var $curCheckbox = d.$el.find('#checkbox-' + item);

            // When saving, check title for override mode
            attributes['data-' + item + '-override'] = $curCheckbox.prop('checked');
            if ($curCheckbox.prop('checked')) {
                // Allow empty title/alt values
                attributes[item] = attributes[item] || '';
            } else {
                delete attributes[item];
            }
        });

        return attributes;
    };
    return d;
}

/**
 * Show image attributes dialog
 *
 * @param editor
 * @param img
 * @param attributes
 * @param table
 * @return {Promise}
 */
function askImageAttributes(editor, img, attributes, table) {
    var resolvePromise, rejectPromise;
    var promise = new Promise(function(resolve, reject) {
        resolvePromise = resolve;
        rejectPromise = reject;
    });
    var dialog = getImageDialog(editor, img, { ...img.processed, ...attributes });

    const modal = Modal.advanced({
        title: img.lang.imageProperties,
        content: dialog.$el,
        buttons: [
            {
                text: img.lang.cancel,
                btnClass: 'btn-default',
                icon: 'actions-close',
                trigger: function () {
                    modal.hideModal();
                    rejectPromise();
                }
            },
            {
                text: img.lang.save,
                btnClass: 'btn-primary',
                icon: 'actions-document-save',
                trigger: function () {

                    var dialogInfo = dialog.get(),
                        filteredAttr = {},
                        allowedAttributes = [
                            '!src', 'alt', 'title', 'class', 'rel', 'width', 'height', 'data-htmlarea-zoom', 'data-noscale', 'data-quality', 'data-title-override', 'data-alt-override', 'caption',
                            'linkHref', 'linkTarget', 'linkTitle', 'linkClass', 'linkParams'
                        ],
                        attributesNew = { ...img, ...dialogInfo };

                    filteredAttr = Object.keys(attributesNew)
                        .filter(function (key) {
                            return allowedAttributes.includes(key)
                        })
                        .reduce(function (obj, key) {
                            obj[key] = attributesNew[key];
                            return obj;
                        }, {});

                    getImageInfo(editor, table, img.uid, filteredAttr)
                        .then(function (getImg) {

                            // Preserve user-entered dimensions instead of overwriting with backend suggestions
                            const userWidth = filteredAttr.width;
                            const userHeight = filteredAttr.height;

                            Object.assign(filteredAttr, {
                                src: urlToRelative(getImg.url, getImg.storageDriver),
                                width: userWidth || getImg.processed.width || getImg.width,
                                height: userHeight || getImg.processed.height || getImg.height,
                                fileUid: img.uid,
                                fileTable: table
                            });
                            modal.hideModal('hide');
                            resolvePromise(filteredAttr);
                        });

                }
            }
        ]
    });

    return promise;
}

/**
 * Get image information
 *
 * @param editor
 * @param table
 * @param uid
 * @param params
 * @return {Promise}
 */
function getImageInfo(editor, table, uid, params) {
    let url = editor.config.get('typo3image').routeUrl + '&action=info&fileId=' + encodeURIComponent(uid) + '&table=' + encodeURIComponent(table);

    // SECURITY: Encode URL parameters to prevent injection attacks
    if (typeof params.width !== 'undefined' && params.width.length) {
        url += '&P[width]=' + encodeURIComponent(params.width);
    }

    if (typeof params.height !== 'undefined' && params.height.length) {
        url += '&P[height]=' + encodeURIComponent(params.height);
    }

    if (typeof params['data-quality'] !== 'undefined' && params['data-quality']) {
        url += '&P[quality]=' + encodeURIComponent(params['data-quality']);
    }

    return fetch(url).then(function(response) {
        if (!response.ok) {
            throw new Error('Image info request failed: ' + response.status);
        }
        return response.json();
    });
}

function selectImage(editor) {
    var resolvePromise;
    var promise = new Promise(function(resolve) {
        resolvePromise = resolve;
    });
    const bparams = [
        '',
        '',
        '',
        '',
    ];

    const contentUrl = editor.config.get('typo3image').routeUrl + '&bparams=' + bparams.join('|');

    const modal = Modal.advanced({
        type: Modal.types.iframe,
        title: 'test',
        content: contentUrl,
        size: Modal.sizes.large,
        callback: function (currentModal) {
            var iframe = currentModal.querySelector('iframe');
            if (!iframe) return;

            iframe.addEventListener('load', function() {
                var doc = iframe.contentDocument;
                if (!doc) return;

                doc.addEventListener('click', function(e) {
                    var el = e.target.closest('[data-filelist-element]');
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
    });

    return promise;
}

/**
 * Parse a TYPO3 TypoLink string into its components.
 * TypoLink format (order is crucial!): url target class title additionalParams
 * - Empty values use "-" as placeholder
 * - Values with spaces are enclosed in double quotes
 * - Backslash is used as escape character
 *
 * Examples:
 *   - "t3://page?uid=1"
 *   - "t3://page?uid=1 _blank"
 *   - "t3://page?uid=1 _blank my-class"
 *   - "t3://page?uid=1 _blank my-class "Click here""
 *   - "t3://page?uid=1 - - "Click here""
 *   - "t3://page?uid=1 _blank my-class "Click here" &foo=bar"
 *
 * @param {string} typoLink - The TypoLink string to parse
 * @return {Object} Object with href, target, class, title, additionalParams properties
 */
function parseTypoLink(typoLink) {
    const result = {
        href: '',
        target: '',
        class: '',
        title: '',
        additionalParams: ''
    };

    if (!typoLink || typeof typoLink !== 'string') {
        return result;
    }

    typoLink = typoLink.trim();
    if (typoLink === '') {
        return result;
    }

    // Parse using CSV-like logic (space delimiter, quote enclosure, backslash escape)
    // This mimics PHP's str_getcsv($typoLink, ' ', '"', '\\')
    const parts = parseTypoLinkParts(typoLink);

    // Order: url, target, class, title, additionalParams
    if (parts.length > 0 && parts[0] !== '-') {
        result.href = parts[0];
    }
    if (parts.length > 1 && parts[1] !== '-') {
        result.target = parts[1];
    }
    if (parts.length > 2 && parts[2] !== '-') {
        result.class = parts[2];
    }
    if (parts.length > 3 && parts[3] !== '-') {
        result.title = parts[3];
    }
    if (parts.length > 4 && parts[4] !== '-') {
        result.additionalParams = parts[4];
    }

    return result;
}

/**
 * Parse TypoLink string into parts using CSV-like logic.
 * Handles quoted strings with spaces and escaped characters.
 *
 * @param {string} str - The TypoLink string
 * @return {string[]} Array of parsed parts
 */
function parseTypoLinkParts(str) {
    const parts = [];
    let current = '';
    let inQuotes = false;
    let i = 0;

    while (i < str.length) {
        const char = str[i];

        if (char === '\\' && i + 1 < str.length) {
            // Escape sequence - include next character literally
            current += str[i + 1];
            i += 2;
            continue;
        }

        if (char === '"') {
            inQuotes = !inQuotes;
            i++;
            continue;
        }

        if (char === ' ' && !inQuotes) {
            // Delimiter - save current part and start new one
            if (current !== '' || parts.length > 0) {
                parts.push(current);
                current = '';
            }
            i++;
            continue;
        }

        current += char;
        i++;
    }

    // Don't forget the last part
    if (current !== '' || parts.length > 0) {
        parts.push(current);
    }

    return parts;
}

/**
 * Encode link data into TypoLink format.
 * This is the reverse of parseTypoLink.
 *
 * Format: url target class title additionalParams
 * - Empty values use '-' placeholder
 * - Values with spaces are quoted
 *
 * @param {Object} linkData - Object with href, target, class, title, additionalParams
 * @return {string} TypoLink string
 */
function encodeTypoLink(linkData) {
    const url = linkData.href || '';
    const target = linkData.target || '-';
    const cssClass = linkData.class || '-';
    const title = linkData.title || '-';
    const additionalParams = linkData.additionalParams || '-';

    // If URL is empty, return empty string
    if (!url) {
        return '';
    }

    // Quote values that contain spaces or special characters
    const quoteIfNeeded = function(value) {
        if (value === '-') {
            return '-';
        }
        // Quote if contains space, quote, or backslash
        if (value.indexOf(' ') !== -1 || value.indexOf('"') !== -1 || value.indexOf('\\') !== -1) {
            // Escape backslashes and quotes
            const escaped = value.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
            return '"' + escaped + '"';
        }
        return value;
    };

    // Build TypoLink parts array
    const parts = [url];

    // Only include parts up to the last non-empty value
    // This keeps the output minimal while still correct
    const hasAdditionalParams = additionalParams !== '-';
    const hasTitle = title !== '-' || hasAdditionalParams;
    const hasClass = cssClass !== '-' || hasTitle;
    const hasTarget = target !== '-' || hasClass;

    if (hasTarget) {
        parts.push(quoteIfNeeded(target));
    }
    if (hasClass) {
        parts.push(quoteIfNeeded(cssClass));
    }
    if (hasTitle) {
        parts.push(quoteIfNeeded(title));
    }
    if (hasAdditionalParams) {
        parts.push(quoteIfNeeded(additionalParams));
    }

    return parts.join(' ');
}

/**
 * Open TYPO3's link browser to select a link target.
 * Used by the image dialog to allow linking images to pages, files, URLs, etc.
 *
 * Uses the typo3image route with action=linkBrowser to get a FormEngine-style
 * link browser URL. Creates hidden form elements that the FormEngine link browser
 * adapter writes to when a link is selected.
 *
 * @param {Object} editor - The CKEditor instance
 * @param {string} currentValue - Current link value (optional)
 * @return {Promise} Promise that resolves with link data {href, target, title, class}
 */
function openLinkBrowser(editor, currentValue) {
    var resolvePromise, rejectPromise;
    var settled = false;
    var promise = new Promise(function(resolve, reject) {
        resolvePromise = function(value) { settled = true; resolve(value); };
        rejectPromise = function(reason) { settled = true; reject(reason); };
    });

    // Use the typo3image route with action=linkBrowser
    const baseUrl = editor.config.get('typo3image').routeUrl;
    if (!baseUrl) {
        console.error('typo3image.routeUrl not configured');
        rejectPromise('Link browser route not configured');
        return promise;
    }

    // Build URL for linkBrowser action
    const separator = baseUrl.indexOf('?') === -1 ? '?' : '&';
    const linkBrowserActionUrl = baseUrl + separator + 'action=linkBrowser&currentValue=' + encodeURIComponent(currentValue || '');

    // Fetch the wizard_link URL from our backend
    fetch(linkBrowserActionUrl).then(function(response) {
        if (!response.ok) {
            throw new Error('Link browser request failed: ' + response.status);
        }
        return response.json();
    }).then(function(response) {
        if (response.error) {
            console.error('Link browser error:', response.error);
            rejectPromise(response.error);
            return;
        }

        const linkBrowserUrl = response.url;

        // Create hidden form elements that the FormEngine link browser adapter expects
        // The adapter looks for: form[name="formName"] [data-formengine-input-name="itemName"]
        // IMPORTANT: The adapter's getParent() returns the list_frame (content iframe),
        // not window.top, so we must create the form in the current document
        const formName = 'typo3image_linkform';
        const itemName = 'typo3image_link';

        // The form must be in the current document (where CKEditor runs)
        // The link browser adapter's getParent() will return this frame
        const targetDoc = document;

        // Remove any existing form from previous link browser sessions
        const existingForm = targetDoc.querySelector('form[name="' + formName + '"]');
        if (existingForm) {
            existingForm.remove();
        }

        // Create hidden form that the link browser will write to
        const hiddenForm = targetDoc.createElement('form');
        hiddenForm.name = formName;
        hiddenForm.style.display = 'none';

        const hiddenInput = targetDoc.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = itemName;
        hiddenInput.setAttribute('data-formengine-input-name', itemName);
        hiddenInput.value = currentValue || '';

        hiddenForm.appendChild(hiddenInput);
        targetDoc.body.appendChild(hiddenForm);

        // Listen for changes on the hidden input (set by the link browser adapter)
        // When a link is selected, the adapter sets the value and dispatches 'change'
        // The adapter ALSO calls Modal.dismiss() to close the link browser modal
        // We should NOT call Modal.dismiss() again, as that would close the parent dialog
        const changeHandler = function() {
            const linkValue = hiddenInput.value;
            if (linkValue && linkValue !== currentValue) {
                // Clean up the event listener but keep the form until modal close
                hiddenInput.removeEventListener('change', changeHandler);

                // Parse the TypoLink string to extract URL, target, class, title, and params
                // TypoLink format: "url target class \"title\" additionalParams"
                // Example: "t3://page?uid=1 _blank my-link-class \"Click here\" &L=1"
                const linkData = parseTypoLink(linkValue);

                // Don't call Modal.dismiss() - the adapter already handles this
                // The modal will close and our typo3-modal-hidden handler will clean up
                resolvePromise(linkData);
            }
        };
        hiddenInput.addEventListener('change', changeHandler);

        // Open the link browser in a modal (standard TYPO3 pattern)
        const modal = Modal.advanced({
            type: Modal.types.iframe,
            title: TYPO3.lang['RTE.titleLinkBrowser'] || 'Link',
            content: linkBrowserUrl,
            size: Modal.sizes.large
        });

        // Handle modal close without selection
        modal.addEventListener('typo3-modal-hidden', function() {
            // Clean up hidden form from the target document
            const form = targetDoc.querySelector('form[name="' + formName + '"]');
            if (form) {
                form.remove();
            }

            if (!settled) {
                rejectPromise();
            }
        });

    }).catch(function(error) {
        console.error('Failed to get link browser URL:', error);
        rejectPromise('Failed to get link browser URL');
    });

    return promise;
}


function edit(selectedImage, editor, imageAttributes) {
    // Capture whether this is an inline image BEFORE the async operations
    // This flag is passed from the caller and indicates the original element type
    const isInlineImage = imageAttributes.isInlineImage || false;

    getImageInfo(editor, selectedImage.table, selectedImage.uid, {})
        .then(function (img) {
            return askImageAttributes(editor, img, imageAttributes, selectedImage.table);
        })
        .then(function (attributes) {
            editor.model.change(writer => {
                const newImageAttributes = {
                    fileUid: attributes.fileUid,
                    fileTable: attributes.fileTable,
                    src: attributes.src,
                    height: attributes.height,
                    width: attributes.width,
                    class: attributes.class,
                    title: attributes.title,
                    titleOverride: attributes['data-title-override'],
                    alt: attributes.alt,
                    altOverride: attributes['data-alt-override'],
                    enableZoom: attributes['data-htmlarea-zoom'] || false,
                    noScale: attributes['data-noscale'] || false,
                    quality: attributes['data-quality'] || ''
                };

                // Only set link attributes if they have non-empty values
                // IMPORTANT: Don't set empty strings to prevent unwanted link wrappers
                if (attributes.linkHref && attributes.linkHref.trim() !== '') {
                    newImageAttributes.imageLinkHref = attributes.linkHref;
                }
                if (attributes.linkTarget && attributes.linkTarget.trim() !== '') {
                    newImageAttributes.imageLinkTarget = attributes.linkTarget;
                }
                if (attributes.linkTitle && attributes.linkTitle.trim() !== '') {
                    newImageAttributes.imageLinkTitle = attributes.linkTitle;
                }
                if (attributes.linkClass && attributes.linkClass.trim() !== '') {
                    newImageAttributes.imageLinkClass = attributes.linkClass;
                }
                if (attributes.linkParams && attributes.linkParams.trim() !== '') {
                    newImageAttributes.imageLinkParams = attributes.linkParams;
                }

                // Create the appropriate element type based on whether this was an inline image
                // Inline images use typo3imageInline (no figure wrapper, flows with text)
                // Block images use typo3image (figure wrapper, standalone block)
                const elementType = isInlineImage ? 'typo3imageInline' : 'typo3image';
                const newImage = writer.createElement(elementType, newImageAttributes);

                // Create caption element if caption text exists (only for block images)
                // Inline images cannot have captions
                if (!isInlineImage) {
                    const captionText = (attributes.caption || '').trim();
                    if (captionText !== '') {
                        const captionElement = writer.createElement('typo3imageCaption');
                        writer.append(captionElement, newImage);
                        writer.insertText(captionText, captionElement);
                    }
                }

                editor.model.insertObject(newImage);
            });
        });
}


/**
 * Command to resize typo3image by updating width/height attributes
 * Integrates with WidgetResize plugin for visual drag handles
 */
class ResizeImageCommand extends Command {
    execute(options) {
        const model = this.editor.model;
        const imageElement = model.document.selection.getSelectedElement();

        if (!imageElement || !imageElement.is('element', 'typo3image')) {
            return;
        }

        model.change(writer => {
            // Update width attribute (height will be auto-calculated by aspect ratio)
            writer.setAttribute('width', options.width, imageElement);

            // Optionally update height if provided
            if (options.height) {
                writer.setAttribute('height', options.height, imageElement);
            }
        });
    }

    refresh() {
        const model = this.editor.model;
        const imageElement = model.document.selection.getSelectedElement();

        this.isEnabled = !!(imageElement && imageElement.is('element', 'typo3image'));
    }
}


/**
 * Command to toggle caption element on/off for typo3image
 * Enables inline caption editing when caption exists
 */
class ToggleCaptionCommand extends Command {
    refresh() {
        const editor = this.editor;
        const selection = editor.model.document.selection;
        const selectedElement = selection.getSelectedElement();

        // Enable if typo3image is selected
        this.isEnabled = selectedElement && selectedElement.name === 'typo3image';

        if (this.isEnabled) {
            // Set value to true if caption exists
            this.value = !!getCaptionFromImageModelElement(selectedElement);
        } else {
            this.value = false;
        }
    }

    execute() {
        const editor = this.editor;
        const model = editor.model;
        const selection = model.document.selection;
        const imageElement = selection.getSelectedElement();

        if (!imageElement || imageElement.name !== 'typo3image') {
            return;
        }

        model.change(writer => {
            const existingCaption = getCaptionFromImageModelElement(imageElement);

            if (existingCaption) {
                // REMOVE caption
                writer.remove(existingCaption);
            } else {
                // ADD caption
                const captionElement = writer.createElement('typo3imageCaption');
                writer.append(captionElement, imageElement);

                // Focus the caption for immediate editing
                writer.setSelection(captionElement, 'in');
            }
        });
    }
}


/**
 * Command to set image style (alignment and display)
 * Handles image-left, image-right, image-center, image-inline, image-block classes
 */
class SetImageStyleCommand extends Command {
    constructor(editor, styleDefinitions) {
        super(editor);
        this._styleDefinitions = styleDefinitions;
    }

    execute(options) {
        const { value } = options;
        const model = this.editor.model;
        const imageElement = model.document.selection.getSelectedElement();

        if (!imageElement || !imageElement.is('element', 'typo3image')) {
            return;
        }

        model.change(writer => {
            // Remove all style classes first
            const currentClass = imageElement.getAttribute('class') || '';
            const cleanedClass = currentClass
                .split(' ')
                .filter(cls => !cls.startsWith('image-'))
                .join(' ');

            // Add new style class
            const newClass = cleanedClass
                ? `${cleanedClass} ${value}`
                : value;

            writer.setAttribute('class', newClass.trim(), imageElement);
        });
    }

    refresh() {
        const model = this.editor.model;
        const imageElement = model.document.selection.getSelectedElement();

        this.isEnabled = !!(imageElement && imageElement.is('element', 'typo3image'));

        if (this.isEnabled) {
            const currentClass = imageElement.getAttribute('class') || '';
            this.value = this._getCurrentStyle(currentClass);
        } else {
            this.value = null;
        }
    }

    _getCurrentStyle(classString) {
        const classes = classString.split(' ');
        const styleClass = classes.find(cls => cls.startsWith('image-'));
        return styleClass || 'image-block';
    }
}


/**
 * Command to toggle between block (typo3image) and inline (typo3imageInline) image types
 * Block→Inline: Removes caption (inline images cannot have captions)
 * Inline→Block: Wraps image in figure
 */
class ToggleImageTypeCommand extends Command {
    refresh() {
        const editor = this.editor;
        const selection = editor.model.document.selection;
        const selectedElement = selection.getSelectedElement();

        // Enable if either typo3image or typo3imageInline is selected
        this.isEnabled = isTypo3ImageElement(selectedElement);

        if (this.isEnabled) {
            // Value is 'inline' if current element is inline, 'block' otherwise
            this.value = selectedElement.name === 'typo3imageInline' ? 'inline' : 'block';
        } else {
            this.value = null;
        }
    }

    execute() {
        const editor = this.editor;
        const model = editor.model;
        const selection = model.document.selection;
        const imageElement = selection.getSelectedElement();

        if (!imageElement) {
            return;
        }

        const isCurrentlyInline = imageElement.name === 'typo3imageInline';

        model.change(writer => {
            // Copy common attributes
            const commonAttributes = [
                'src', 'fileUid', 'fileTable', 'alt', 'altOverride',
                'title', 'titleOverride', 'width', 'height',
                'enableZoom', 'noScale', 'quality',
                'imageLinkHref', 'imageLinkTarget', 'imageLinkTitle', 'imageLinkClass', 'imageLinkParams'
            ];

            const attributes = {};
            for (const attr of commonAttributes) {
                const value = imageElement.getAttribute(attr);
                if (value !== undefined && value !== null && value !== '') {
                    attributes[attr] = value;
                }
            }

            // Handle class attribute - remove/add image-inline as needed
            let classValue = imageElement.getAttribute('class') || '';

            if (isCurrentlyInline) {
                // Inline → Block: remove image-inline class, add image-block if no other style class
                classValue = classValue
                    .split(' ')
                    .filter(cls => cls !== 'image-inline')
                    .join(' ')
                    .trim();

                // If no style class remains, default to image-block
                if (!classValue.match(/image-(left|right|center|block)/)) {
                    classValue = classValue ? classValue + ' image-block' : 'image-block';
                }
            } else {
                // Block → Inline: remove block style classes, add image-inline
                classValue = classValue
                    .split(' ')
                    .filter(cls => !cls.match(/^image-(left|right|center|block)$/))
                    .join(' ')
                    .trim();

                classValue = classValue ? classValue + ' image-inline' : 'image-inline';
            }

            attributes['class'] = classValue;

            // Create new element with appropriate type
            const newElementName = isCurrentlyInline ? 'typo3image' : 'typo3imageInline';
            const newImage = writer.createElement(newElementName, attributes);

            // Insert new element at the same position
            const position = writer.createPositionBefore(imageElement);
            writer.insert(newImage, position);

            // Remove old element
            writer.remove(imageElement);

            // Select the new element
            writer.setSelection(newImage, 'on');
        });
    }
}


/**
 * Check if element is a TYPO3 image (block or inline)
 * @param {Element} element - The model element to check
 * @return {boolean} True if element is typo3image or typo3imageInline
 */
function isTypo3ImageElement(element) {
    return element && (element.name === 'typo3image' || element.name === 'typo3imageInline');
}

/**
 * Get caption element from typo3image model element
 * @param {Element} imageElement - The typo3image model element
 * @return {Element|null} The typo3imageCaption element or null if not found
 */
function getCaptionFromImageModelElement(imageElement) {
    for (const child of imageElement.getChildren()) {
        if (child.is('element', 'typo3imageCaption')) {
            return child;
        }
    }
    return null;
}

/**
 * Check if caption element exists and has content
 * @param {Element} imageElement - The typo3image model element
 * @return {boolean} True if caption exists with content
 */
function hasCaptionContent(imageElement) {
    const caption = getCaptionFromImageModelElement(imageElement);
    return caption && caption.childCount > 0;
}

export default class Typo3Image extends Plugin {
    static pluginName = 'Typo3Image';

    static get requires() {
        // TYPO3's CKEditor 5 build includes these plugins
        // WidgetResize is also available but requires custom integration for typo3image model
        return ['StyleUtils', 'GeneralHtmlSupport', 'WidgetToolbarRepository'];
    }

    init() {
        const editor = this.editor;

        // Cache for translations to avoid multiple AJAX calls
        let translationsCache = null;

        /**
         * Fetch translations from the server.
         * Uses caching to avoid multiple AJAX calls.
         *
         * @return {Promise} Promise that resolves with translations object
         */
        const getTranslations = async function() {
            if (translationsCache) {
                return translationsCache;
            }

            const routeUrl = editor.config.get('typo3image').routeUrl;
            const url = routeUrl + '&action=info&fileId=translations';

            try {
                const fetchResponse = await fetch(url);
                if (!fetchResponse.ok) {
                    throw new Error('Translations request failed: ' + fetchResponse.status);
                }
                const response = await fetchResponse.json();
                translationsCache = response.lang;
                return translationsCache;
            } catch (error) {
                // Fallback to English if translation fetch fails
                console.error('Failed to fetch translations:', error);
                return {
                    insertImage: 'Insert image'
                };
            }
        };

        // Configure contextual balloon toolbar for both typo3image (block) and typo3imageInline widgets
        // Using a single toolbar registration for consistent UX across all image types.
        // Commands like toggleImageCaption and image styles will be automatically disabled
        // for inline images based on their isEnabled state.
        const widgetToolbarRepository = editor.plugins.get('WidgetToolbarRepository');
        widgetToolbarRepository.register('typo3image', {
            ariaLabel: 'Image toolbar',
            items: [
                'editTypo3Image',
                '|',
                'toggleImageCaption',
                '|',
                'toggleImageType',
                '|',
                'imageStyle:image-left',
                'imageStyle:image-center',
                'imageStyle:image-right',
                'imageStyle:image-block'
            ],
            getRelatedElement: selection => {
                try {
                    // Get the selected element from the view
                    const viewElement = selection.getSelectedElement();

                    if (!viewElement) {
                        return null;
                    }

                    // Ensure the element is still in the document (defensive check for balloon errors)
                    if (!viewElement.root || !viewElement.root.document) {
                        return null;
                    }

                    // Map view element to model element to check if it's a typo3image or typo3imageInline
                    const modelElement = editor.editing.mapper.toModelElement(viewElement);

                    if (isTypo3ImageElement(modelElement)) {
                        // Verify the view element is still valid for positioning
                        if (viewElement.is('element') && viewElement.parent) {
                            return viewElement;
                        }
                    }
                } catch (e) {
                    // Return null on any error to prevent balloon positioning issues
                    // This can happen when view elements are in an inconsistent state
                    return null;
                }

                return null;
            }
        });

        // Hide the link balloon when an image widget is selected to prevent balloon conflicts
        // This must happen BEFORE the widget toolbar shows to avoid stacking issues
        const contextualBalloon = editor.plugins.has('ContextualBalloon')
            ? editor.plugins.get('ContextualBalloon')
            : null;

        if (contextualBalloon) {
            // Listen to selection changes to hide link balloon when image is selected
            editor.model.document.selection.on('change:range', () => {
                const selectedElement = editor.model.document.selection.getSelectedElement();
                if (isTypo3ImageElement(selectedElement)) {
                    // Try to hide the link balloon if it's visible
                    try {
                        const linkUI = editor.plugins.has('LinkUI') ? editor.plugins.get('LinkUI') : null;
                        if (linkUI && linkUI.formView && contextualBalloon.hasView(linkUI.formView)) {
                            contextualBalloon.remove(linkUI.formView);
                        }
                        // Also try to remove link actions view
                        if (linkUI && linkUI.actionsView && contextualBalloon.hasView(linkUI.actionsView)) {
                            contextualBalloon.remove(linkUI.actionsView);
                        }
                    } catch (e) {
                        // Ignore errors when trying to hide link balloon
                    }
                }
            });
        }

        // Prevent Link plugin's balloon from showing when typo3image widget is selected
        // This resolves the conflict where both image toolbar and link balloon appear
        // Uses the same approach as CKEditor's LinkImageUI: stop event propagation
        // See: https://github.com/ckeditor/ckeditor5/issues/9607
        const viewDocument = editor.editing.view.document;

        // Helper to check if current selection is on a typo3image/typo3imageInline widget
        // Returns true if we should suppress the link balloon in favor of image toolbar
        const isSelectedTypo3ImageWidget = () => {
            const selection = editor.model.document.selection;
            const selectedElement = selection.getSelectedElement();

            // Always suppress link balloon for image widgets - image toolbar takes precedence
            if (isTypo3ImageElement(selectedElement)) {
                return true;
            }
            return false;
        };

        // Listen to click events with high priority to intercept before LinkUI
        // Stop event propagation to prevent LinkUI from showing its balloon
        // Note: There may be a visual artifact when clicking from link text to image
        // widget - this is a known limitation due to CKEditor's balloon stack behavior.
        this.listenTo(viewDocument, 'click', (evt, data) => {
            if (isSelectedTypo3ImageWidget()) {
                // Stop event propagation to prevent LinkUI from handling the click
                evt.stop();
                // Prevent default browser behavior
                data.preventDefault();
            }
        }, { priority: 'high' });

        const styleUtils = editor.plugins.get('StyleUtils');
        // Add listener to allow style sets for `img` element, when a `typo3image` element is selected
        this.listenTo(styleUtils, 'isStyleEnabledForBlock', (event, [style, element]) => {
            if (style.element === 'img') {
                for (const item of editor.model.document.selection.getFirstRange().getItems()) {
                    if (item.name === 'typo3image') {
                        event.return = true;
                    }
                }
            }
        })

        // Add listener to check if style is active for `img` element, when a `typo3image` element is selected
        this.listenTo(styleUtils, 'isStyleActiveForBlock', (event, [style, element]) => {
            if (style.element === 'img') {
                for (const item of editor.model.document.selection.getFirstRange().getItems()) {
                    if (item.name === 'typo3image') {
                        const classAttribute = item.getAttribute('class');
                        if (classAttribute && typeof classAttribute === 'string') {
                            const classlist = classAttribute.split(' ');
                            if (style.classes.filter(value => !classlist.includes(value)).length === 0) {
                                event.return = true;
                                break
                            }
                        }
                    }
                }
            }
        })

        // Add listener to return the correct `typo3image` model element for `img` style
        this.listenTo(styleUtils, 'getAffectedBlocks', (event, [style, element]) => {
            if (style.element === 'img') {
                for (const item of editor.model.document.selection.getFirstRange().getItems()) {
                    if (item.name === 'typo3image') {
                        event.return = [item]
                        break
                    }
                }
            }
        })

        const ghs = editor.plugins.get('GeneralHtmlSupport');
        // Convert `addModelHtmlClass` to an event
        ghs.decorate('addModelHtmlClass')
        // Add listener to update the `class` attribute of the `typo3image` element
        this.listenTo(ghs, 'addModelHtmlClass', (event, [viewElement, className, selectable]) => {
            if (selectable && selectable.name === 'typo3image') {
                editor.model.change(writer => {
                    writer.setAttribute('class', className.join(' '), selectable);
                })
            }
        })

        // Convert `removeModelHtmlClass` to an event
        ghs.decorate('removeModelHtmlClass')
        // Add listener to remove the `class` attribute of the `typo3image` element
        this.listenTo(ghs, 'removeModelHtmlClass', (event, [viewElement, className, selectable]) => {
            if (selectable && selectable.name === 'typo3image') {
                editor.model.change(writer => {
                    writer.removeAttribute('class', selectable);
                })
            }
        })

        editor.editing.view.addObserver(Typo3ImageDoubleClickObserver);

        // Register typo3image schema with link attributes
        // Link handling is done through custom upcast/downcast converters
        // Note: CKEditor's LinkImage plugin cannot be used due to conflict with TYPO3's Typo3LinkEditing
        // See: Documentation/ADR/001-native-ckeditor-architecture-linkimage-incompatibility.md
        editor.model.schema.register('typo3image', {
            inheritAllFrom: '$blockObject',
            allowIn: ['$text', '$block'],
            allowAttributes: [
                'src',
                'fileUid',
                'fileTable',
                'alt',
                'altOverride',
                'title',
                'titleOverride',
                'class',
                'enableZoom',
                'noScale',
                'width',
                'height',
                'quality',
                // Note: 'htmlA' intentionally NOT included - we handle links via imageLinkHref/imageLinkTarget/imageLinkTitle/imageLinkClass/imageLinkParams
                // Including htmlA would cause GHS to output duplicate <a> elements
                // IMPORTANT: We use 'imageLink*' prefix instead of 'link*' to prevent TYPO3's Typo3LinkEditing plugin
                // from recognizing these as link attributes and adding its own outer <a> wrapper
                'imageLinkHref',
                'imageLinkTarget',
                'imageLinkTitle',
                'imageLinkClass',
                'imageLinkParams'
            ],
        });

        // Register typo3imageCaption element schema for inline editable captions
        editor.model.schema.register('typo3imageCaption', {
            allowIn: 'typo3image',
            allowContentOf: '$block',
            isLimit: true
        });

        // Extend typo3image to allow caption child element
        editor.model.schema.extend('typo3image', {
            allowChildren: 'typo3imageCaption'
        });

        // Register typo3imageInline schema - inherits from $inlineObject for true inline behavior
        // This enables cursor positioning before/after the image on the same line
        // Note: Inline images cannot have captions (no typo3imageCaption child allowed)
        editor.model.schema.register('typo3imageInline', {
            inheritAllFrom: '$inlineObject',
            allowAttributes: [
                'src',
                'fileUid',
                'fileTable',
                'alt',
                'altOverride',
                'title',
                'titleOverride',
                'class',
                'enableZoom',
                'noScale',
                'width',
                'height',
                'quality',
                'imageLinkHref',
                'imageLinkTarget',
                'imageLinkTitle',
                'imageLinkClass',
                'imageLinkParams'
            ],
        });

        // Upcast converter for corrupted double-link structure: <a><a><img></a></a>
        // This handles content that was corrupted by previous save cycles
        editor.conversion
            .for('upcast')
            .elementToElement({
                view: {
                    name: 'a'
                },
                model: (viewOuterLink, { writer, consumable }) => {
                    // Check if this <a> contains another <a> with an img
                    let innerLink = null;
                    let imgElement = null;

                    for (const child of viewOuterLink.getChildren()) {
                        if (child.is('element', 'a')) {
                            innerLink = child;
                            // Find img inside inner link
                            for (const innerChild of child.getChildren()) {
                                if (innerChild.is('element', 'img') && innerChild.getAttribute('data-htmlarea-file-uid')) {
                                    imgElement = innerChild;
                                    break;
                                }
                            }
                        }
                    }

                    if (!innerLink || !imgElement) {
                        return null; // Not a double-link structure
                    }

                    // Test consumability
                    if (!consumable.test(viewOuterLink, { name: true }) ||
                        !consumable.test(innerLink, { name: true }) ||
                        !consumable.test(imgElement, { name: true })) {
                        return null;
                    }

                    // Consume all elements
                    consumable.consume(viewOuterLink, { name: true });
                    consumable.consume(innerLink, { name: true });
                    consumable.consume(imgElement, { name: true });

                    // Use inner link attributes (they have more complete info like data-link-params)
                    const linkHref = innerLink.getAttribute('href') || viewOuterLink.getAttribute('href') || '';
                    const linkTarget = innerLink.getAttribute('target') || viewOuterLink.getAttribute('target') || '';
                    const linkTitle = innerLink.getAttribute('title') || viewOuterLink.getAttribute('title') || '';
                    const linkClass = innerLink.getAttribute('class') || viewOuterLink.getAttribute('class') || '';
                    const linkParams = innerLink.getAttribute('data-link-params') || viewOuterLink.getAttribute('data-link-params') || '';

                    const imageAttributes = {
                        fileUid: imgElement.getAttribute('data-htmlarea-file-uid'),
                        fileTable: imgElement.getAttribute('data-htmlarea-file-table') || 'sys_file',
                        src: imgElement.getAttribute('src'),
                        width: imgElement.getAttribute('width') || '',
                        height: imgElement.getAttribute('height') || '',
                        class: imgElement.getAttribute('class') || '',
                        alt: imgElement.getAttribute('alt') || '',
                        altOverride: imgElement.getAttribute('data-alt-override') || false,
                        title: imgElement.getAttribute('title') || '',
                        titleOverride: imgElement.getAttribute('data-title-override') || false,
                        enableZoom: imgElement.getAttribute('data-htmlarea-zoom') || false,
                        noScale: imgElement.getAttribute('data-noscale') || false,
                        quality: imgElement.getAttribute('data-quality') || ''
                    };

                    if (linkHref && linkHref.trim() !== '' && linkHref.trim() !== '/') {
                        imageAttributes.imageLinkHref = linkHref;
                        if (linkTarget && linkTarget.trim() !== '') {
                            imageAttributes.imageLinkTarget = linkTarget;
                        }
                        if (linkTitle && linkTitle.trim() !== '') {
                            imageAttributes.imageLinkTitle = linkTitle;
                        }
                        // Filter out alignment classes
                        const alignmentClasses = ['image-left', 'image-center', 'image-right', 'image-block', 'image-inline'];
                        const linkClassParts = (linkClass || '').split(' ').filter(c => c.trim() !== '');
                        const actualLinkClass = linkClassParts.filter(c => !alignmentClasses.includes(c)).join(' ');
                        if (actualLinkClass) {
                            imageAttributes.imageLinkClass = actualLinkClass;
                        }
                        if (linkParams && linkParams.trim() !== '') {
                            imageAttributes.imageLinkParams = linkParams;
                        }
                    }

                    // Check image class to create correct element type (inline vs block)
                    const imgClass = (imageAttributes.class || '').toString();
                    const isInline = imgClass.split(/\s+/).includes('image-inline');
                    return writer.createElement(isInline ? 'typo3imageInline' : 'typo3image', imageAttributes);
                },
                converterPriority: 'highest'
            });

        // Upcast converter for linked figure: <a href="..."><figure class="image">...</figure></a>
        // This handles legacy/alternate structure where link wraps the figure
        // Must run before the regular figure upcast to consume the outer <a>
        editor.conversion
            .for('upcast')
            .elementToElement({
                view: {
                    name: 'a'
                },
                model: (viewLink, { writer, consumable }) => {
                    // Check if this <a> contains a figure.image
                    let figureElement = null;
                    for (const child of viewLink.getChildren()) {
                        if (child.is('element', 'figure') && child.hasClass('image')) {
                            figureElement = child;
                            break;
                        }
                    }

                    if (!figureElement) {
                        return null; // Not a linked figure, let other converters handle
                    }

                    // Find img element within figure
                    // Also track inner link element if img is wrapped in <a>
                    let imgElement = null;
                    let figcaptionElement = null;
                    let innerLinkElement = null;
                    for (const child of figureElement.getChildren()) {
                        if (child.is('element', 'img')) {
                            imgElement = child;
                        } else if (child.is('element', 'figcaption')) {
                            figcaptionElement = child;
                        } else if (child.is('element', 'a')) {
                            // Image might be wrapped in inner <a> too
                            innerLinkElement = child;
                            for (const innerChild of child.getChildren()) {
                                if (innerChild.is('element', 'img')) {
                                    imgElement = innerChild;
                                }
                            }
                        }
                    }

                    if (!imgElement || !imgElement.getAttribute('data-htmlarea-file-uid')) {
                        return null;
                    }

                    // Test consumability before committing
                    if (!consumable.test(viewLink, { name: true }) ||
                        !consumable.test(figureElement, { name: true }) ||
                        !consumable.test(imgElement, { name: true })) {
                        return null;
                    }

                    // Consume all elements to prevent GHS from preserving them
                    consumable.consume(viewLink, { name: true });
                    consumable.consume(figureElement, { name: true });
                    consumable.consume(imgElement, { name: true });
                    // CRITICAL: Also consume inner link element to prevent duplicate links
                    if (innerLinkElement && consumable.test(innerLinkElement, { name: true })) {
                        consumable.consume(innerLinkElement, { name: true });
                    }

                    // Extract link attributes - prefer inner link for data-link-params, outer for class
                    const linkHref = viewLink.getAttribute('href') || innerLinkElement?.getAttribute('href') || '';
                    const linkTarget = viewLink.getAttribute('target') || innerLinkElement?.getAttribute('target') || '';
                    const linkTitle = viewLink.getAttribute('title') || innerLinkElement?.getAttribute('title') || '';
                    // linkClass from outer or inner link
                    const linkClass = viewLink.getAttribute('class') || innerLinkElement?.getAttribute('class') || '';
                    // data-link-params is typically on inner link
                    const linkParams = innerLinkElement?.getAttribute('data-link-params') || viewLink.getAttribute('data-link-params') || '';

                    // Build image attributes
                    const imageAttributes = {
                        fileUid: imgElement.getAttribute('data-htmlarea-file-uid'),
                        fileTable: imgElement.getAttribute('data-htmlarea-file-table') || 'sys_file',
                        src: imgElement.getAttribute('src'),
                        width: imgElement.getAttribute('width') || '',
                        height: imgElement.getAttribute('height') || '',
                        class: figureElement.getAttribute('class') || '',
                        alt: imgElement.getAttribute('alt') || '',
                        altOverride: imgElement.getAttribute('data-alt-override') || false,
                        title: imgElement.getAttribute('title') || '',
                        titleOverride: imgElement.getAttribute('data-title-override') || false,
                        enableZoom: imgElement.getAttribute('data-htmlarea-zoom') || false,
                        noScale: imgElement.getAttribute('data-noscale') || false,
                        quality: imgElement.getAttribute('data-quality') || ''
                    };

                    // Add link attributes if valid
                    if (linkHref && linkHref.trim() !== '' && linkHref.trim() !== '/') {
                        imageAttributes.imageLinkHref = linkHref;
                        if (linkTarget && linkTarget.trim() !== '') {
                            imageAttributes.imageLinkTarget = linkTarget;
                        }
                        if (linkTitle && linkTitle.trim() !== '') {
                            imageAttributes.imageLinkTitle = linkTitle;
                        }
                        // Filter out alignment classes from linkClass
                        const alignmentClasses = ['image-left', 'image-center', 'image-right', 'image-block', 'image-inline'];
                        const linkClassParts = (linkClass || '').split(' ').filter(c => c.trim() !== '');
                        const actualLinkClass = linkClassParts.filter(c => !alignmentClasses.includes(c)).join(' ');
                        const alignmentFromLink = linkClassParts.filter(c => alignmentClasses.includes(c)).join(' ');

                        if (actualLinkClass) {
                            imageAttributes.imageLinkClass = actualLinkClass;
                        }
                        // Add alignment classes to figure class if they were on the link
                        if (alignmentFromLink) {
                            const existingClass = imageAttributes.class || '';
                            const existingParts = existingClass.split(' ').filter(c => c.trim() !== '' && !alignmentClasses.includes(c));
                            imageAttributes.class = [...existingParts, alignmentFromLink].join(' ');
                        }
                        if (linkParams && linkParams.trim() !== '') {
                            imageAttributes.imageLinkParams = linkParams;
                        }
                    }

                    const typo3image = writer.createElement('typo3image', imageAttributes);

                    // Handle caption
                    if (figcaptionElement) {
                        const captionElement = writer.createElement('typo3imageCaption');
                        writer.append(captionElement, typo3image);

                        for (const node of figcaptionElement.getChildren()) {
                            if (node.is('$text')) {
                                writer.insertText(node.data, captionElement);
                            }
                        }
                    }

                    return typo3image;
                },
                converterPriority: 'highest'
            });

        // Upcast converter for figure with caption (higher priority)
        // Handles: <figure class="image"><img data-htmlarea-file-uid="..."><figcaption>Caption text</figcaption></figure>
        editor.conversion
            .for('upcast')
            .elementToElement({
                view: {
                    name: 'figure',
                    classes: ['image']
                },
                model: (viewFigure, { writer, consumable }) => {
                    // Skip if parent is a link - handled by the linked figure upcast above
                    const parent = viewFigure.parent;
                    if (parent && parent.is('element', 'a')) {
                        return null;
                    }

                    // Find img and figcaption elements within figure
                    // Note: img may be wrapped in <a> element when linked
                    let imgElement = null;
                    let figcaptionElement = null;

                    for (const child of viewFigure.getChildren()) {
                        if (child.is('element', 'img')) {
                            imgElement = child;
                        } else if (child.is('element', 'figcaption')) {
                            figcaptionElement = child;
                        } else if (child.is('element', 'a')) {
                            // Look for img inside link wrapper
                            for (const linkChild of child.getChildren()) {
                                if (linkChild.is('element', 'img')) {
                                    imgElement = linkChild;
                                    break;
                                }
                            }
                        }
                    }

                    // Figure must contain an img with data-htmlarea-file-uid
                    if (!imgElement || !imgElement.getAttribute('data-htmlarea-file-uid')) {
                        return null;
                    }

                    // Check if this is an inline image (figure has image-inline class)
                    // If so, create typo3imageInline instead of typo3image
                    const figureClasses = viewFigure.getAttribute('class') || '';
                    const figureClassList = figureClasses.split(/\s+/);
                    const isInlineImage = figureClassList.includes('image-inline');

                    // Check if image is wrapped in a link element (inside figure)
                    const linkElement = imgElement.parent?.is('element', 'a') ? imgElement.parent : null;

                    // CRITICAL: Consume the link element to prevent GHS from preserving it
                    // This prevents duplicate <a> tags in the output
                    if (linkElement && consumable.test(linkElement, { name: true })) {
                        consumable.consume(linkElement, { name: true });
                    }

                    // Extract link attributes if link wrapper exists
                    const linkHref = linkElement?.getAttribute('href') || '';
                    const linkTarget = linkElement?.getAttribute('target') || '';
                    const linkTitle = linkElement?.getAttribute('title') || '';
                    const linkClass = linkElement?.getAttribute('class') || '';
                    const linkParams = linkElement?.getAttribute('data-link-params') || '';

                    const imageAttributes = {
                        fileUid: imgElement.getAttribute('data-htmlarea-file-uid'),
                        fileTable: imgElement.getAttribute('data-htmlarea-file-table') || 'sys_file',
                        src: imgElement.getAttribute('src'),
                        width: imgElement.getAttribute('width') || '',
                        height: imgElement.getAttribute('height') || '',
                        class: viewFigure.getAttribute('class') || '',
                        alt: imgElement.getAttribute('alt') || '',
                        altOverride: imgElement.getAttribute('data-alt-override') || false,
                        title: imgElement.getAttribute('title') || '',
                        titleOverride: imgElement.getAttribute('data-title-override') || false,
                        enableZoom: imgElement.getAttribute('data-htmlarea-zoom') || false,
                        noScale: imgElement.getAttribute('data-noscale') || false,
                        quality: imgElement.getAttribute('data-quality') || ''
                    };

                    // Only set link attributes if they have non-empty values
                    if (linkHref && linkHref.trim() !== '' && linkHref.trim() !== '/') {
                        imageAttributes.imageLinkHref = linkHref;
                        if (linkTarget && linkTarget.trim() !== '') {
                            imageAttributes.imageLinkTarget = linkTarget;
                        }
                        if (linkTitle && linkTitle.trim() !== '') {
                            imageAttributes.imageLinkTitle = linkTitle;
                        }
                        if (linkClass && linkClass.trim() !== '') {
                            imageAttributes.imageLinkClass = linkClass;
                        }
                        if (linkParams && linkParams.trim() !== '') {
                            imageAttributes.imageLinkParams = linkParams;
                        }
                    }

                    // Create typo3image or typo3imageInline based on figure class
                    if (isInlineImage) {
                        // Inline images: no caption support, simpler structure
                        return writer.createElement('typo3imageInline', imageAttributes);
                    }

                    // Block image: may have caption
                    const typo3image = writer.createElement('typo3image', imageAttributes);

                    // Create caption element if figcaption exists
                    if (figcaptionElement) {
                        const captionElement = writer.createElement('typo3imageCaption');
                        writer.append(captionElement, typo3image);

                        // Copy caption text content
                        for (const node of figcaptionElement.getChildren()) {
                            if (node.is('$text')) {
                                writer.insertText(node.data, captionElement);
                            }
                        }
                    }

                    return typo3image;
                },
                converterPriority: 'highest'
            });

        // Upcast converter for linked images: <a href="..."><img data-htmlarea-file-uid="..."></a>
        // CRITICAL FIX for #565: This converter CONSUMES the <a> element to prevent
        // duplicate links. Without this, GHS preserves the <a> and downcast creates another.
        // Must run before standalone img converter to handle linked images correctly.
        // NOTE: Linked images inside <figure> are handled by the figure upcast converter above.
        editor.conversion
            .for('upcast')
            .elementToElement({
                view: {
                    name: 'a'
                },
                model: (viewElement, { writer, consumable }) => {
                    // Skip if parent is a figure element - handled by figure upcast converter
                    // CRITICAL: Still consume the <a> to prevent GHS from preserving it
                    const parentElement = viewElement.parent;
                    if (parentElement?.is('element', 'figure') && parentElement?.hasClass('image')) {
                        // Consume the <a> even though we return null - prevents duplicate links
                        consumable.consume(viewElement, { name: true });
                        return null;
                    }

                    // Find img child with data-htmlarea-file-uid
                    // Also check if link contains ONLY the image (no other content)
                    let imgElement = null;
                    let hasOtherContent = false;

                    for (const child of viewElement.getChildren()) {
                        if (child.is('element', 'img') && child.getAttribute('data-htmlarea-file-uid')) {
                            if (imgElement) {
                                // Already found an image - multiple images means other content
                                hasOtherContent = true;
                            }
                            imgElement = child;
                        } else if (child.is('element', 'img')) {
                            // Image without data-htmlarea-file-uid is other content
                            hasOtherContent = true;
                        } else if (child.is('$text') && child.data.trim() !== '') {
                            // Link contains text content besides the image
                            hasOtherContent = true;
                        } else if (child.is('element')) {
                            // Link contains other elements
                            hasOtherContent = true;
                        }
                    }

                    // If no TYPO3 image found, let other converters handle this <a>
                    if (!imgElement) {
                        return null;
                    }

                    // CRITICAL: If link contains text or other elements besides the image,
                    // do NOT consume the link - let the normal link handling preserve the text.
                    // The inline image upcast will handle the img separately.
                    if (hasOtherContent) {
                        return null;
                    }

                    // Extract link attributes from <a> element
                    const linkHref = viewElement.getAttribute('href') || '';
                    const linkTarget = viewElement.getAttribute('target') || '';
                    const linkTitle = viewElement.getAttribute('title') || '';
                    const linkClass = viewElement.getAttribute('class') || '';
                    const linkParams = viewElement.getAttribute('data-link-params') || '';

                    // Determine if this is a real link (non-empty, non-placeholder href)
                    const hasValidLink = linkHref && linkHref.trim() !== '' && linkHref.trim() !== '/';

                    // Test if both elements can be consumed before committing to conversion
                    // Using test() first ensures we don't partially consume elements
                    if (
                        !consumable.test(viewElement, { name: true }) ||
                        !consumable.test(imgElement, { name: true })
                    ) {
                        return null;
                    }

                    // Consume the <a> element and its children to prevent GHS from processing
                    // Note: Only consume 'name' - consuming all attributes causes iteration error
                    consumable.consume(viewElement, { name: true });
                    consumable.consume(imgElement, { name: true });

                    // Build image attributes from the img element
                    const imageAttributes = {
                        fileUid: imgElement.getAttribute('data-htmlarea-file-uid'),
                        fileTable: imgElement.getAttribute('data-htmlarea-file-table') || 'sys_file',
                        src: imgElement.getAttribute('src'),
                        width: imgElement.getAttribute('width') || '',
                        height: imgElement.getAttribute('height') || '',
                        class: imgElement.getAttribute('class') || '',
                        alt: imgElement.getAttribute('alt') || '',
                        altOverride: imgElement.getAttribute('data-alt-override') || false,
                        title: imgElement.getAttribute('title') || '',
                        titleOverride: imgElement.getAttribute('data-title-override') || false,
                        enableZoom: imgElement.getAttribute('data-htmlarea-zoom') || false,
                        noScale: imgElement.getAttribute('data-noscale') || false,
                        quality: imgElement.getAttribute('data-quality') || ''
                    };

                    // Only add link attributes if href is valid (not empty/placeholder)
                    if (hasValidLink) {
                        imageAttributes.imageLinkHref = linkHref;
                        if (linkTarget && linkTarget.trim() !== '') {
                            imageAttributes.imageLinkTarget = linkTarget;
                        }
                        if (linkTitle && linkTitle.trim() !== '') {
                            imageAttributes.imageLinkTitle = linkTitle;
                        }
                        if (linkClass && linkClass.trim() !== '') {
                            imageAttributes.imageLinkClass = linkClass;
                        }
                        if (linkParams && linkParams.trim() !== '') {
                            imageAttributes.imageLinkParams = linkParams;
                        }
                    }

                    // Check if this should be an inline image based on class
                    const imgClass = imageAttributes.class || '';
                    const classList = imgClass.split(/\s+/);
                    const isInlineImage = classList.includes('image-inline');

                    return writer.createElement(isInlineImage ? 'typo3imageInline' : 'typo3image', imageAttributes);
                },
                converterPriority: 'highest'
            });

        // Upcast converter for inline images (highest priority)
        // Handles: <img class="image-inline" data-htmlarea-file-uid="..." src="...">
        // These become typo3imageInline model elements for true inline behavior
        editor.conversion
            .for('upcast')
            .elementToElement({
                view: {
                    name: 'img',
                    attributes: [
                        'data-htmlarea-file-uid',
                        'src',
                    ]
                },
                model: (viewElement, { writer }) => {
                    // Check if this image should be inline
                    const className = viewElement.getAttribute('class') || '';
                    const classList = className.split(/\s+/);
                    const hasInlineClass = classList.includes('image-inline');

                    // If no inline class, let the block converter handle it
                    if (!hasInlineClass) {
                        return null;
                    }

                    // Check if image is wrapped in a link element
                    const linkElement = viewElement.parent?.name === 'a' ? viewElement.parent : null;

                    // Check if link contains ONLY this image (no other content)
                    // If link has other content, don't extract link attrs - let CKEditor handle the link
                    let linkHasOnlyImage = true;
                    if (linkElement) {
                        for (const sibling of linkElement.getChildren()) {
                            if (sibling === viewElement) continue;
                            if (sibling.is('$text') && sibling.data.trim() !== '') {
                                linkHasOnlyImage = false;
                                break;
                            }
                            if (sibling.is('element')) {
                                linkHasOnlyImage = false;
                                break;
                            }
                        }
                    }

                    // Extract link attributes only if link wraps ONLY this image
                    const effectiveLinkElement = (linkElement && linkHasOnlyImage) ? linkElement : null;
                    const linkHref = effectiveLinkElement?.getAttribute('href') || '';
                    const linkTarget = effectiveLinkElement?.getAttribute('target') || '';
                    const linkTitle = effectiveLinkElement?.getAttribute('title') || '';
                    const linkClass = effectiveLinkElement?.getAttribute('class') || '';
                    const linkParams = effectiveLinkElement?.getAttribute('data-link-params') || '';

                    const imageAttributes = {
                        fileUid: viewElement.getAttribute('data-htmlarea-file-uid'),
                        fileTable: viewElement.getAttribute('data-htmlarea-file-table') || 'sys_file',
                        src: viewElement.getAttribute('src'),
                        width: viewElement.getAttribute('width') || '',
                        height: viewElement.getAttribute('height') || '',
                        class: className,
                        alt: viewElement.getAttribute('alt') || '',
                        altOverride: viewElement.getAttribute('data-alt-override') || false,
                        title: viewElement.getAttribute('title') || '',
                        titleOverride: viewElement.getAttribute('data-title-override') || false,
                        enableZoom: viewElement.getAttribute('data-htmlarea-zoom') || false,
                        noScale: viewElement.getAttribute('data-noscale') || false,
                        quality: viewElement.getAttribute('data-quality') || ''
                    };

                    // Only set link attributes if they have non-empty values
                    if (linkHref && linkHref.trim() !== '' && linkHref.trim() !== '/') {
                        imageAttributes.imageLinkHref = linkHref;
                        if (linkTarget && linkTarget.trim() !== '') {
                            imageAttributes.imageLinkTarget = linkTarget;
                        }
                        if (linkTitle && linkTitle.trim() !== '') {
                            imageAttributes.imageLinkTitle = linkTitle;
                        }
                        if (linkClass && linkClass.trim() !== '') {
                            imageAttributes.imageLinkClass = linkClass;
                        }
                        if (linkParams && linkParams.trim() !== '') {
                            imageAttributes.imageLinkParams = linkParams;
                        }
                    }

                    return writer.createElement('typo3imageInline', imageAttributes);
                },
                converterPriority: 'highest'
            });

        // Upcast converter for standalone img (backward compatibility)
        // Handles: <img data-htmlarea-file-uid="..." src="...">
        // NOTE: Linked images are now handled by the converter above
        editor.conversion
            .for('upcast')
            .elementToElement({
                view: {
                    name: 'img',
                    attributes: [
                        'data-htmlarea-file-uid',
                        'src',
                    ]
                },
                model: (viewElement, { writer }) => {
                    // Skip if parent is <a> - handled by linked image converter above
                    // This prevents double processing
                    if (viewElement.parent?.name === 'a') {
                        return null;
                    }

                    const imageAttributes = {
                        fileUid: viewElement.getAttribute('data-htmlarea-file-uid'),
                        fileTable: viewElement.getAttribute('data-htmlarea-file-table') || 'sys_file',
                        src: viewElement.getAttribute('src'),
                        width: viewElement.getAttribute('width') || '',
                        height: viewElement.getAttribute('height') || '',
                        class: viewElement.getAttribute('class') || '',
                        alt: viewElement.getAttribute('alt') || '',
                        altOverride: viewElement.getAttribute('data-alt-override') || false,
                        title: viewElement.getAttribute('title') || '',
                        titleOverride: viewElement.getAttribute('data-title-override') || false,
                        enableZoom: viewElement.getAttribute('data-htmlarea-zoom') || false,
                        noScale: viewElement.getAttribute('data-noscale') || false,
                        quality: viewElement.getAttribute('data-quality') || ''
                    };

                    return writer.createElement('typo3image', imageAttributes);
                },
                converterPriority: 'high'
            });

        // Helper function to create view element for typo3image
        const createImageViewElement = (modelElement, writer) => {
            const attributes= {
                'src': modelElement.getAttribute('src'),
                'data-htmlarea-file-uid': modelElement.getAttribute('fileUid'),
                'data-htmlarea-file-table': modelElement.getAttribute('fileTable'),
                'width': modelElement.getAttribute('width'),
                'height': modelElement.getAttribute('height'),
                // NOTE: class is applied to figure via attributeToAttribute converter (line 1394)
                // not to img element to avoid double margin application
                'title': modelElement.getAttribute('title') || '',
                'alt': modelElement.getAttribute('alt') || '',
            }

            if (modelElement.getAttribute('titleOverride') || false) {
                attributes['data-title-override'] = true
            }

            if (modelElement.getAttribute('altOverride') || false) {
                attributes['data-alt-override'] = true
            }

            if (modelElement.getAttribute('enableZoom') || false) {
                attributes['data-htmlarea-zoom'] = true
            }

            if (modelElement.getAttribute('noScale') || false) {
                attributes['data-noscale'] = true
            }

            const quality = modelElement.getAttribute('quality') || '';
            if (quality) {
                attributes['data-quality'] = quality
            }

            // Add data-caption attribute for PHP backend compatibility
            // The caption is stored in typo3imageCaption child element
            // This ensures PHP can access the caption even though it only sees <img> attributes
            const captionElement = getCaptionFromImageModelElement(modelElement);
            if (captionElement && captionElement.childCount > 0) {
                let captionText = '';
                for (const child of captionElement.getChildren()) {
                    if (child.is('$text')) {
                        captionText += child.data;
                    }
                }
                if (captionText.trim()) {
                    attributes['data-caption'] = captionText;
                }
            }

            const imgElement = writer.createEmptyElement('img', attributes);

            // Check if model has link attributes and wrap in <a> if present
            // Treat "/" as "no link" since it's TYPO3 link browser default/placeholder value
            const linkHref = modelElement.getAttribute('imageLinkHref');
            if (linkHref && linkHref.trim() !== '' && linkHref.trim() !== '/') {
                const linkAttributes = {
                    href: linkHref
                };

                // Add optional link attributes only if they have values
                const linkTarget = modelElement.getAttribute('imageLinkTarget');
                if (linkTarget && linkTarget.trim() !== '') {
                    linkAttributes.target = linkTarget;
                }

                const linkTitle = modelElement.getAttribute('imageLinkTitle');
                if (linkTitle && linkTitle.trim() !== '') {
                    linkAttributes.title = linkTitle;
                }

                const linkClass = modelElement.getAttribute('imageLinkClass');
                if (linkClass && linkClass.trim() !== '') {
                    linkAttributes.class = linkClass;
                }

                // Store linkParams as data attribute for TYPO3 to process on render
                // TYPO3's frontend rendering will append these to the final URL
                // We don't append to href here to avoid doubling on save/load cycles
                const linkParams = modelElement.getAttribute('imageLinkParams');
                if (linkParams && linkParams.trim() !== '') {
                    linkAttributes['data-link-params'] = linkParams;
                }

                // Wrap image in link element
                return writer.createContainerElement('a', linkAttributes, imgElement);
            }

            return imgElement;
        };

        // Editing downcast - creates figure widget with editable caption slot
        editor.conversion
            .for('editingDowncast')
            .elementToStructure({
                model: {
                    name: 'typo3image',
                    attributes: [
                        'fileUid',
                        'fileTable',
                        'src'
                    ]
                },
                view: (modelElement, { writer }) => {
                    const imageElement = createImageViewElement(modelElement, writer);

                    // Always use figure wrapper for consistency
                    const figure = writer.createContainerElement('figure', {
                        class: 'image ck-widget ck-widget_with-resizer'
                    });

                    // Insert image into figure
                    writer.insert(writer.createPositionAt(figure, 0), imageElement);

                    // Add visual indicators for link and zoom
                    const linkHref = modelElement.getAttribute('imageLinkHref');
                    const hasLink = linkHref && linkHref.trim() !== '' && linkHref.trim() !== '/';
                    const hasZoom = modelElement.getAttribute('enableZoom');

                    if (hasLink || hasZoom) {
                        const indicatorContainer = writer.createContainerElement('span', {
                            class: 'ck-image-indicators'
                        });

                        if (hasZoom) {
                            // Zoom/enlarge indicator (magnifying glass icon)
                            const zoomIndicator = writer.createContainerElement('span', {
                                class: 'ck-image-indicator ck-image-indicator--zoom',
                                title: 'Click to enlarge'
                            });
                            writer.insert(writer.createPositionAt(indicatorContainer, 'end'), zoomIndicator);
                        }

                        if (hasLink) {
                            // Link indicator (chain link icon)
                            const linkIndicator = writer.createContainerElement('span', {
                                class: 'ck-image-indicator ck-image-indicator--link',
                                title: linkHref
                            });
                            writer.insert(writer.createPositionAt(indicatorContainer, 'end'), linkIndicator);
                        }

                        writer.insert(writer.createPositionAt(figure, 'end'), indicatorContainer);
                    }

                    // Create caption slot (renders all children including typo3imageCaption)
                    const captionSlot = writer.createSlot();
                    writer.insert(writer.createPositionAt(figure, 'end'), captionSlot);

                    return toWidget(figure, writer, {
                        label: 'image widget',
                        hasSelectionHandle: true
                    });
                },
            });

        // Editing downcast for caption element - makes it inline editable
        editor.conversion
            .for('editingDowncast')
            .elementToElement({
                model: 'typo3imageCaption',
                view: (modelElement, { writer }) => {
                    const figcaption = writer.createEditableElement('figcaption', {
                        class: 'ck-image-caption'
                    });

                    // THIS IS THE MAGIC: toWidgetEditable makes caption inline-editable
                    return toWidgetEditable(figcaption, writer, {
                        placeholder: 'Enter image caption'
                    });
                }
            });

        // Data downcast - outputs clean HTML for saving (no widget wrapper)
        // Creates figure/figcaption structure when caption element exists
        editor.conversion
            .for('dataDowncast')
            .elementToStructure({
                model: {
                    name: 'typo3image',
                    attributes: [
                        'fileUid',
                        'fileTable',
                        'src'
                    ]
                },
                view: (modelElement, { writer }) => {
                    const imageElement = createImageViewElement(modelElement, writer);
                    const captionElement = getCaptionFromImageModelElement(modelElement);

                    // Get alignment class from model (e.g., 'image-left', 'image-right', 'image-center')
                    const modelClass = modelElement.getAttribute('class') || '';

                    // If caption element exists (even if empty), wrap in figure with figcaption slot
                    if (captionElement) {
                        // Combine 'image' base class with alignment class
                        const figureClasses = ['image', modelClass].filter(c => c.trim()).join(' ');
                        const figure = writer.createContainerElement('figure', {
                            class: figureClasses
                        });

                        // Insert image into figure
                        writer.insert(writer.createPositionAt(figure, 0), imageElement);

                        // Create caption slot (renders all children including typo3imageCaption)
                        const captionSlot = writer.createSlot();
                        writer.insert(writer.createPositionAt(figure, 'end'), captionSlot);

                        return figure;
                    }

                    // No caption: wrap in figure with alignment class if present
                    // This ensures alignment classes are preserved even without caption
                    if (modelClass.trim()) {
                        const figureClasses = ['image', modelClass].filter(c => c.trim()).join(' ');
                        const figure = writer.createContainerElement('figure', {
                            class: figureClasses
                        });
                        writer.insert(writer.createPositionAt(figure, 0), imageElement);
                        return figure;
                    }

                    // No caption and no alignment: return plain image element
                    return imageElement;
                },
            });

        // Data downcast for caption element itself
        editor.conversion
            .for('dataDowncast')
            .elementToElement({
                model: 'typo3imageCaption',
                view: (modelElement, { writer }) => {
                    return writer.createContainerElement('figcaption');
                }
            });

        // Helper function to create view element for typo3imageInline
        // Similar to createImageViewElement but with class on img and no caption support
        const createInlineImageViewElement = (modelElement, writer) => {
            const attributes = {
                'src': modelElement.getAttribute('src'),
                'data-htmlarea-file-uid': modelElement.getAttribute('fileUid'),
                'data-htmlarea-file-table': modelElement.getAttribute('fileTable'),
                'width': modelElement.getAttribute('width'),
                'height': modelElement.getAttribute('height'),
                // For inline images, class goes directly on the img element
                'class': modelElement.getAttribute('class') || 'image-inline',
                'title': modelElement.getAttribute('title') || '',
                'alt': modelElement.getAttribute('alt') || '',
            };

            if (modelElement.getAttribute('titleOverride') || false) {
                attributes['data-title-override'] = true;
            }

            if (modelElement.getAttribute('altOverride') || false) {
                attributes['data-alt-override'] = true;
            }

            if (modelElement.getAttribute('enableZoom') || false) {
                attributes['data-htmlarea-zoom'] = true;
            }

            if (modelElement.getAttribute('noScale') || false) {
                attributes['data-noscale'] = true;
            }

            const quality = modelElement.getAttribute('quality') || '';
            if (quality) {
                attributes['data-quality'] = quality;
            }

            // Ensure image-inline class is present
            const existingClasses = (attributes['class'] || '').split(/\s+/);
            if (!existingClasses.includes('image-inline')) {
                attributes['class'] = attributes['class']
                    ? attributes['class'] + ' image-inline'
                    : 'image-inline';
            }

            const imgElement = writer.createEmptyElement('img', attributes);

            // Check if model has link attributes and wrap in <a> if present
            const linkHref = modelElement.getAttribute('imageLinkHref');
            if (linkHref && linkHref.trim() !== '' && linkHref.trim() !== '/') {
                const linkAttributes = {
                    href: linkHref
                };

                const linkTarget = modelElement.getAttribute('imageLinkTarget');
                if (linkTarget && linkTarget.trim() !== '') {
                    linkAttributes.target = linkTarget;
                }

                const linkTitle = modelElement.getAttribute('imageLinkTitle');
                if (linkTitle && linkTitle.trim() !== '') {
                    linkAttributes.title = linkTitle;
                }

                const linkClass = modelElement.getAttribute('imageLinkClass');
                if (linkClass && linkClass.trim() !== '') {
                    linkAttributes.class = linkClass;
                }

                const linkParams = modelElement.getAttribute('imageLinkParams');
                if (linkParams && linkParams.trim() !== '') {
                    linkAttributes['data-link-params'] = linkParams;
                }

                return writer.createContainerElement('a', linkAttributes, imgElement);
            }

            return imgElement;
        };

        // Editing downcast for inline images - creates inline span widget
        editor.conversion
            .for('editingDowncast')
            .elementToElement({
                model: 'typo3imageInline',
                view: (modelElement, { writer }) => {
                    const imageElement = createInlineImageViewElement(modelElement, writer);

                    // Wrap in span for inline widget (not figure)
                    const wrapper = writer.createContainerElement('span', {
                        class: 'ck-widget ck-widget_inline-image'
                    });

                    writer.insert(writer.createPositionAt(wrapper, 0), imageElement);

                    // Add visual indicators for link and zoom (same as block images)
                    const linkHref = modelElement.getAttribute('imageLinkHref');
                    const hasLink = linkHref && linkHref.trim() !== '' && linkHref.trim() !== '/';
                    const hasZoom = modelElement.getAttribute('enableZoom');

                    if (hasLink || hasZoom) {
                        const indicatorContainer = writer.createContainerElement('span', {
                            class: 'ck-image-indicators'
                        });

                        if (hasZoom) {
                            const zoomIndicator = writer.createContainerElement('span', {
                                class: 'ck-image-indicator ck-image-indicator--zoom',
                                title: 'Click to enlarge'
                            });
                            writer.insert(writer.createPositionAt(indicatorContainer, 'end'), zoomIndicator);
                        }

                        if (hasLink) {
                            const linkIndicator = writer.createContainerElement('span', {
                                class: 'ck-image-indicator ck-image-indicator--link',
                                title: linkHref
                            });
                            writer.insert(writer.createPositionAt(indicatorContainer, 'end'), linkIndicator);
                        }

                        writer.insert(writer.createPositionAt(wrapper, 'end'), indicatorContainer);
                    }

                    return toWidget(wrapper, writer, {
                        label: 'inline image widget',
                        hasSelectionHandle: false
                    });
                }
            });

        // Data downcast for inline images - outputs plain img (no wrapper)
        // Priority highest to ensure it runs before typo3image downcast
        editor.conversion
            .for('dataDowncast')
            .elementToElement({
                model: 'typo3imageInline',
                view: (modelElement, { writer }) => {
                    return createInlineImageViewElement(modelElement, writer);
                },
                converterPriority: 'highest'
            });

        // Register the attribute converter to make changes to the `class` attribute visible in the view
        editor.conversion.for('downcast').attributeToAttribute({
            model: {
                name: 'typo3image',
                key: 'class'
            },
            view: 'class'
        });

        // Register class attribute converter for inline images
        editor.conversion.for('downcast').attributeToAttribute({
            model: {
                name: 'typo3imageInline',
                key: 'class'
            },
            view: 'class'
        });


        // Loop over existing images
        // SECURITY: Removed debug logging to prevent information disclosure
        editor.model.change(writer => {
            const range = writer.createRangeIn(editor.model.document.getRoot());
            // Process existing images if needed
            for (const value of range.getWalker({ ignoreElementEnd: true })) {
                // Image processing logic would go here
            }
        });


        editor.ui.componentFactory.add('insertimage', () => {
            const button = new ButtonView();

            // Initialize with English label, will be updated with translation
            button.set({
                label: 'Insert image',
                icon: '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M6.91 10.54c.26-.23.64-.21.88.03l3.36 3.14 2.23-2.06a.64.64 0 0 1 .87 0l2.52 2.97V4.5H3.2v10.12l3.71-4.08zm10.27-7.51c.6 0 1.09.47 1.09 1.05v11.84c0 .59-.49 1.06-1.09 1.06H2.79c-.6 0-1.09-.47-1.09-1.06V4.08c0-.58.49-1.05 1.1-1.05h14.38zm-5.22 5.56a1.96 1.96 0 1 1 3.4-1.96 1.96 1.96 0 0 1-3.4 1.96z"/></svg>',
                tooltip: true,
                withText: false,
            });

            // Fetch and apply translated label
            getTranslations().then(translations => {
                if (translations.insertImage) {
                    button.set({
                        label: translations.insertImage
                    });
                }
            });

            button.on('execute', () => {
                const selectedElement = editor.model.document.selection.getSelectedElement();

                if (selectedElement && selectedElement.name === 'typo3image') {
                    edit(
                        {
                            uid: selectedElement.getAttribute('fileUid'),
                            table: selectedElement.getAttribute('fileTable'),
                        },
                        editor,
                        {
                            width: selectedElement.getAttribute('width'),
                            height: selectedElement.getAttribute('height'),
                            class: selectedElement.getAttribute('class'),
                            alt: selectedElement.getAttribute('alt'),
                            title: selectedElement.getAttribute('title'),
                            'data-htmlarea-zoom': selectedElement.getAttribute('enableZoom'),
                            'data-noscale': selectedElement.getAttribute('noScale'),
                            'data-quality': selectedElement.getAttribute('quality'),
                            'data-title-override': selectedElement.getAttribute('titleOverride'),
                            'data-alt-override': selectedElement.getAttribute('altOverride'),
                            linkHref: selectedElement.getAttribute('imageLinkHref'),
                            linkTarget: selectedElement.getAttribute('imageLinkTarget'),
                            linkTitle: selectedElement.getAttribute('imageLinkTitle'),
                            linkClass: selectedElement.getAttribute('imageLinkClass'),
                            linkParams: selectedElement.getAttribute('imageLinkParams'),
                        }
                    );
                } else {
                    selectImage(editor).then(selectedImage => {
                        edit(selectedImage, editor, {});
                    });
                }
            });

            return button;
        });

        // Register edit button for balloon toolbar
        editor.ui.componentFactory.add('editTypo3Image', () => {
            const button = new ButtonView();

            button.set({
                label: 'Edit image',
                icon: '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M6.91 10.54c.26-.23.64-.21.88.03l3.36 3.14 2.23-2.06a.64.64 0 0 1 .87 0l2.52 2.97V4.5H3.2v10.12l3.71-4.08zm10.27-7.51c.6 0 1.09.47 1.09 1.05v11.84c0 .59-.49 1.06-1.09 1.06H2.79c-.6 0-1.09-.47-1.09-1.06V4.08c0-.58.49-1.05 1.1-1.05h14.38zm-5.22 5.56a1.96 1.96 0 1 1 3.4-1.96 1.96 1.96 0 0 1-3.4 1.96z"/></svg>',
                tooltip: true,
                withText: false,
            });

            // Fetch and apply translated label
            getTranslations().then(translations => {
                if (translations.editImage) {
                    button.set({
                        label: translations.editImage
                    });
                }
            });

            button.on('execute', () => {
                const selectedElement = editor.model.document.selection.getSelectedElement();

                // Handle both block (typo3image) and inline (typo3imageInline) images
                if (isTypo3ImageElement(selectedElement)) {
                    // Get caption text from caption element (if exists) - only for block images
                    let captionText = '';
                    if (selectedElement.name === 'typo3image') {
                        const captionElement = getCaptionFromImageModelElement(selectedElement);
                        if (captionElement) {
                            for (const child of captionElement.getChildren()) {
                                if (child.is('$text')) {
                                    captionText += child.data;
                                }
                            }
                        }
                    }

                    // Check if inline image is inside an external link (link wrapping the image from outside)
                    // This happens when user creates a link around text that includes an inline image
                    let isInsideExternalLink = false;
                    if (selectedElement.name === 'typo3imageInline') {
                        // The image itself doesn't have imageLinkHref, so check if we're inside a link
                        const imageLinkHref = selectedElement.getAttribute('imageLinkHref');
                        if (!imageLinkHref || imageLinkHref.trim() === '') {
                            // No link on the image itself - check if there's an external link wrapping us
                            // Use the view selection which gives us the widget element
                            const viewSelection = editor.editing.view.document.selection;
                            const viewElement = viewSelection.getSelectedElement();
                            if (viewElement) {
                                // The viewElement is the widget span, check its parent for a link
                                const parent = viewElement.parent;
                                if (parent && parent.is('attributeElement') && parent.name === 'a') {
                                    // Image is inside an external link in the view
                                    isInsideExternalLink = true;
                                }
                            }
                        }
                    }

                    edit(
                        {
                            uid: selectedElement.getAttribute('fileUid'),
                            table: selectedElement.getAttribute('fileTable'),
                        },
                        editor,
                        {
                            width: selectedElement.getAttribute('width'),
                            height: selectedElement.getAttribute('height'),
                            class: selectedElement.getAttribute('class'),
                            alt: selectedElement.getAttribute('alt'),
                            title: selectedElement.getAttribute('title'),
                            caption: captionText,
                            'data-htmlarea-zoom': selectedElement.getAttribute('enableZoom'),
                            'data-noscale': selectedElement.getAttribute('noScale'),
                            'data-quality': selectedElement.getAttribute('quality'),
                            'data-title-override': selectedElement.getAttribute('titleOverride'),
                            'data-alt-override': selectedElement.getAttribute('altOverride'),
                            linkHref: selectedElement.getAttribute('imageLinkHref'),
                            linkTarget: selectedElement.getAttribute('imageLinkTarget'),
                            linkTitle: selectedElement.getAttribute('imageLinkTitle'),
                            linkClass: selectedElement.getAttribute('imageLinkClass'),
                            linkParams: selectedElement.getAttribute('imageLinkParams'),
                            // Pass flag to indicate if this is an inline image (for hiding caption field)
                            isInlineImage: selectedElement.name === 'typo3imageInline',
                            // Pass flag to indicate if image is inside an external link (for disabling link options)
                            isInsideExternalLink: isInsideExternalLink,
                        }
                    );
                }
            });

            return button;
        });

        // Register toggle caption button for balloon toolbar
        editor.ui.componentFactory.add('toggleImageCaption', () => {
            const command = editor.commands.get('toggleImageCaption');
            const button = new ButtonView();

            button.set({
                label: 'Toggle caption',
                icon: '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M2 4h16v3H2zM2 14h10v2H2z"/></svg>',
                tooltip: true,
                isToggleable: true
            });

            // Bind button state to command
            button.bind('isOn').to(command, 'value');
            button.bind('isEnabled').to(command, 'isEnabled');

            button.on('execute', () => {
                editor.execute('toggleImageCaption');
                editor.editing.view.focus();
            });

            return button;
        });

        // Register toggle image type button for balloon toolbar (block ↔ inline)
        editor.ui.componentFactory.add('toggleImageType', () => {
            const command = editor.commands.get('toggleImageType');
            const button = new ButtonView();

            button.set({
                label: 'Toggle inline/block',
                // Icon: shows inline text with image
                icon: '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M2 3h16v2H2zm0 4h4v4H2zm6 0h10v2H8zm0 4h10v2H8zm-6 4h16v2H2z"/></svg>',
                tooltip: true,
                isToggleable: true
            });

            // Bind button state to command
            // isOn is true when the image is inline
            button.bind('isOn').to(command, 'value', value => value === 'inline');
            button.bind('isEnabled').to(command, 'isEnabled');

            button.on('execute', () => {
                editor.execute('toggleImageType');
                editor.editing.view.focus();
            });

            return button;
        });

        // Make image selectable with a single click (both block and inline images)
        editor.listenTo(editor.editing.view.document, 'click', (event, data) => {
            // Find the widget wrapper - traverse UP if we clicked the inner img/link
            let targetElement = data.target;

            // If clicked on img or link inside wrapper, find the parent wrapper (span or figure)
            if (targetElement.name === 'img' || targetElement.name === 'a') {
                const parent = targetElement.parent;
                if (parent && (parent.name === 'span' || parent.name === 'figure') && parent.hasClass('ck-widget')) {
                    targetElement = parent;
                }
                // Handle img inside link inside wrapper: img -> a -> span/figure
                else if (targetElement.name === 'img' && parent && parent.name === 'a') {
                    const grandparent = parent.parent;
                    if (grandparent && (grandparent.name === 'span' || grandparent.name === 'figure') && grandparent.hasClass('ck-widget')) {
                        targetElement = grandparent;
                    }
                }
            }

            const modelElement = editor.editing.mapper.toModelElement(targetElement);
            // Handle both block (typo3image) and inline (typo3imageInline) images
            if (isTypo3ImageElement(modelElement)) {
                // Prevent default link behavior in editor
                data.preventDefault();
                data.stopPropagation();

                // Select the clicked element
                editor.model.change(writer => {
                    writer.setSelection(modelElement, 'on');
                });
            }
        }, { priority: 'highest' })

        // Define image style options with SVG icons
        const styleDefinitions = {
            'image-left': {
                title: 'Align left',
                icon: '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M2 3h16v2H2zm0 12h16v2H2zm0-6h7v2H2zm9 0h7v2h-7z"/></svg>',
                className: 'image-left'
            },
            'image-center': {
                title: 'Align center',
                icon: '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M2 3h16v2H2zm0 12h16v2H2zm3-6h10v2H5z"/></svg>',
                className: 'image-center'
            },
            'image-right': {
                title: 'Align right',
                icon: '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M2 3h16v2H2zm0 12h16v2H2zm11-6h7v2h-7zm-9 0h7v2H4z"/></svg>',
                className: 'image-right'
            },
            'image-inline': {
                title: 'Inline',
                icon: '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M2 3h16v2H2zm0 4h4v4H2zm6 0h10v2H8zm0 2h10v2H8zm-6 4h16v2H2zm0 4h16v2H2z"/></svg>',
                className: 'image-inline'
            },
            'image-block': {
                title: 'Block',
                icon: '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M2 3h16v2H2zm0 12h16v2H2zm0-8h16v4H2z"/></svg>',
                className: 'image-block'
            }
        };

        // Register SetImageStyleCommand
        editor.commands.add('setImageStyle', new SetImageStyleCommand(editor, styleDefinitions));

        // Register UI buttons for each style
        for (const [styleName, definition] of Object.entries(styleDefinitions)) {
            editor.ui.componentFactory.add(`imageStyle:${styleName}`, () => {
                const command = editor.commands.get('setImageStyle');
                const buttonView = new ButtonView();

                buttonView.set({
                    label: definition.title,
                    icon: definition.icon,
                    tooltip: true
                });

                buttonView.bind('isEnabled').to(command, 'isEnabled');
                buttonView.bind('isOn').to(command, 'value', value => value === styleName);

                buttonView.on('execute', () => {
                    editor.execute('setImageStyle', { value: styleName });
                    editor.editing.view.focus();
                });

                return buttonView;
            });
        }

        // NOTE: Balloon toolbar is implemented using WidgetToolbarRepository (see lines 19, 874-875)
        // WidgetResize is NOT available in TYPO3's CKEditor 5 build - visual resize handles not supported
        // Resize functionality is available via context toolbar buttons instead

        // Keeping the commands registered for potential future use
        editor.commands.add('resizeImage', new ResizeImageCommand(editor));

        // Register ToggleCaptionCommand for inline caption editing
        editor.commands.add('toggleImageCaption', new ToggleCaptionCommand(editor));

        // Register ToggleImageTypeCommand for switching between block and inline images
        editor.commands.add('toggleImageType', new ToggleImageTypeCommand(editor));

        editor.listenTo(editor.editing.view.document, 'dblclick:typo3image', (event, data) => {
            // Find the widget wrapper - traverse UP if we clicked the inner img/link
            let targetElement = data.target;

            // If we clicked on text inside figcaption, traverse up to find the widget
            let checkElement = targetElement;
            while (checkElement && checkElement.name !== 'figcaption') {
                checkElement = checkElement.parent;
            }

            if (checkElement && checkElement.name === 'figcaption') {
                // We're inside figcaption, find the parent figure widget
                const parent = checkElement.parent;
                if (parent && parent.name === 'figure' && parent.hasClass('ck-widget')) {
                    targetElement = parent;
                }
            }
            // If clicked on img, link, or figcaption inside wrapper, find the parent wrapper (span or figure)
            else if (targetElement.name === 'img' || targetElement.name === 'a' || targetElement.name === 'figcaption') {
                const parent = targetElement.parent;
                if (parent && (parent.name === 'span' || parent.name === 'figure') && parent.hasClass('ck-widget')) {
                    targetElement = parent;
                }
                // Handle img inside link inside wrapper: img -> a -> span/figure
                else if (targetElement.name === 'img' && parent && parent.name === 'a') {
                    const grandparent = parent.parent;
                    if (grandparent && (grandparent.name === 'span' || grandparent.name === 'figure') && grandparent.hasClass('ck-widget')) {
                        targetElement = grandparent;
                    }
                }
            }

            // If we still don't have a widget, traverse up from the original target
            // to find any ck-widget ancestor (handles img inside CKEditor link wrapper)
            if (!targetElement.hasClass || !targetElement.hasClass('ck-widget')) {
                let searchElement = data.target;
                while (searchElement) {
                    if (searchElement.hasClass && searchElement.hasClass('ck-widget') &&
                        (searchElement.name === 'span' || searchElement.name === 'figure')) {
                        targetElement = searchElement;
                        break;
                    }
                    // Also check if we're on an img directly inside a widget span
                    if (searchElement.name === 'img') {
                        const widgetParent = searchElement.parent;
                        if (widgetParent && widgetParent.hasClass && widgetParent.hasClass('ck-widget')) {
                            targetElement = widgetParent;
                            break;
                        }
                    }
                    searchElement = searchElement.parent;
                }
            }

            const modelElement = editor.editing.mapper.toModelElement(targetElement);
            // Handle both block (typo3image) and inline (typo3imageInline) images
            if (isTypo3ImageElement(modelElement)) {
                // Select the clicked element
                editor.model.change(writer => {
                    writer.setSelection(modelElement, 'on');
                });

                // Get caption text from caption element (if exists) - only for block images
                let captionText = '';
                if (modelElement.name === 'typo3image') {
                    const captionElement = getCaptionFromImageModelElement(modelElement);
                    if (captionElement) {
                        for (const child of captionElement.getChildren()) {
                            if (child.is('$text')) {
                                captionText += child.data;
                            }
                        }
                    }
                }

                // Check if inline image is inside an external link (link wrapping from outside)
                let isInsideExternalLink = false;
                if (modelElement.name === 'typo3imageInline') {
                    const imageLinkHref = modelElement.getAttribute('imageLinkHref');
                    if (!imageLinkHref || imageLinkHref.trim() === '') {
                        // No link on the image - check if targetElement's parent is a link
                        const parent = targetElement.parent;
                        if (parent && parent.is('attributeElement') && parent.name === 'a') {
                            isInsideExternalLink = true;
                        }
                    }
                }

                edit(
                    {
                        uid: modelElement.getAttribute('fileUid'),
                        table: modelElement.getAttribute('fileTable'),
                    },
                    editor,
                    {
                        width: modelElement.getAttribute('width'),
                        height: modelElement.getAttribute('height'),
                        class: modelElement.getAttribute('class'),
                        alt: modelElement.getAttribute('alt'),
                        title: modelElement.getAttribute('title'),
                        'data-htmlarea-zoom': modelElement.getAttribute('enableZoom'),
                        'data-noscale': modelElement.getAttribute('noScale'),
                        'data-quality': modelElement.getAttribute('quality'),
                        'data-title-override': modelElement.getAttribute('titleOverride'),
                        'data-alt-override': modelElement.getAttribute('altOverride'),
                        linkHref: modelElement.getAttribute('imageLinkHref'),
                        linkTarget: modelElement.getAttribute('imageLinkTarget'),
                        linkTitle: modelElement.getAttribute('imageLinkTitle'),
                        linkClass: modelElement.getAttribute('imageLinkClass'),
                        linkParams: modelElement.getAttribute('imageLinkParams'),
                        caption: captionText,
                        // Pass flag to indicate if this is an inline image (for hiding caption field)
                        isInlineImage: modelElement.name === 'typo3imageInline',
                        // Pass flag to indicate if image is inside an external link (for disabling link options)
                        isInsideExternalLink: isInsideExternalLink,
                    }
                );
            }
        });

        // Disable external link command when typo3image is selected
        // Links should be added via the image dialog to prevent conflicts
        // between our link handling and TYPO3's Typo3LinkEditing plugin
        const linkCommand = editor.commands.get('link');
        if (linkCommand) {
            // Override the link command's refresh to disable it for typo3image
            const originalRefresh = linkCommand.refresh.bind(linkCommand);
            linkCommand.refresh = function() {
                originalRefresh();

                // Disable link command when typo3image is selected
                const selection = editor.model.document.selection;
                const selectedElement = selection.getSelectedElement();

                if (selectedElement && selectedElement.name === 'typo3image') {
                    this.isEnabled = false;
                }
            };
        }
    }
}
