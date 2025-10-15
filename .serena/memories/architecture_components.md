# Architecture Components

## Component Overview
The extension follows TYPO3 MVC architecture with dependency injection via Symfony service container.

## Controllers (Classes/Controller/)

### SelectImageController
**Purpose**: Handles image selection and processing for CKEditor integration

**Methods**:
- `mainAction()`: Image browser entry point (route: `/rte/wizard/selectimage`)
- `infoAction()`: Returns image information and processed variants via JSON
- `getImage()`: Retrieves File object from FAL
- `processImage()`: Creates processed image variants with specified dimensions

**Backend Route**: `rteckeditorimage_wizard_select_image`

### ImageRenderingController
**Purpose**: Frontend rendering of images in RTE content

**Integration**: `lib.parseFunc_RTE.tags.img` TypoScript hook
- Method: `renderImageAttributes` (preUserFunc)
- Processes magic images, FAL attributes, zoom functionality

### ImageLinkRenderingController
**Purpose**: Renders images within `<a>` tags

**Integration**: `lib.parseFunc_RTE.tags.a` TypoScript hook
- Method: `renderImages` (preUserFunc)
- Handles linked image scenarios

## Event Listeners (Classes/EventListener/)

### RteConfigurationListener
**Event**: `TYPO3\CMS\RteCKEditor\Form\Element\Event\AfterPrepareConfigurationForEditorEvent`

**Purpose**: Customizes RTE configuration before editor initialization
- Method: `__invoke()` - Event handler for configuration modification

**Service Configuration**: Tagged as `event.listener` with identifier `rte_configuration_listener`

## Database Hooks (Classes/Database/)

### RteImagesDbHook
**Hook**: `$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']`

**Purpose**: TCEmain data processing hook for image handling
- Processes image references during content save operations
- Maintains image relationships in database

**Service**: Public service (registered in ext_localconf.php)

## Data Handling (Classes/DataHandling/SoftReference/)

### RteImageSoftReferenceParser
**Purpose**: Parses soft references for RTE images

**Service Configuration**:
- Public service
- Tagged as `softreference.parser` with parserKey `rtehtmlarea_images`

**Functionality**: Identifies and tracks image references in RTE content for link management and deletion prevention

## Backend Preview (Classes/Backend/Preview/)

### RteImagePreviewRenderer
**Purpose**: Renders image previews in TYPO3 backend
- Displays placeholder/preview for RTE images in backend views

## Utils (Classes/Utils/)

### ProcessedFilesHandler
**Purpose**: Utility class for processed file management
- Handles creation and retrieval of processed image variants

## Service Registration (Configuration/Services.yaml)
All classes use:
- `autowire: true` - Automatic dependency injection
- `autoconfigure: true` - Automatic service configuration
- `public: false` - Services private by default (except specific controllers/hooks)