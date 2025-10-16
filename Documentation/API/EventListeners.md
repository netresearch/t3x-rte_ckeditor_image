# Event Listeners API

Complete API reference for PSR-14 event listeners in the rte_ckeditor_image extension.

## RteConfigurationListener

**Namespace**: `Netresearch\RteCKEditorImage\EventListener`

**Purpose**: Injects backend route configuration into CKEditor RTE configuration for image plugin integration.

**Event**: `TYPO3\CMS\RteCKEditor\Form\Element\Event\AfterPrepareConfigurationForEditorEvent`

**Service Configuration**:
```yaml
Netresearch\RteCKEditorImage\EventListener\RteConfigurationListener:
  tags:
    - name: event.listener
      identifier: 'rte_configuration_listener'
      event: TYPO3\CMS\RteCKEditor\Form\Element\Event\AfterPrepareConfigurationForEditorEvent
```

---

## PSR-14 Event System

### What is PSR-14?

PSR-14 is a standardized event dispatcher interface that allows decoupled components to communicate through events:

- **Events**: Objects containing data about what happened
- **Listeners**: Callables that respond to specific events
- **Dispatcher**: Routes events to registered listeners

### Why Use Events Over Hooks?

| Feature | PSR-14 Events | Traditional Hooks |
|---------|---------------|-------------------|
| Standard | Yes (PSR standard) | No (TYPO3-specific) |
| Type Safety | Strong (typed events) | Weak (array parameters) |
| Discoverability | IDE autocomplete | Manual documentation |
| Testing | Easy (mock events) | Difficult (DataHandler mock) |
| Modern | PHP 7.4+ features | Legacy patterns |

---

## Event Flow

```
Backend Form Rendering
    ↓
RteCKEditor prepares configuration
    ↓
AfterPrepareConfigurationForEditorEvent dispatched
    ↓
RteConfigurationListener invoked
    ↓
Configuration injected with route URL
    ↓
CKEditor loads with typo3image plugin config
```

---

## RteConfigurationListener API

### __invoke()

```php
public function __invoke(
    AfterPrepareConfigurationForEditorEvent $event
): void
```

**Purpose**: Main listener method that modifies RTE configuration before it's sent to the CKEditor instance.

**Parameters**:
- `$event` - Event object containing mutable RTE configuration

**Processing Steps**:

1. **URI Builder Instantiation**:
```php
$uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
```
Creates TYPO3 URI builder for backend route generation.

2. **Configuration Retrieval**:
```php
$configuration = $event->getConfiguration();
```
Gets current RTE configuration array from event.

3. **Route URL Injection**:
```php
$configuration['style']['typo3image'] = [
    'routeUrl' => (string) $uriBuilder->buildUriFromRoute('rteckeditorimage_wizard_select_image'),
];
```
Adds backend route URL to configuration under `style.typo3image.routeUrl`.

4. **Configuration Update**:
```php
$event->setConfiguration($configuration);
```
Updates event with modified configuration.

**Result**:
CKEditor receives configuration like:
```javascript
{
  style: {
    typo3image: {
      routeUrl: '/typo3/rte/wizard/selectimage?...'
    }
  }
}
```

---

## AfterPrepareConfigurationForEditorEvent

### Event Properties

```php
class AfterPrepareConfigurationForEditorEvent
{
    private array $configuration;

    public function getConfiguration(): array;
    public function setConfiguration(array $configuration): void;
}
```

### Event Lifecycle

**Dispatch Point**: After RTE configuration is prepared but before rendering

**Mutability**: Configuration array can be modified by listeners

**Priority**: Not configurable (TYPO3 dispatches in registration order)

**Multiple Listeners**: Supported - each listener receives modified config from previous

---

## Configuration Injection Pattern

### What Gets Injected?

```php
[
    'style' => [
        'typo3image' => [
            'routeUrl' => '/typo3/rte/wizard/selectimage?token=abc123'
        ]
    ]
]
```

### How CKEditor Plugin Accesses It

```javascript
// In Resources/Public/JavaScript/Plugins/typo3image.js
const routeUrl = editor.config.get('style').typo3image.routeUrl;

// Used for image selection modal
Modal.advanced({
    type: Modal.types.iframe,
    content: routeUrl + '&contentsLanguage=en&bparams=...'
});
```

### Why This Pattern?

- **Dynamic Routes**: Backend routes include CSRF tokens that change per session
- **Environment Independence**: Works across different TYPO3 installations
- **Security**: CSRF tokens validated by TYPO3 backend
- **Flexibility**: Easily extended for additional configuration

---

## Usage Examples

### Accessing Route URL in JavaScript

```javascript
// CKEditor plugin initialization
export default class Typo3Image extends Core.Plugin {
    init() {
        const editor = this.editor;
        const routeUrl = editor.config.get('style').typo3image.routeUrl;

        // Use for image info API calls
        function getImageInfo(fileUid) {
            const url = routeUrl + '&action=info&fileId=' + fileUid;
            return fetch(url).then(r => r.json());
        }
    }
}
```

### Extending Configuration with Custom Listener

Create your own listener to add custom configuration:

```php
// EXT:my_ext/Classes/EventListener/CustomRteConfigListener.php
namespace MyVendor\MyExt\EventListener;

use TYPO3\CMS\RteCKEditor\Form\Element\Event\AfterPrepareConfigurationForEditorEvent;

final class CustomRteConfigListener
{
    public function __invoke(AfterPrepareConfigurationForEditorEvent $event): void
    {
        $configuration = $event->getConfiguration();

        // Add custom configuration
        $configuration['myext'] = [
            'apiEndpoint' => '/api/my-endpoint',
            'options' => ['foo' => 'bar'],
        ];

        $event->setConfiguration($configuration);
    }
}
```

Register in `Configuration/Services.yaml`:
```yaml
MyVendor\MyExt\EventListener\CustomRteConfigListener:
  tags:
    - name: event.listener
      identifier: 'custom_rte_config_listener'
      event: TYPO3\CMS\RteCKEditor\Form\Element\Event\AfterPrepareConfigurationForEditorEvent
```

Access in CKEditor plugin:
```javascript
const myConfig = editor.config.get('myext');
console.log(myConfig.apiEndpoint);  // '/api/my-endpoint'
```

---

## Modifying Existing Configuration

### Override typo3image Route

```php
public function __invoke(AfterPrepareConfigurationForEditorEvent $event): void
{
    $configuration = $event->getConfiguration();

    // Use custom route instead
    $configuration['style']['typo3image']['routeUrl'] = '/custom/image/route';

    $event->setConfiguration($configuration);
}
```

### Add Additional Routes

```php
public function __invoke(AfterPrepareConfigurationForEditorEvent $event): void
{
    $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
    $configuration = $event->getConfiguration();

    // Keep existing typo3image config
    // Add new routes for custom functionality
    $configuration['style']['typo3image']['uploadRoute'] =
        (string) $uriBuilder->buildUriFromRoute('my_custom_upload');
    $configuration['style']['typo3image']['processRoute'] =
        (string) $uriBuilder->buildUriFromRoute('my_custom_process');

    $event->setConfiguration($configuration);
}
```

---

## Listener Execution Order

### Multiple Listeners for Same Event

When multiple listeners register for `AfterPrepareConfigurationForEditorEvent`:

1. **Registration Order**: Listeners execute in the order they're registered
2. **Configuration Chain**: Each listener receives config modified by previous listeners
3. **No Priority**: TYPO3 doesn't support listener priority for this event

### Example: Two Listeners

```yaml
# services.yaml
MyVendor\FirstExt\EventListener\FirstListener:
  tags:
    - name: event.listener
      event: AfterPrepareConfigurationForEditorEvent

MyVendor\SecondExt\EventListener\SecondListener:
  tags:
    - name: event.listener
      event: AfterPrepareConfigurationForEditorEvent
```

**Execution**:
```
1. FirstListener receives base config
2. FirstListener modifies config (adds 'first' key)
3. SecondListener receives config with 'first' key
4. SecondListener modifies config (adds 'second' key)
5. Final config has both 'first' and 'second' keys
```

---

## Testing Event Listeners

### Unit Test Example

```php
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Backend\Routing\UriBuilder;

class RteConfigurationListenerTest extends UnitTestCase
{
    /**
     * @test
     */
    public function invokeAddsRouteUrlToConfiguration(): void
    {
        // Arrange
        $event = new AfterPrepareConfigurationForEditorEvent(['existing' => 'config']);
        $listener = new RteConfigurationListener();

        // Act
        $listener->__invoke($event);

        // Assert
        $config = $event->getConfiguration();
        self::assertArrayHasKey('style', $config);
        self::assertArrayHasKey('typo3image', $config['style']);
        self::assertArrayHasKey('routeUrl', $config['style']['typo3image']);
        self::assertStringContainsString('rteckeditorimage_wizard_select_image', $config['style']['typo3image']['routeUrl']);
    }

    /**
     * @test
     */
    public function invokePreservesExistingConfiguration(): void
    {
        // Arrange
        $existingConfig = [
            'toolbar' => ['items' => ['bold', 'italic']],
            'style' => ['definitions' => []]
        ];
        $event = new AfterPrepareConfigurationForEditorEvent($existingConfig);
        $listener = new RteConfigurationListener();

        // Act
        $listener->__invoke($event);

        // Assert
        $config = $event->getConfiguration();
        self::assertArrayHasKey('toolbar', $config);
        self::assertArrayHasKey('style', $config);
        self::assertArrayHasKey('definitions', $config['style']);
        self::assertArrayHasKey('typo3image', $config['style']);
    }
}
```

---

## Debugging Event Listeners

### Check if Listener is Registered

```php
// Debug in TYPO3 backend
use TYPO3\CMS\Core\EventDispatcher\ListenerProvider;
use TYPO3\CMS\Core\Utility\GeneralUtility;

$listenerProvider = GeneralUtility::makeInstance(ListenerProvider::class);
$listeners = $listenerProvider->getListenersForEvent(
    new AfterPrepareConfigurationForEditorEvent([])
);

// Dump listeners
var_dump($listeners);
```

### Log Configuration Changes

```php
public function __invoke(AfterPrepareConfigurationForEditorEvent $event): void
{
    $configuration = $event->getConfiguration();

    // Log before modification
    GeneralUtility::makeInstance(LogManager::class)
        ->getLogger(__CLASS__)
        ->debug('RTE config before', ['config' => $configuration]);

    // Modify configuration
    $configuration['style']['typo3image'] = [
        'routeUrl' => $this->getRouteUrl(),
    ];

    // Log after modification
    GeneralUtility::makeInstance(LogManager::class)
        ->getLogger(__CLASS__)
        ->debug('RTE config after', ['config' => $configuration]);

    $event->setConfiguration($configuration);
}
```

### Verify Configuration in Browser

```javascript
// In browser console after RTE loads
Object.values(CKEDITOR.instances)[0].config.get('style').typo3image;
// Should output: {routeUrl: '/typo3/rte/wizard/selectimage?...'}
```

---

## Common Issues

### Issue: routeUrl Not Available in Plugin

**Symptoms**:
- JavaScript error: "Cannot read property 'typo3image' of undefined"
- Image selection modal doesn't open

**Cause**: Event listener not registered or not executing

**Solution**:
1. Verify service configuration in `Configuration/Services.yaml`
2. Clear system cache: `./vendor/bin/typo3 cache:flush --group=system`
3. Check event listener is loaded: `grep -r "event.listener" var/cache/code/di/`

---

### Issue: Multiple Listeners Conflict

**Symptoms**:
- Configuration keys overwritten
- Expected configuration missing

**Cause**: Later listener overwrites earlier listener's changes

**Solution**: Merge instead of replace:
```php
// ❌ Wrong - Overwrites entire 'style' key
$configuration['style'] = ['typo3image' => [...]];

// ✅ Right - Merges with existing
$configuration['style'] = array_merge(
    $configuration['style'] ?? [],
    ['typo3image' => [...]]
);
```

---

### Issue: CSRF Token Errors

**Symptoms**:
- Backend route returns 403 errors
- "Invalid CSRF token" in logs

**Cause**: Route URL built incorrectly or cached

**Solution**:
- Always use `UriBuilder->buildUriFromRoute()` (includes token)
- Never cache route URLs (they expire)
- Route URL must be generated per request

```php
// ❌ Wrong - Static URL without token
$configuration['style']['typo3image']['routeUrl'] = '/typo3/rte/wizard/selectimage';

// ✅ Right - Dynamic URL with token
$configuration['style']['typo3image']['routeUrl'] =
    (string) $uriBuilder->buildUriFromRoute('rteckeditorimage_wizard_select_image');
```

---

## Advanced Patterns

### Conditional Configuration

```php
public function __invoke(AfterPrepareConfigurationForEditorEvent $event): void
{
    $configuration = $event->getConfiguration();

    // Only add config for specific RTE presets
    if (($configuration['preset'] ?? '') === 'full') {
        $configuration['style']['typo3image'] = [
            'routeUrl' => $this->getRouteUrl(),
            'enableAdvancedFeatures' => true,
        ];
    }

    $event->setConfiguration($configuration);
}
```

### User-Specific Configuration

```php
use TYPO3\CMS\Core\Context\Context;

public function __invoke(AfterPrepareConfigurationForEditorEvent $event): void
{
    $context = GeneralUtility::makeInstance(Context::class);
    $configuration = $event->getConfiguration();

    // Add config based on backend user permissions
    if ($context->getPropertyFromAspect('backend.user', 'isAdmin')) {
        $configuration['style']['typo3image']['allowExternalImages'] = true;
    }

    $event->setConfiguration($configuration);
}
```

### Environment-Specific Configuration

```php
use TYPO3\CMS\Core\Core\Environment;

public function __invoke(AfterPrepareConfigurationForEditorEvent $event): void
{
    $configuration = $event->getConfiguration();

    // Development-only features
    if (Environment::getContext()->isDevelopment()) {
        $configuration['style']['typo3image']['debugMode'] = true;
        $configuration['style']['typo3image']['verboseLogging'] = true;
    }

    $event->setConfiguration($configuration);
}
```

---

## Related Documentation

- [Controllers API](Controllers.md)
- [Data Handling API](DataHandling.md)
- [CKEditor Plugin Development](../CKEditor/Plugin-Development.md)
- [Configuration Guide](../Integration/Configuration.md)
- [Architecture Overview](../Architecture/Overview.md)
