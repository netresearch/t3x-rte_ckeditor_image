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

    $.each(fields, function () {
        var $row = $('<div class="row">').appendTo(d.$el);

        $rows.push($row);
        $.each(this, function (key, config) {
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

                $.each(qualityOptions, function(i, option) {
                    var $option = $('<option>')
                        .val(option.value)
                        .text(option.marker + ' ' + option.label)
                        .data('multiplier', option.multiplier)
                        .data('color', option.color)
                        .css('color', option.color);
                    $option.appendTo($el);
                });

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
        });
    });

    // Create quality indicator container (inserted after first row with dimensions)
    var $qualityIndicator = $('<div class="image-quality-indicator" style="margin: 12px 0; font-size: 13px; line-height: 1.6;">');
    $qualityIndicator.insertAfter($rows[0]);

    var $checkboxTitle = d.$el.find('#checkbox-title'),
        $checkboxAlt = d.$el.find('#checkbox-alt'),
        $inputWidth = d.$el.find('#rteckeditorimage-width'),
        $inputHeight = d.$el.find('#rteckeditorimage-height'),
        $qualityDropdown = d.$el.find('#rteckeditorimage-quality'),
        $zoom = $('<input id="checkbox-zoom" type="checkbox">'),
        $noScale = $('<input id="checkbox-noscale" type="checkbox">'),
        cssClass = attributes.class || '',
        $inputCssClass = $('<input id="input-cssclass" type="text" class="form-control">').val(cssClass),
        $customRow = $('<div class="row">').insertAfter($rows[2]),
        $customRowCol1 = $('<div class="col-xs-12 col-sm-6">'),
        $customRowCol2 = $('<div class="col-xs-12 col-sm-6">');

    // Create zoom checkbox following TYPO3 v13 backend conventions
    var $zoomContainer = $('<div class="form-group">').prependTo($customRowCol1);
    var $zoomTitle = $('<div class="form-label">').text(img.lang.clickToEnlarge).appendTo($zoomContainer);
    var $zoomFormCheck = $('<div class="form-check form-check-type-toggle">').appendTo($zoomContainer);
    $zoom.addClass('form-check-input').appendTo($zoomFormCheck);
    var $zoomLabel = $('<label class="form-check-label" for="checkbox-zoom">').text(img.lang.enabled).appendTo($zoomFormCheck);
    var $helpIcon = $('<span style="margin-left: 8px; cursor: help; color: #888;" title="' + img.lang.zoomHelp + '">ℹ️</span>');
    $zoomTitle.append($helpIcon);

    // noScale checkbox is now replaced by quality dropdown "No Scaling" option
    // Keep the $noScale variable for backward compatibility with existing code
    // The checkbox UI is hidden since quality dropdown provides "No Scaling" option

    $inputCssClass
        .prependTo(
            $('<div class="form-group">').prependTo($customRowCol2)
        )
        .before($('<label class="form-label" for="input-cssclass">').text(img.lang.cssClass));

    $customRow.append($customRowCol1, $customRowCol2);

    // Support new `zoom` and legacy `clickenlarge` attributes
    if (attributes['data-htmlarea-zoom'] || attributes['data-htmlarea-clickenlarge']) {
        $zoom.prop('checked', true);
    }

    // Check for existing noScale attribute
    if (attributes['data-noscale']) {
        $noScale.prop('checked', true);
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
        title: img.lang.imageProperties,
        content: dialog.$el,
        buttons: [
            {
                text: img.lang.cancel,
                btnClass: 'btn-default',
                icon: 'actions-close',
                trigger: function () {
                    modal.hideModal();
                    deferred.reject();
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

                            // Preserve user-entered dimensions instead of overwriting with backend suggestions
                            const userWidth = filteredAttr.width;
                            const userHeight = filteredAttr.height;

                            $.extend(filteredAttr, {
                                src: urlToRelative(getImg.url, getImg.storageDriver),
                                width: userWidth || getImg.processed.width || getImg.width,
                                height: userHeight || getImg.processed.height || getImg.height,
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

    const contentUrl = editor.config.get('typo3image').routeUrl + '&bparams=' + bparams.join('|');

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
                const imageAttributes = {
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
                    imageAttributes.linkHref = attributes.linkHref;
                }
                if (attributes.linkTarget && attributes.linkTarget.trim() !== '') {
                    imageAttributes.linkTarget = attributes.linkTarget;
                }
                if (attributes.linkTitle && attributes.linkTitle.trim() !== '') {
                    imageAttributes.linkTitle = attributes.linkTitle;
                }

                const newImage = writer.createElement('typo3image', imageAttributes);

                // Create caption element if caption text exists
                const captionText = (attributes.caption || '').trim();
                if (captionText !== '') {
                    const captionElement = writer.createElement('typo3imageCaption');
                    writer.append(captionElement, newImage);
                    writer.insertText(captionText, captionElement);
                }

                editor.model.insertObject(newImage);
            });
        });
};


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
                const response = await $.getJSON(url);
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

        // Configure contextual balloon toolbar for typo3image widget
        const widgetToolbarRepository = editor.plugins.get('WidgetToolbarRepository');
        widgetToolbarRepository.register('typo3image', {
            ariaLabel: 'Image toolbar',
            items: [
                'editTypo3Image',
                '|',
                'toggleImageCaption',
                '|',
                'imageStyle:image-left',
                'imageStyle:image-center',
                'imageStyle:image-right',
                'imageStyle:image-block'
            ],
            getRelatedElement: selection => {
                // Get the selected element from the view
                const viewElement = selection.getSelectedElement();

                if (!viewElement) {
                    return null;
                }

                // Map view element to model element to check if it's a typo3image
                const modelElement = editor.editing.mapper.toModelElement(viewElement);

                if (modelElement && modelElement.name === 'typo3image') {
                    return viewElement; // Return view element for toolbar positioning
                }

                return null;
            }
        });

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
                'htmlA',
                'linkHref',
                'linkTarget',
                'linkTitle'
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

        // Upcast converter for figure with caption (higher priority)
        // Handles: <figure class="image"><img data-htmlarea-file-uid="..."><figcaption>Caption text</figcaption></figure>
        editor.conversion
            .for('upcast')
            .elementToElement({
                view: {
                    name: 'figure',
                    classes: ['image']
                },
                model: (viewFigure, { writer }) => {
                    // Find img and figcaption elements within figure
                    let imgElement = null;
                    let figcaptionElement = null;

                    for (const child of viewFigure.getChildren()) {
                        if (child.is('element', 'img')) {
                            imgElement = child;
                        } else if (child.is('element', 'figcaption')) {
                            figcaptionElement = child;
                        }
                    }

                    // Figure must contain an img with data-htmlarea-file-uid
                    if (!imgElement || !imgElement.getAttribute('data-htmlarea-file-uid')) {
                        return null;
                    }

                    // Check if image is wrapped in a link element (inside figure)
                    const linkElement = imgElement.parent?.name === 'a' ? imgElement.parent : null;

                    // Extract link attributes if link wrapper exists
                    const linkHref = linkElement?.getAttribute('href') || '';
                    const linkTarget = linkElement?.getAttribute('target') || '';
                    const linkTitle = linkElement?.getAttribute('title') || '';

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
                        imageAttributes.linkHref = linkHref;
                        if (linkTarget && linkTarget.trim() !== '') {
                            imageAttributes.linkTarget = linkTarget;
                        }
                        if (linkTitle && linkTitle.trim() !== '') {
                            imageAttributes.linkTitle = linkTitle;
                        }
                    }

                    // Create typo3image element
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

        // Upcast converter for standalone img (backward compatibility)
        // Handles: <img data-htmlarea-file-uid="..." src="...">
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
                    // Only extract non-empty values to prevent unwanted link wrappers
                    const linkHref = linkElement?.getAttribute('href') || '';
                    const linkTarget = linkElement?.getAttribute('target') || '';
                    const linkTitle = linkElement?.getAttribute('title') || '';

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

                    // Only set link attributes if they have non-empty values
                    // IMPORTANT: Don't set empty strings to prevent unwanted link wrappers
                    if (linkHref && linkHref.trim() !== '' && linkHref.trim() !== '/') {
                        imageAttributes.linkHref = linkHref;
                        // Only set target/title if there's an actual link
                        if (linkTarget && linkTarget.trim() !== '') {
                            imageAttributes.linkTarget = linkTarget;
                        }
                        if (linkTitle && linkTitle.trim() !== '') {
                            imageAttributes.linkTitle = linkTitle;
                        }
                    }

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

            const imgElement = writer.createEmptyElement('img', attributes);

            // Check if model has link attributes and wrap in <a> if present
            // Treat "/" as "no link" since it's TYPO3 link browser default/placeholder value
            const linkHref = modelElement.getAttribute('linkHref');
            if (linkHref && linkHref.trim() !== '' && linkHref.trim() !== '/') {
                const linkAttributes = {
                    href: linkHref
                };

                // Add optional link attributes only if they have values
                const linkTarget = modelElement.getAttribute('linkTarget');
                if (linkTarget && linkTarget.trim() !== '') {
                    linkAttributes.target = linkTarget;
                }

                const linkTitle = modelElement.getAttribute('linkTitle');
                if (linkTitle && linkTitle.trim() !== '') {
                    linkAttributes.title = linkTitle;
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

                    // If caption element exists (even if empty), wrap in figure with figcaption slot
                    if (captionElement) {
                        const figure = writer.createContainerElement('figure', {
                            class: 'image'
                        });

                        // Insert image into figure
                        writer.insert(writer.createPositionAt(figure, 0), imageElement);

                        // Create caption slot (renders all children including typo3imageCaption)
                        const captionSlot = writer.createSlot();
                        writer.insert(writer.createPositionAt(figure, 'end'), captionSlot);

                        return figure;
                    }

                    // No caption: return plain image element
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

        // Register edit button for balloon toolbar
        editor.ui.componentFactory.add('editTypo3Image', () => {
            const button = new ButtonView();

            button.set({
                label: 'Edit image',
                icon: '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="m11.077 15 .991-1.416a.75.75 0 1 1 1.229.86l-1.148 1.64a.748.748 0 0 1-.217.206 5.251 5.251 0 0 1-8.503-5.955.741.741 0 0 1 .12-.274l1.147-1.639a.75.75 0 1 1 1.228.86L4.933 10.7l.006.003a3.75 3.75 0 0 0 6.132 4.294l.006.004zm5.494-5.335a.748.748 0 0 1-.12.274l-1.147 1.639a.75.75 0 1 1-1.228-.86l.86-1.23a3.75 3.75 0 0 0-6.144-4.301l-.86 1.229a.75.75 0 0 1-1.229-.86l1.148-1.64a.748.748 0 0 1 .217-.206 5.251 5.251 0 0 1 8.503 5.955zm-4.563-2.532a.75.75 0 0 1 .184 1.045l-3.155 4.505a.75.75 0 1 1-1.229-.86l3.155-4.506a.75.75 0 0 1 1.045-.184z"/></svg>',
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

                if (selectedElement && selectedElement.name === 'typo3image') {
                    // Get caption text from caption element (if exists)
                    const captionElement = getCaptionFromImageModelElement(selectedElement);
                    let captionText = '';
                    if (captionElement) {
                        for (const child of captionElement.getChildren()) {
                            if (child.is('$text')) {
                                captionText += child.data;
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
                            linkHref: selectedElement.getAttribute('linkHref'),
                            linkTarget: selectedElement.getAttribute('linkTarget'),
                            linkTitle: selectedElement.getAttribute('linkTitle'),
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

        // Make image selectable with a single click
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
            if (modelElement && modelElement.name === 'typo3image') {
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

            const modelElement = editor.editing.mapper.toModelElement(targetElement);
            if (modelElement && modelElement.name === 'typo3image') {
                // Select the clicked element
                editor.model.change(writer => {
                    writer.setSelection(modelElement, 'on');
                });

                // Get caption text from caption element (if exists)
                const captionElement = getCaptionFromImageModelElement(modelElement);
                let captionText = '';
                if (captionElement) {
                    for (const child of captionElement.getChildren()) {
                        if (child.is('$text')) {
                            captionText += child.data;
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
                        linkHref: modelElement.getAttribute('linkHref'),
                        linkTarget: modelElement.getAttribute('linkTarget'),
                        linkTitle: modelElement.getAttribute('linkTitle'),
                        caption: captionText,
                    }
                );
            }
        });
    }
}
