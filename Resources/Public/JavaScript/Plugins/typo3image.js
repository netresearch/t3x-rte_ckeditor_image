/*
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

'use strict';


(function() {
    var $;
    require(['jquery'], function (jquery) {
        $ = jquery;
    });

    CKEDITOR.plugins.add('typo3image', {
        elementBrowser: null,
        init: function (editor) {
            var allowedAttributes = ['!src', 'alt', 'title', 'class', 'rel', 'width', 'height'],
                required = 'img[src]';

            var additionalAttributes = getAdditionalAttributes(editor);
            if (additionalAttributes.length) {
                allowedAttributes.push.apply(allowedAttributes, additionalAttributes);
            }

            var edit = function (table, uid, attributes) {
                getImageInfo(editor, table, uid)
                    .then(function(img) {
                        return askImageAttributes(editor, img, attributes);
                    })
                    .then(function (img, attributes) {
                        $.extend(attributes, {
                            src: '../' + img.processed.url,
                            'data-htmlarea-file-uid': uid,
                            'data-htmlarea-file-table': table
                        });
                        editor.insertElement(
                            editor.document.createElement('img', { attributes: attributes })
                        );
                    });
            };

            // Override link command
            editor.addCommand('image', {
                exec: function () {
                    var current = editor.getSelection().getSelectedElement();
                    if (current && current.is('img') && current.getAttribute('data-htmlarea-file-uid')) {
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
        }
    });

    /**
     * Show image attributes dialog
     *
     * @param editor
     * @param img
     * @param attributes
     * @return {$.Deferred}
     */
    function askImageAttributes(editor, img, attributes) {
        var deferred = $.Deferred();
        var dialog = getImageDialog(editor, img, $.extend({}, img.processed, attributes));
        require(['TYPO3/CMS/Backend/Modal'], function(Modal) {
            var $modal = Modal.advanced({
                title: editor.lang.image.title,
                content: dialog.$el,
                buttons: [
                    {
                        text: editor.lang.common.ok,
                        trigger: function () {
                            $modal.modal('hide');
                            deferred.resolve(img, dialog.get());
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
     * @return {$.Deferred}
     */
    function getImageInfo(editor, table, uid) {
        var routeUrl = editor.config.typo3image.routeUrl;
        var url = routeUrl
            + (routeUrl.indexOf('?') === -1 ? '?' : '&')
            + 'action=info'
            + '&id=' + encodeURIComponent(uid)
            + '&table=' + encodeURIComponent(table);

        return $.getJSON(url);
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
            editor.name, // $irreObjectId
            '', // $irreCheckUniqueAction
            '', // $irreAddAction
            'onSelected' // $irreInsertAction
        ];
        var routeUrl = editor.config.typo3image.routeUrl;
        var url = routeUrl
            + (routeUrl.indexOf('?') === -1 ? '?' : '&')
            + 'contentsLanguage=' + editor.config.contentsLanguage
            + '&editorId=' + editor.id
            + '&bparams=' + bparams.join('|');

        var deferred = $.Deferred();

        require(['TYPO3/CMS/Backend/Modal'], function (Modal) {
            var $modal = Modal.advanced({
                type: Modal.types.iframe,
                title: editor.lang.common.image,
                content: url,
                size: Modal.sizes.large,
                callback: function(currentModal) {
                    currentModal.find('iframe').on('load', function (e) {
                        e.currentTarget.contentWindow.opener = {
                            focus: function () {
                                editor.focus();
                            },
                            top: window.top,
                            onSelected: function(editorName, table, uid, type) {
                                if (editorName === editor.name) {
                                    $modal.modal('hide');
                                    deferred.resolve(table, uid);
                                }
                            }
                        };
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
        var d = {};
        var $rows = [];
        d.$el = $('<div class="rteckeditorimage">');
        const fields = [
            {
                width: { label: editor.lang.common.width, type: 'number' },
                height: { label: editor.lang.common.height, type: 'number' },
            },
            {
                title: { label: editor.lang.common.advisoryTitle, type: 'text' },
                alt: { label: editor.lang.image.alt, type: 'text' }
            }
        ];
        var elements = {};
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
                    var hasDefault = img[key] && img[key].trim();
                    $el.prop('disabled', hasDefault && !value);

                    var cbox = $('<input type="checkbox">')
                        .prop('checked', !!value || !hasDefault)
                        .prop('disabled', !hasDefault);
                    var cboxLabel = $('<label></label>').text(
                        hasDefault ? img.lang.override.replace('%s', img[key]) : img.lang.overrideNoDefault
                    );
                    cbox.prependTo(
                        cboxLabel.appendTo($('<div class="checkbox" style="margin: 0 0 6px;">').appendTo($group))
                    );
                    cboxLabel.click(function () {
                        $el.prop('disabled', !cbox.prop('checked'));
                        if (!cbox.prop('checked')) {
                            $el.val('');
                        } else {
                            $el.focus();
                        }
                    })
                } else if (config.type === 'number') {
                    var ratio = img.processed.width / img.processed.height;
                    if (key === 'height') {
                        ratio = 1 / ratio;
                    }
                    var opposite = 1;
                    var max = img.processed[key];
                    var min = Math.ceil(opposite * ratio);
                    $el.attr('max', max);
                    $el.attr('min', min);

                    var constrainDimensions = function (currentMin, delta) {
                        value = parseInt($el.val().replace(/[^0-9]/g, '') || max);
                        if (delta) {
                            value += delta;
                        }
                        value = Math.max(currentMin, Math.min(value, max));
                        var $opposite = elements[key === 'width' ? 'height' : 'width'];
                        var oppositeMax = parseInt($opposite.attr('max'));
                        var ratio = oppositeMax / max;
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

        var $zoom = $('<input type="checkbox">');
        if (attributes['data-htmlarea-zoom']) {
            $zoom.prop('checked', true);
        }
        $zoom.prependTo(
            $('<label>').text(img.lang.zoom).appendTo(
                $('<div class="checkbox" style="margin: -5px 0 15px;">').insertAfter($rows[0])
            )
        );

        d.get = function () {
            var attributes = {};
            $.each(fields, function () {
                $.each(this, function(key) {
                    var value = elements[key].val();
                    if (value) {
                        attributes[key] = value;
                    }
                });
            });
            if ($zoom.prop('checked')) {
                attributes['data-htmlarea-zoom'] = true;
            }
            return attributes;
        };
        return d;
    }

})();
