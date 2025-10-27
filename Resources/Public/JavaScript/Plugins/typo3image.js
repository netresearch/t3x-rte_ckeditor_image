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
import { Plugin } from '@ckeditor/ckeditor5-core';
import { ButtonView } from '@ckeditor/ckeditor5-ui';
import { DomEventObserver } from '@ckeditor/ckeditor5-engine';
import { toWidget } from '@ckeditor/ckeditor5-widget';
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
        return;
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

    $.each(fields, function () {
        var $row = $('<div class="row">').appendTo(d.$el);

        $rows.push($row);
        $.each(this, function (key, config) {
            // Use full width for title and alt fields, otherwise use col-sm-4
            var colClass = (key === 'title' || key === 'alt') ? 'col-xs-12' : 'col-xs-12 col-sm-4';
            var $group = $('<div class="form-group">').appendTo($('<div class="' + colClass + '">').appendTo($row));
            var id = 'rteckeditorimage-' + key;
            $('<label class="form-label" for="' + id + '">' + config.label + '</label>').appendTo($group);

            var $el;
            if (config.type === 'select') {
                $el = $('<select id="' + id + '" name="' + key + '" class="form-select"></select>');
            } else {
                $el = $('<input type="' + config.type + '" id ="' + id + '" name="' + key + '" class="form-control">');
            }

            var placeholder = (config.type === 'text' ? (img[key] || '') : img.processed[key]) + '';
            var value = ((attributes[key] || '') + '').trim();

            // For number inputs (width/height), if no value exists (new image), use placeholder as initial value
            if (config.type === 'number' && value === '') {
                value = placeholder;
            }

            if (config.type !== 'select') {
                $el.attr('placeholder', placeholder);
                $el.val(value);
            }

            if (config.type === 'text') {
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
                    cbox.attr('title', 'No default ' + key + ' available in file metadata. Cannot override empty value.');
                    cboxLabel.css('cursor', 'not-allowed').attr('title', 'No default ' + key + ' available in file metadata. Cannot override empty value.');
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
                // Calculate aspect ratio from ORIGINAL image dimensions (not capped)
                // img.width/img.height are now the original dimensions from backend
                // img.processed.width/height are the suggested display dimensions (respecting aspect ratio)
                var aspectRatio = img.width / img.height;
                var opposite = 1;

                // Use TSConfig max values for display dimensions
                // These are DISPLAY dimensions, not processing dimensions
                // The backend will cap processing at original image size to prevent upscaling
                var max = key === 'width' ? maxConfigWidth : maxConfigHeight;

                var min = 1; // Allow minimum of 1px for all images
                $el.attr('max', max);
                $el.attr('min', min);

                var constrainDimensions = function (currentMin, delta) {
                    value = parseInt($el.val().replace(/[^0-9]/g, '') || (isSvg ? '' : max));
                    if (delta) {
                        value += delta;
                    }
                    value = Math.max(currentMin, Math.min(value, max));

                    // For SVG images, allow free-form sizing without ratio constraint
                    if (!isSvg && aspectRatio && aspectRatio > 0) {
                        var $opposite = elements[key === 'width' ? 'height' : 'width'],
                            oppositeMax = parseInt($opposite.attr('max'));

                        // Calculate opposite dimension based on which field is being edited
                        // If editing width: height = width / aspectRatio
                        // If editing height: width = height * aspectRatio
                        var oppositeValue = key === 'width'
                            ? Math.ceil(value / aspectRatio)  // Calculate height from width
                            : Math.ceil(value * aspectRatio); // Calculate width from height

                        $opposite.val(oppositeValue);
                    }
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
                    { value: 'none', label: 'No Scaling', multiplier: 1.0, color: '#6c757d', marker: '●' },
                    { value: 'low', label: 'Low (0.9x)', multiplier: 0.9, color: '#dc3545', marker: '●' },
                    { value: 'standard', label: 'Standard (1.0x)', multiplier: 1.0, color: '#ffc107', marker: '●' },
                    { value: 'retina', label: 'Retina (2.0x)', multiplier: 2.0, color: '#28a745', marker: '●' },
                    { value: 'ultra', label: 'Ultra (3.0x)', multiplier: 3.0, color: '#17a2b8', marker: '●' },
                    { value: 'print', label: 'Print (6.0x)', multiplier: 6.0, color: '#007bff', marker: '●' }
                ];

                $.each(qualityOptions, function(i, option) {
                    var $option = $('<option>')
                        .val(option.value)
                        .text(option.marker + ' ' + option.label)
                        .data('multiplier', option.multiplier)
                        .data('color', option.color)
                        .css('color', option.color);
                    $option.appendTo($el);
                });

                // Default to retina for normal images, print for SVG
                var defaultQuality = isSvg ? 'print' : (attributes['data-quality'] || 'retina');
                $el.val(defaultQuality);

                // Disable for SVG
                if (isSvg) {
                    $el.prop('disabled', true);
                }
            }

            $group.append($el);
            elements[key] = $el;
        });
    });

    // Create quality indicator container
    var $qualityIndicator = $('<div class="image-quality-indicator" style="margin: 12px 0; font-size: 13px; line-height: 1.6;">');
    $qualityIndicator.insertAfter($rows[0]);

    var $checkboxTitle = d.$el.find('#checkbox-title'),
        $checkboxAlt = d.$el.find('#checkbox-alt'),
        $inputWidth = d.$el.find('#rteckeditorimage-width'),
        $inputHeight = d.$el.find('#rteckeditorimage-height'),
        $qualityDropdown = d.$el.find('#rteckeditorimage-quality'),
        $zoom = $('<input id="checkbox-zoom" type="checkbox">'),
        cssClass = attributes.class || '',
        $inputCssClass = $('<input id="input-cssclass" type="text" class="form-control">').val(cssClass),
        $customRow = $('<div class="row">').insertAfter($rows[2]),
        $customRowCol1 = $('<div class="col-xs-12 col-sm-6">'),
        $customRowCol2 = $('<div class="col-xs-12 col-sm-6">');

    // Create zoom checkbox following TYPO3 v13 backend conventions
    var $zoomContainer = $('<div class="form-group">').prependTo($customRowCol1);
    var $zoomTitle = $('<div class="form-label">').text('Click to Enlarge').appendTo($zoomContainer);
    var $zoomFormCheck = $('<div class="form-check form-check-type-toggle">').appendTo($zoomContainer);
    $zoom.addClass('form-check-input').appendTo($zoomFormCheck);
    var $zoomLabel = $('<label class="form-check-label" for="checkbox-zoom">').text('Enabled').appendTo($zoomFormCheck);
    var $helpIcon = $('<span style="margin-left: 8px; cursor: help; color: #888;" title="Enables click-to-enlarge/lightbox functionality. Default popup configuration is provided automatically. See documentation for custom lightbox library integration.">ℹ️</span>');
    $zoomTitle.append($helpIcon);

    $inputCssClass
        .prependTo(
            $('<div class="form-group">').prependTo($customRowCol2)
        )
        .before($('<label class="form-label" for="input-cssclass">').text(img.lang.cssClass));

    $customRow.append($customRowCol1, $customRowCol2);

    // Check for existing noresize attribute
    if (attributes['data-htmlarea-noresize']) {
        $checkboxNoResize.prop('checked', true);
        $inputWidth.prop('disabled', true);
        $inputHeight.prop('disabled', true);
    }

    // Support new `zoom` and legacy `clickenlarge` attributes
    if (attributes['data-htmlarea-zoom'] || attributes['data-htmlarea-clickenlarge']) {
        $zoom.prop('checked', true);
    }

    // Quality indicator functions
    function getQualityLevel(ratio) {
        if (ratio < 0.9) {
            return { level: 'low', label: 'Low', color: '#dc3545', tooltip: 'Low quality (' + ratio.toFixed(1) + 'x) - Image may appear blurry' };
        } else if (ratio < 1.5) {
            return { level: 'standard', label: 'Standard', color: '#fd7e14', tooltip: 'Standard quality (' + ratio.toFixed(1) + 'x) - Sharp on basic displays' };
        } else if (ratio < 3.0) {
            return { level: 'retina', label: 'Retina', color: '#28a745', tooltip: 'Retina quality (' + ratio.toFixed(1) + 'x) - Optimal for modern displays' };
        } else if (ratio < 6.0) {
            return { level: 'ultra', label: 'Ultra', color: '#6f42c1', tooltip: 'Ultra quality (' + ratio.toFixed(1) + 'x) - For ultra-high DPI or small print' };
        } else if (ratio <= 10.0) {
            return { level: 'print', label: 'Print', color: '#007bff', tooltip: 'Print quality (' + ratio.toFixed(1) + 'x) - Suitable for high-quality printing (300 DPI)' };
        } else {
            return { level: 'excessive', label: 'Excessive', color: '#6c757d', tooltip: 'Excessive resolution (' + ratio.toFixed(1) + 'x) - Unnecessarily high' };
        }
    }

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
        var displayWidth = parseInt($inputWidth.val()) || img.width;
        var displayHeight = parseInt($inputHeight.val()) || img.height;
        renderQualityIndicator(displayWidth, displayHeight);
    }

    // Update quality indicator when dimensions or quality selection change
    // Use debounced version for input events to improve performance
    var debouncedUpdateQualityIndicator = debounce(updateQualityIndicator, 250);
    $inputWidth.on('input', debouncedUpdateQualityIndicator);
    $inputHeight.on('input', debouncedUpdateQualityIndicator);
    $inputWidth.on('change', updateQualityIndicator);
    $inputHeight.on('change', updateQualityIndicator);
    $qualityDropdown.on('change', updateQualityIndicator); // No debounce for dropdown

    // Initial update
    updateQualityIndicator();

    d.get = function () {
        $.each(fields, function () {
            $.each(this, function (key) {
                var value = elements[key].val();

                if (typeof value !== 'undefined') {
                    attributes[key] = value;
                }
            });
        });

        // When saving, the zoom property is saved as the new `zoom` attribute
        if ($zoom.prop('checked')) {
            attributes['data-htmlarea-zoom'] = true;
        } else if (attributes['data-htmlarea-zoom'] || attributes['data-htmlarea-clickenlarge']) {
            delete attributes['data-htmlarea-zoom'];
            delete attributes['data-htmlarea-clickenlarge'];
        }

        // Save quality selection (except for SVG which is always 'print')
        if (img.extension !== 'svg') {
            var selectedQuality = $qualityDropdown.val();
            attributes['data-quality'] = selectedQuality;

            // Set data-noscale attribute when "No Scaling" is selected
            if (selectedQuality === 'none') {
                attributes['data-noscale'] = true;
            } else {
                // Remove data-noscale if another quality is selected
                delete attributes['data-noscale'];
            }
        }

        // Set and escape cssClass value
        attributes.class = $inputCssClass.val() ? $('<div/>').html($inputCssClass.val().trim()).text() : '';

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
 * @return {$.Deferred}
 */
function askImageAttributes(editor, img, attributes, table) {
    var deferred = $.Deferred();
    var dialog = getImageDialog(editor, img, $.extend({}, img.processed, attributes));

    const modal = Modal.advanced({
        title: 'Image Properties',
        content: dialog.$el,
        buttons: [
            {
                text: 'Cancel',
                btnClass: 'btn-default',
                icon: 'actions-close',
                trigger: function () {
                    modal.hideModal();
                    deferred.reject();
                }
            },
            {
                text: 'Save',
                btnClass: 'btn-primary',
                icon: 'actions-document-save',
                trigger: function () {

                    var dialogInfo = dialog.get(),
                        filteredAttr = {},
                        allowedAttributes = [
                            '!src', 'alt', 'title', 'class', 'rel', 'width', 'height', 'data-htmlarea-zoom', 'data-noscale', 'data-quality', 'data-title-override', 'data-alt-override'
                        ],
                        attributesNew = $.extend({}, img, dialogInfo);

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

                            $.extend(filteredAttr, {
                                src: urlToRelative(getImg.url, getImg.storageDriver),
                                width: getImg.processed.width || getImg.width,
                                height: getImg.processed.height || getImg.height,
                                fileUid: img.uid,
                                fileTable: table
                            });
                            modal.hideModal('hide');
                            deferred.resolve(filteredAttr);
                        });

                }
            }
        ]
    });

    return deferred;
}

/**
 * Get image information
 *
 * @param editor
 * @param table
 * @param uid
 * @param params
 * @return {$.Deferred}
 */
function getImageInfo(editor, table, uid, params) {
    let url = editor.config.get('typo3image').routeUrl + '&action=info&fileId=' + encodeURIComponent(uid) + '&table=' + encodeURIComponent(table) + '&contentsLanguage=en&editorId=123';

    // SECURITY: Encode URL parameters to prevent injection attacks
    if (typeof params.width !== 'undefined' && params.width.length) {
        url += '&P[width]=' + encodeURIComponent(params.width);
    }

    if (typeof params.height !== 'undefined' && params.height.length) {
        url += '&P[height]=' + encodeURIComponent(params.height);
    }

    return $.getJSON(url);
}

function selectImage(editor) {
    const deferred = $.Deferred();
    const bparams = [
        '',
        '',
        '',
        '',
    ];

    // TODO: Use ajaxUrl
    const contentUrl = editor.config.get('typo3image').routeUrl + '&contentsLanguage=en&editorId=123&bparams=' + bparams.join('|');

    const modal = Modal.advanced({
        type: Modal.types.iframe,
        title: 'test',
        content: contentUrl,
        size: Modal.sizes.large,
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
                    deferred.resolve(selectedItem);
                });
            });
        }
    });

    return deferred;
}


function edit(selectedImage, editor, imageAttributes) {
    getImageInfo(editor, selectedImage.table, selectedImage.uid, {})
        .then(function (img) {
            return askImageAttributes(editor, img, imageAttributes, selectedImage.table);
        })
        .then(function (attributes) {

            editor.model.change(writer => {
                // SECURITY: Removed console.log to prevent information disclosure in production

                const newImage = writer.createElement('typo3image', {
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
                    quality: attributes['data-quality'] || '',
                    linkHref: attributes.linkHref || '',
                    linkTarget: attributes.linkTarget || '',
                    linkTitle: attributes.linkTitle || '',
                });

                editor.model.insertObject(newImage);
            });
        });
};


export default class Typo3Image extends Plugin {
    static pluginName = 'Typo3Image';

    static get requires() {
        return ['StyleUtils', 'GeneralHtmlSupport'];
    }

    init() {
        const editor = this.editor;

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
                'quality',
                'width',
                'height',
                'htmlA',
                'linkHref',
                'linkTarget',
                'linkTitle'
            ],
        });

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
                    // Check if image is wrapped in a link element
                    const linkElement = viewElement.parent?.name === 'a' ? viewElement.parent : null;

                    // Extract link attributes if link wrapper exists
                    // IMPORTANT: Don't extract links from zoom wrappers (imageLinkWrap creates these in frontend)
                    // Zoom links are dynamic frontend features and should not be persisted to CKEditor model
                    // Only extract linkHref if the image does NOT have data-htmlarea-zoom attribute
                    const hasZoom = viewElement.getAttribute('data-htmlarea-zoom') || viewElement.getAttribute('data-htmlarea-clickenlarge');
                    const linkHref = (linkElement && !hasZoom) ? (linkElement.getAttribute('href') || '') : '';
                    const linkTarget = (linkElement && !hasZoom) ? (linkElement.getAttribute('target') || '') : '';
                    const linkTitle = (linkElement && !hasZoom) ? (linkElement.getAttribute('title') || '') : '';

                    return writer.createElement('typo3image', {
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
                        quality: viewElement.getAttribute('data-quality') || '',
                        linkHref: linkHref,
                        linkTarget: linkTarget,
                        linkTitle: linkTitle,
                    });
                },
                converterPriority: 'high'
            });

        // Helper function to create the view structure for typo3image
        function createImageViewElement(modelElement, writer, options = {}) {
            const attributes = {
                'src': modelElement.getAttribute('src'),
                'data-htmlarea-file-uid': modelElement.getAttribute('fileUid'),
                'data-htmlarea-file-table': modelElement.getAttribute('fileTable'),
                'width': modelElement.getAttribute('width'),
                'height': modelElement.getAttribute('height'),
                'class': modelElement.getAttribute('class') || '',
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

            const quality = modelElement.getAttribute('quality');
            if (quality && quality.trim() !== '') {
                attributes['data-quality'] = quality;
            }

            const imgElement = writer.createEmptyElement('img', attributes);

            // Check if model has link attributes
            const linkHref = modelElement.getAttribute('linkHref');
            const hasLink = linkHref && linkHref.trim() !== '';

            // For editing view, always wrap in a container for toWidget
            // Link goes INSIDE the widget container, not outside
            if (options.asWidget) {
                let innerElement;

                if (hasLink) {
                    // Create link wrapper for the image
                    const linkAttributes = {
                        href: linkHref
                    };

                    const linkTarget = modelElement.getAttribute('linkTarget');
                    if (linkTarget && linkTarget.trim() !== '') {
                        linkAttributes.target = linkTarget;
                    }

                    const linkTitle = modelElement.getAttribute('linkTitle');
                    if (linkTitle && linkTitle.trim() !== '') {
                        linkAttributes.title = linkTitle;
                    }

                    // Link wraps the img
                    innerElement = writer.createContainerElement('a', linkAttributes, imgElement);
                } else {
                    // No link, just the img
                    innerElement = imgElement;
                }

                // Widget container wraps everything (img or link+img)
                const widgetContainer = writer.createContainerElement('span', {
                    class: 'typo3-image-widget'
                }, innerElement);

                return toWidget(widgetContainer, writer, { label: 'TYPO3 image widget' });
            }

            // For data view, return plain structure
            if (hasLink) {
                const linkAttributes = {
                    href: linkHref
                };

                const linkTarget = modelElement.getAttribute('linkTarget');
                if (linkTarget && linkTarget.trim() !== '') {
                    linkAttributes.target = linkTarget;
                }

                const linkTitle = modelElement.getAttribute('linkTitle');
                if (linkTitle && linkTitle.trim() !== '') {
                    linkAttributes.title = linkTitle;
                }

                return writer.createContainerElement('a', linkAttributes, imgElement);
            }

            return imgElement;
        }

        // Data downcast: Used for editor.getData() - returns plain HTML structure
        editor.conversion
            .for('dataDowncast')
            .elementToElement({
                model: {
                    name: 'typo3image',
                    attributes: [
                        'fileUid',
                        'fileTable',
                        'src'
                    ]
                },
                view: (modelElement, { writer }) => {
                    return createImageViewElement(modelElement, writer, { asWidget: false });
                },
            });

        // Editing downcast: Used for editor UI - wraps with toWidget for selectability
        editor.conversion
            .for('editingDowncast')
            .elementToElement({
                model: {
                    name: 'typo3image',
                    attributes: [
                        'fileUid',
                        'fileTable',
                        'src'
                    ]
                },
                view: (modelElement, { writer }) => {
                    return createImageViewElement(modelElement, writer, { asWidget: true });
                },
            });

        // Register the attribute converter to make changes to the `class` attribute visible in the view
        editor.conversion.for('downcast').attributeToAttribute({
            model: {
                name: 'typo3image',
                key: 'class'
            },
            view: 'class'
        })


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

            button.set({
                label: 'Insert image',
                icon: '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M6.91 10.54c.26-.23.64-.21.88.03l3.36 3.14 2.23-2.06a.64.64 0 0 1 .87 0l2.52 2.97V4.5H3.2v10.12l3.71-4.08zm10.27-7.51c.6 0 1.09.47 1.09 1.05v11.84c0 .59-.49 1.06-1.09 1.06H2.79c-.6 0-1.09-.47-1.09-1.06V4.08c0-.58.49-1.05 1.1-1.05h14.38zm-5.22 5.56a1.96 1.96 0 1 1 3.4-1.96 1.96 1.96 0 0 1-3.4 1.96z"/></svg>',
                tooltip: true,
                withText: false,
            });

            button.on('execute', () => {
                // SECURITY: Removed console.log to prevent configuration disclosure
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
                            linkHref: selectedElement.getAttribute('linkHref'),
                            linkTarget: selectedElement.getAttribute('linkTarget'),
                            linkTitle: selectedElement.getAttribute('linkTitle'),
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

        // Make image selectable with a single click
        // With toWidget(), clicks on any part of the widget (img, span, or a wrapper) should work
        editor.listenTo(editor.editing.view.document, 'click', (event, data) => {
            // Try to get model element from click target
            let modelElement = editor.editing.mapper.toModelElement(data.target);

            // If not found, traverse up the view hierarchy to find the widget
            if (!modelElement || modelElement.name !== 'typo3image') {
                let viewElement = data.target.parent;
                while (viewElement) {
                    modelElement = editor.editing.mapper.toModelElement(viewElement);
                    if (modelElement && modelElement.name === 'typo3image') {
                        break;
                    }
                    viewElement = viewElement.parent;
                }
            }

            if (modelElement && modelElement.name === 'typo3image') {
                // Select the clicked element
                editor.model.change(writer => {
                    writer.setSelection(modelElement, 'on');
                });
            }
        })

        editor.listenTo(editor.editing.view.document, 'dblclick:typo3image', (event, data) => {
            // Try to get model element from double-click target
            let modelElement = editor.editing.mapper.toModelElement(data.target);

            // If not found, traverse up the view hierarchy to find the widget
            if (!modelElement || modelElement.name !== 'typo3image') {
                let viewElement = data.target.parent;
                while (viewElement) {
                    modelElement = editor.editing.mapper.toModelElement(viewElement);
                    if (modelElement && modelElement.name === 'typo3image') {
                        break;
                    }
                    viewElement = viewElement.parent;
                }
            }

            if (modelElement && modelElement.name === 'typo3image') {
                // Select the clicked element
                editor.model.change(writer => {
                    writer.setSelection(modelElement, 'on');
                });

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
                        linkHref: modelElement.getAttribute('linkHref'),
                        linkTarget: modelElement.getAttribute('linkTarget'),
                        linkTitle: modelElement.getAttribute('linkTitle'),
                    }
                );
            }
        });
    }
}



//
// TODO:
//                 // Update image when editor loads
//                 if (existingImages.length) {
//                     $.each(existingImages, function (i, curImg) {
//                         var $curImg = $(curImg),
//                             uid = $curImg.attr('data-htmlarea-file-uid'),
//                             table = $curImg.attr('data-htmlarea-file-table'),
//                             routeUrl = editor.config.typo3image.routeUrl,
//                             url = routeUrl
//                                 + (routeUrl.indexOf('?') === -1 ? '?' : '&')
//                                 + 'action=info'
//                                 + '&fileId=' + encodeURIComponent(uid)
//                                 + '&table=' + encodeURIComponent(table);

//                         if (typeof $curImg.attr('width') !== 'undefined' && $curImg.attr('width').length) {
//                             url += '&P[width]=' + $curImg.attr('width');
//                         }

//                         if (typeof $curImg.attr('height') !== 'undefined' && $curImg.attr('height').length) {
//                             url += '&P[height]=' + $curImg.attr('height');
//                         }

//                         $.getJSON(url, function (newImg) {
//                             // RTEs in flexforms might contain dots in their ID, so we need to escape them
//                             var escapedEditorId = editor.element.$.id.replace('.', '\\.');

//                             var realEditor = $('#cke_' + escapedEditorId).find('iframe').contents().find('body'),
//                                 newImgUrl = newImg.processed.url || newImg.url,
//                                 imTag = realEditor.contents().find('img[data-htmlarea-file-uid=' + uid + ']');

//                             // Sets the title attribute if any
//                             if (typeof $curImg.attr('title') !== 'undefined' && $curImg.attr('title').length) {
//                                 imTag.attr('title', $curImg.attr('title'));
//                             }

//                             // Sets the width attribute if any
//                             if (typeof $curImg.attr('width') !== 'undefined' && $curImg.attr('width').length) {
//                                 imTag.attr('width', $curImg.attr('width'));
//                             }

//                             // Sets the height attribute if any
//                             if (typeof $curImg.attr('height') !== 'undefined' && $curImg.attr('height').length) {
//                                 imTag.attr('height', $curImg.attr('height'));
//                             }

//                             // Sets the style attribute if any
//                             if (typeof $curImg.attr('style') !== 'undefined' && $curImg.attr('style').length) {
//                                 imTag.attr('style', $curImg.attr('style'));
//                             }

//                             // Replaces the current html with the updated one
//                             realEditor.html(realEditor.html());

//                             // Replace current url with updated one
//                             if ($curImg.attr('src') && newImgUrl) {
//                                 realEditor.html(realEditor.html().replaceAll($curImg.attr('src'), newImgUrl));
//                             }
//                         });
//                     });
//                 }

//
//             });
//         }
//     });
