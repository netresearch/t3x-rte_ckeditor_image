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

(function() {
    'use strict';

    var $;

    CKEDITOR.plugins.add('typo3image', {
        elementBrowser: null,
        init: function (editor) {
            require(['jquery'], function (jquery) {
                $ = jquery;

                var allowedAttributes = ['!src', 'alt', 'title', 'class', 'rel', 'width', 'height'],
                    additionalAttributes = getAdditionalAttributes(editor),
                    $shadowEditor = $('<div>').append(editor.element.$.innerText),
                    existingImages = $shadowEditor.find('img');

                editor.toolbar.forEach(function(toolbar){
                    if(toolbar.items){
                        toolbar.items.forEach(function(item){
                            if(item.command == 'image'){
                                item.setState(CKEDITOR.TRISTATE_OFF)
                            }
                        });
                    }
                });

                if (additionalAttributes.length) {
                    allowedAttributes.push.apply(allowedAttributes, additionalAttributes);
                }

                var edit = function (table, uid, attributes) {
                    getImageInfo(editor, table, uid, {})
                        .then(function(img) {
                            return askImageAttributes(editor, img, attributes, table);
                        })
                        .then(function (img, attributes) {
                            $.extend(attributes, {
                                src: img.processed.url,
                                'data-htmlarea-file-uid': uid,
                                'data-htmlarea-file-table': table
                            });
                            editor.insertElement(
                                editor.document.createElement('img', { attributes: attributes })
                            );
                        });
                    };

                // Update image when editor loads
                if (existingImages.length) {
                    $.each(existingImages, function(i,curImg) {
                        var $curImg = $(curImg),
                            uid = $curImg.attr('data-htmlarea-file-uid'),
                            table = $curImg.attr('data-htmlarea-file-table'),
                            routeUrl = editor.config.typo3image.routeUrl,
                            url = routeUrl
                                + (routeUrl.indexOf('?') === -1 ? '?' : '&')
                                + 'action=info'
                                + '&fileId=' + encodeURIComponent(uid)
                                + '&table=' + encodeURIComponent(table);

                        if (typeof $curImg.attr('width') !== 'undefined' && $curImg.attr('width').length) {
                            url += '&P[width]=' + $curImg.attr('width');
                        }

                        if (typeof $curImg.attr('height') !== 'undefined' && $curImg.attr('height').length) {
                            url += '&P[height]=' + $curImg.attr('height');
                        }

                        $.getJSON(url, function(newImg) {
                            var realEditor = $('#cke_' + editor.element.$.id).find('iframe').contents().find('body'),
                                newImgUrl = newImg.processed.url || newImg.url;

                            // Replace current url with updated one
                            if ($curImg.attr('src') && newImgUrl) {
                                realEditor.html(realEditor.html().replaceAll($curImg.attr('src'), newImgUrl));
                            }
                        });
                    });
                }

                // Override link command
                editor.addCommand('image', {
                    exec: function () {
                        var current = editor.getSelection().getSelectedElement();
                        if (current && current.is('img') && current.getAttribute('data-htmlarea-file-uid')) {
                            // If the button is clicked with a selected image
                            edit(
                                current.getAttribute('data-htmlarea-file-table') || 'sys_file',
                                current.getAttribute('data-htmlarea-file-uid'),
                                current.getAttributes()
                            );
                        } else {
                            selectImage(editor).then(edit);
                        }
                    },
                    allowedContent: 'img[' + allowedAttributes.join(',') + ']',
                    requiredContent: 'img[src]'
                });

                // Use a separate command for editing from the context menu
                editor.addCommand('imageProperties', {
                    exec: function() {
                        var current = editor.getSelection().getSelectedElement(),
                            img;
                        if (current) {
                            if (!current.is('img')) {
                                img = new CKEDITOR.dom.element(current.$.querySelector('img'));
                            } else {
                                img = current;
                            }
                        }
                        if (img && img.getAttribute('data-htmlarea-file-uid')) {
                            edit(
                                img.getAttribute('data-htmlarea-file-table') || 'sys_file',
                                img.getAttribute('data-htmlarea-file-uid'),
                                img.getAttributes()
                            );
                        }
                    }
                });
                // Override the existing `image` context menu item to use the separate editing command
                editor.addMenuItems({
                    image: {
                        label: editor.lang.image.menu,
                        command: 'imageProperties',
                        group: 'image'
                    }
                });

                // Open our and not the CKEditor image dialog on double click:
                editor.on('doubleclick', function(evt) {
                    if (evt.data.dialog === 'image') {
                        delete evt.data.dialog;
                    }
                    var current = evt.data.element;
                    if (!evt.data.dialog && current && current.is('img') && current.getAttribute('data-htmlarea-file-uid')) {
                        edit(
                            current.getAttribute('data-htmlarea-file-table') || 'sys_file',
                            current.getAttribute('data-htmlarea-file-uid'),
                            current.getAttributes()
                        );
                    }
                });

                // Fix images being removed when linked
                // @see typo3/sysext/rte_ckeditor/Resources/Public/JavaScript/RteLinkBrowser.js
                editor.on('insertElement', function (e) {
                    var element = e.data;
                    if (element.getName() === 'a') {
                        var selection = editor.getSelection();
                        const selectedElement = selection.getSelectedElement();
                        if (selection.getSelectedText().trim() !== '' || selectedElement) {
                            element.setHtml(editor.getSelectedHtml(true));
                            var a = null;
                            if (selectedElement && selectedElement.getParent().getName() === 'a') {
                                selectedElement.getParent().remove();
                            }
                            while (a = element.findOne('a')) {
                                a.remove(true);
                            }
                        }
                    }
                });
            });
        }
    });

    /**
     * 
     * @returns value
     */
    function getTitleText() {
        var field = $('input[data-formengine-input-name^="data[tx_news_domain_model_news]"][data-formengine-input-name$="title]"]').first();
        var value = "";
        if (field.length) {
            value = field.first().val();
        }
        return value;
    }

    /**
     * 
     * @param url
     * @return relativeUrl
     */
    function urlToRelative(url) {

        // check for absolute URL first

        if (!url) {
            return;
        }

        if (url.indexOf("http://") !== -1 || url.indexOf("https://") !== -1) {
            return new URL(url).pathname;
        } else {
            if (url[0] !== "/") {
                return "/" + url;
            }
        }
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

        // Init image editing popup
        require(['TYPO3/CMS/Backend/Modal'], function(Modal) {
            var $modal = Modal.advanced({
                title: editor.lang.image.title,
                content: dialog.$el,
                buttons: [
                    {
                        text: editor.lang.common.ok,
                        trigger: function () {

                            var dialogInfo = dialog.get(),
                                filteredAttr = {},
                                allowedAttributes = [
                                    '!src', 'alt', 'title', 'class', 'rel', 'width', 'height', 'data-htmlarea-zoom', 'data-title-override', 'data-alt-override'
                                ],
                                additionalAttributes = getAdditionalAttributes(editor),
                                attributesNew = $.extend({}, img, dialogInfo);

                            if (additionalAttributes.length) {
                                allowedAttributes.push.apply(allowedAttributes, additionalAttributes);
                            }

                            filteredAttr = Object.keys(attributesNew)
                                .filter(function(key) {
                                    return allowedAttributes.includes(key)
                                })
                                .reduce(function(obj, key) {
                                    obj[key] = attributesNew[key];
                                    return obj;
                                }, {});

                            getImageInfo(editor, table, img.uid, filteredAttr)
                                .then(function (getImg) {

                                    $.extend(filteredAttr, {
                                        src: urlToRelative(getImg.url),
                                        width: getImg.processed.width || getImg.width,
                                        height: getImg.processed.height || getImg.height,
                                        'data-cke-saved-src': getImg.processed.url,
                                        'data-htmlarea-file-uid': img.uid,
                                        'data-htmlarea-file-table': table
                                    });

                                    editor.insertElement(
                                        editor.document.createElement('img', { attributes: filteredAttr })
                                    );
                                });
                            $modal.modal('hide');
                            return deferred;
                        }
                    }
                ]
            });
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

        var routeUrl = editor.config.typo3image.routeUrl,
            url = routeUrl
                + (routeUrl.indexOf('?') === -1 ? '?' : '&')
                + 'action=info'
                + '&fileId=' + encodeURIComponent(uid)
                + '&table=' + encodeURIComponent(table);

        if (typeof params.width !== 'undefined' && params.width.length) {
            url += '&P[width]=' + params.width;
        }

        if (typeof params.height !== 'undefined' && params.height.length) {
            url += '&P[height]=' + params.height;
        }

        return $.getJSON(url);
    }

    function _getElementsList(currentModal) {
        var list = {};
        currentModal.contents().find('#typo3-filelist input').each(function (i, o) {
            list[$(o).attr("name")] = {
                "type": "file",
                "table": "sys_file",
                "uid": $(o).data("file-uid"),
                "fileName": $(o).data("file-name")
            };
        });
        return list;
    }

    /**
     * Open a window with container iframe
     *
     * @param {Object} editor The CKEditor instance
     * @return {$.Deferred}
     */
    function selectImage(editor) {
        // @see \TYPO3\CMS\Recordlist\Browser\AbstractElementBrowser::getBParamDataAttributes
        // @see \TYPO3\CMS\Recordlist\Browser\FileBrowser::render

        var bparams = [
                editor.name, // $fieldRef
                'ckeditor', // $rteParams
                'typo3image', // $rteConfig
                editor.config.typo3image.allowedExtensions || '', // allowedFileExtensions -> Defaults set in controller
                editor.name // $irreObjectId
            ],
            routeUrl = editor.config.typo3image.routeUrl,
            url = routeUrl
                + (routeUrl.indexOf('?') === -1 ? '?' : '&')
                + 'contentsLanguage=' + editor.config.contentsLanguage
                + '&editorId=' + editor.id
                + '&bparams=' + bparams.join('|'),
            deferred = $.Deferred(),
            $modal;

        require(['TYPO3/CMS/Backend/Modal'], function (Modal) {
            $modal = Modal.advanced({
                type: Modal.types.iframe,
                title: editor.lang.common.image,
                content: url,
                size: Modal.sizes.large,
                callback: function(currentModal) {
                    currentModal.find('iframe').on('load', function (e) {

                        var AddImage = {
                            elements: {},
                            handle: function(e) {

                                var items = $(e).parents('div').last().find('#typo3-filelist input'),
                                    selectedItems = [];

                                if (items.length) {
                                    items.each(function(position, item) {
                                        if (item.checked && item.name) {
                                            selectedItems.push({uid: AddImage.elements[item.name].uid, table: AddImage.elements[item.name].table});
                                        }
                                    });
                                    if(selectedItems.length > 0){
                                        AddImage.addedImage(selectedItems);
                                    }
                                }
                            },

                            addedImage: function(selectedItems){
                                $modal.modal('hide');
                                deferred.resolve(selectedItems[0].table, selectedItems[0].uid);
                            },

                        };

                        $.extend(AddImage.elements, _getElementsList($(this)));


                        $(this).contents().find('[data-close]').on('click', function (e) {
                            e.stopImmediatePropagation();
                            var selectedItems = [];
                            selectedItems.push({
                                uid: AddImage.elements['file_' + $(this).data('file-uid')].uid,
                                table: AddImage.elements['file_' + $(this).data('file-uid')].table
                            });
                            AddImage.addedImage(selectedItems);
                        });
                        $(this).contents().find('button[data-multi-record-selection-action=import]').on('click',  function (e) {
                            e.stopImmediatePropagation();

                            AddImage.handle($(this));
                        });
                    });
                }
            });
        });

        return deferred;
    }

    /**
     * Fetch attributes for the <a> tag which are allowed additionally
     * @param {Object} editor The CKEditor instance
     *
     * @return {Array} registered attributes available for the link
     */
    function getAdditionalAttributes(editor) {
        if (editor.config.typo3image.additionalAttributes && editor.config.typo3image.additionalAttributes.length) {
            return editor.config.typo3image.additionalAttributes;
        } else {
            return [];
        }
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
                width: { label: editor.lang.common.width, type: 'number' },
                height: { label: editor.lang.common.height, type: 'number' }
            },
            {
                title: { label: editor.lang.common.advisoryTitle, type: 'text' },
                alt: { label: editor.lang.image.alt, type: 'text' }
            }
        ];

        d.$el = $('<div class="rteckeditorimage">');

        $.each(fields, function () {
            var $row = $('<div class="row">').appendTo(d.$el);

            $rows.push($row);
            $.each(this, function(key, config) {
                var $group = $('<div class="form-group">').appendTo($('<div class="col-xs-12 col-sm-6">').appendTo($row));
                var id = 'rteckeditorimage-' + key;
                $('<label for="' + id + '">' + config.label + '</label>').appendTo($group);
                var $el = $('<input type="' + config.type + '" id ="' + id + '" name="' + key + '" class="form-control">');

                var placeholder = (config.type === 'text' ? (img[key] || '') : img.processed[key]) + '';
                var value = ((attributes[key] || '') + '').trim();
                $el.attr('placeholder', placeholder);
                $el.val(value);

                if (config.type === 'text') {
                    var startVal = value,
                        hasDefault = img[key] && img[key].trim(),
                        cbox = $('<input type="checkbox">')
                            .attr('id', 'checkbox-' + key)
                            .prop('checked', !!value || !hasDefault)
                            .prop('disabled', !hasDefault),
                        cboxLabel = $('<label></label>').text(
                            hasDefault ? img.lang.override.replace('%s', img[key]) : img.lang.overrideNoDefault
                        );

                    $el.prop('disabled', hasDefault && !value);
                    cbox.prependTo(
                        cboxLabel.appendTo($('<div class="checkbox" style="margin: 0 0 6px;">').appendTo($group))
                    );

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

                    $el.on('input', function() {
                        constrainDimensions(1);
                    });
                    $el.on('change', function() {
                        constrainDimensions(min);
                    });
                    $el.on('mousewheel', function(e) {
                        constrainDimensions(min, e.originalEvent.wheelDelta > 0 ? 1 : -1);
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                    });
                }

                $group.append($el);
                elements[key] = $el;
            });
        });

        var $checkboxTitle = d.$el.find('#checkbox-title'),
            $checkboxAlt = d.$el.find('#checkbox-alt'),
            $inputWidth = d.$el.find('#rteckeditorimage-width'),
            $inputHeight = d.$el.find('#rteckeditorimage-height'),
            $zoom = $('<input id="checkbox-zoom" type="checkbox">'),
            cssClass = attributes.class || '',
            $inputCssClass = $('<input id="input-cssclass" type="text" class="form-control">').val(cssClass),
            $customRow = $('<div class="row">').insertAfter($rows[0]),
            $customRowCol1 = $('<div class="col-xs-12 col-sm-6">'),
            $customRowCol2 = $('<div class="col-xs-12 col-sm-6">');

        $zoom.prependTo(
            $('<label>').text(img.lang.zoom).appendTo(
                $('<div class="checkbox" style="margin: -5px 0 15px;">').prependTo($customRowCol1)
            )
        );

        $inputCssClass
            .prependTo(
                $('<div class="form-group">').prependTo($customRowCol2)
            )
            .before($('<label for="input-cssclass">').text(img.lang.cssClass));

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

        d.get = function () {
            $.each(fields, function () {
                $.each(this, function(key) {
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
}());
