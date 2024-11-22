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
import { Core, UI, Engine } from '@typo3/ckeditor5-bundle.js';
import { default as Modal } from '@typo3/backend/modal.js';
import $ from 'jquery';


class DoubleClickObserver extends Engine.DomEventObserver {
    constructor(view) {
        super(view);

        this.domEventType = 'dblclick';
    }

    onDomEvent(domEvent) {
        this.fire(domEvent.type, domEvent);
    }
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
            width: { label: 'Width', type: 'number' },
            height: { label: 'Height', type: 'number' }
        },
        {
            title: { label: 'Advisory Title', type: 'text' },
            alt: { label: 'Alternative Text', type: 'text' }
        }
    ];

    d.$el = $('<div class="rteckeditorimage">');

    $.each(fields, function () {
        var $row = $('<div class="row">').appendTo(d.$el);

        $rows.push($row);
        $.each(this, function (key, config) {
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

                $el.on('input', function () {
                    constrainDimensions(1);
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
                text: 'Ok',
                trigger: function () {

                    var dialogInfo = dialog.get(),
                        filteredAttr = {},
                        allowedAttributes = [
                            '!src', 'alt', 'title', 'class', 'rel', 'width', 'height', 'data-htmlarea-zoom', 'data-title-override', 'data-alt-override'
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
                                src: urlToRelative(getImg.url),
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
    let url = editor.config.get('style').typo3image.routeUrl + '&action=info&fileId=' + encodeURIComponent(uid) + '&table=' + encodeURIComponent(table) + '&contentsLanguage=en&editorId=123';

    if (typeof params.width !== 'undefined' && params.width.length) {
        url += '&P[width]=' + params.width;
    }

    if (typeof params.height !== 'undefined' && params.height.length) {
        url += '&P[height]=' + params.height;
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
    const contentUrl = editor.config.get('style').typo3image.routeUrl + '&contentsLanguage=en&editorId=123&bparams=' + bparams.join('|');

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
                console.log(attributes);

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
                });

                editor.model.insertObject(newImage);
            });
        });
};


export default class Typo3Image extends Core.Plugin {
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
        // Convert `addModelHtmlClass` to and event
        ghs.decorate('addModelHtmlClass')
        // Add listener to update the `class` attribute of the `typo3image` element
        this.listenTo(ghs, 'addModelHtmlClass', (event, [viewElement, className, selectable]) => {
            if (selectable && selectable.name === 'typo3image') {
                editor.model.change(writer => {
                    writer.setAttribute('class', className.join(' '), selectable);
                })
            }
        })

        // Convert `removeModelHtmlClass` to and event
        ghs.decorate('removeModelHtmlClass')
        // Add listener to remove the `class` attribute of the `typo3image` element
        this.listenTo(ghs, 'removeModelHtmlClass', (event, [viewElement, className, selectable]) => {
            if (selectable && selectable.name === 'typo3image') {
                editor.model.change(writer => {
                    writer.removeAttribute('class', selectable);
                })
            }
        })

        editor.editing.view.addObserver(DoubleClickObserver);

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
                'width',
                'height',
                'linkHref',
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
                    });
                }
            });

        editor.conversion
            .for('downcast')
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
                    const attributes= {
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

                    return writer.createEmptyElement('img', attributes)
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
        editor.model.change(writer => {
            const range = writer.createRangeIn(editor.model.document.getRoot());
            console.log(range);
            for (const value of range.getWalker({ ignoreElementEnd: true })) {
                console.log(value);
            }
        });


        editor.ui.componentFactory.add('insertimage', () => {
            const button = new UI.ButtonView();

            button.set({
                label: 'Insert image',
                icon: '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M6.91 10.54c.26-.23.64-.21.88.03l3.36 3.14 2.23-2.06a.64.64 0 0 1 .87 0l2.52 2.97V4.5H3.2v10.12l3.71-4.08zm10.27-7.51c.6 0 1.09.47 1.09 1.05v11.84c0 .59-.49 1.06-1.09 1.06H2.79c-.6 0-1.09-.47-1.09-1.06V4.08c0-.58.49-1.05 1.1-1.05h14.38zm-5.22 5.56a1.96 1.96 0 1 1 3.4-1.96 1.96 1.96 0 0 1-3.4 1.96z"/></svg>',
                tooltip: true,
                withText: false,
            });

            button.on('execute', () => {
                console.log(editor.config);
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
                            'data-title-override': selectedElement.getAttribute('titleOverride'),
                            'data-alt-override': selectedElement.getAttribute('altOverride'),
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
        editor.listenTo(editor.editing.view.document, 'click', (event, data) => {
            const modelElement = editor.editing.mapper.toModelElement(data.target);
            if (modelElement && modelElement.name === 'typo3image') {
                // Select the clicked element
                editor.model.change(writer => {
                    writer.setSelection(modelElement, 'on');
                });
            }
        })

        editor.listenTo(editor.editing.view.document, 'dblclick', (event, data) => {
            const modelElement = editor.editing.mapper.toModelElement(data.target);
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
                        'data-title-override': modelElement.getAttribute('titleOverride'),
                        'data-alt-override': modelElement.getAttribute('altOverride'),
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
