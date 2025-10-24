.. include:: /Includes.rst.txt

.. _examples-image-styles:

=============
Image Styles
=============

Examples for configuring custom image styles with CSS classes and style groups.

.. contents:: Table of Contents
   :depth: 3
   :local:

Bootstrap-Style Images
======================

**Objective**: Add Bootstrap image utility classes

Configuration
-------------

.. code-block:: yaml
   :caption: EXT:my_site/Configuration/RTE/Default.yaml

   editor:
     config:
       style:
         definitions:
           # Alignment Styles
           - name: 'Float Left'
             element: 'img'
             classes: ['float-start', 'me-3', 'mb-3']
           - name: 'Float Right'
             element: 'img'
             classes: ['float-end', 'ms-3', 'mb-3']
           - name: 'Center'
             element: 'img'
             classes: ['d-block', 'mx-auto']

           # Size Styles
           - name: 'Thumbnail'
             element: 'img'
             classes: ['img-thumbnail']
           - name: 'Rounded'
             element: 'img'
             classes: ['rounded']
           - name: 'Circle'
             element: 'img'
             classes: ['rounded-circle']

           # Responsive
           - name: 'Responsive'
             element: 'img'
             classes: ['img-fluid']

         groupDefinitions:
           - name: 'Image Alignment'
             styles: ['Float Left', 'Float Right', 'Center']
           - name: 'Image Style'
             styles: ['Thumbnail', 'Rounded', 'Circle', 'Responsive']

CSS (if not using Bootstrap)
-----------------------------

.. code-block:: css
   :caption: EXT:my_site/Resources/Public/Css/rte-images.css

   .float-start { float: left; }
   .float-end { float: right; }
   .me-3 { margin-right: 1rem; }
   .ms-3 { margin-left: 1rem; }
   .mb-3 { margin-bottom: 1rem; }
   .d-block { display: block; }
   .mx-auto { margin-left: auto; margin-right: auto; }

   .img-thumbnail {
       padding: 0.25rem;
       background-color: #fff;
       border: 1px solid #dee2e6;
       border-radius: 0.25rem;
       max-width: 100%;
       height: auto;
   }

   .rounded { border-radius: 0.25rem; }
   .rounded-circle { border-radius: 50%; }
   .img-fluid { max-width: 100%; height: auto; }

**Result**: Professional image styling options ✅

Related Documentation
=====================

- :ref:`ckeditor-style-integration` - Style system details
- :ref:`examples-responsive-images` - Responsive image examples
- :ref:`integration-configuration` - Configuration guide
