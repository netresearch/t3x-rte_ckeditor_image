/**
 * CKEditor 5 mocks for unit testing upcast/downcast converters.
 *
 * These mocks simulate the CKEditor conversion API to test converter logic
 * without requiring a full CKEditor instance.
 */

/**
 * Mock ViewElement - represents a DOM-like element in CKEditor's view tree
 */
export class MockViewElement {
  constructor(name, attributes = {}, children = []) {
    this._name = name;
    this._attributes = new Map(Object.entries(attributes));
    this._children = children;
    this._parent = null;

    // Set parent references for children
    children.forEach(child => {
      if (child instanceof MockViewElement) {
        child._parent = this;
      }
    });
  }

  get name() {
    return this._name;
  }

  get parent() {
    return this._parent;
  }

  is(type, name) {
    if (type === 'element') {
      return name ? this._name === name : true;
    }
    return false;
  }

  getAttribute(key) {
    return this._attributes.get(key) || null;
  }

  hasAttribute(key) {
    return this._attributes.has(key);
  }

  getChildren() {
    return this._children[Symbol.iterator]();
  }

  getChild(index) {
    return this._children[index];
  }

  childCount() {
    return this._children.length;
  }
}

/**
 * Mock ModelElement - represents an element in CKEditor's model tree
 */
export class MockModelElement {
  constructor(name, attributes = {}) {
    this._name = name;
    this._attributes = new Map(Object.entries(attributes));
  }

  get name() {
    return this._name;
  }

  getAttribute(key) {
    return this._attributes.get(key);
  }

  setAttribute(key, value) {
    this._attributes.set(key, value);
  }

  hasAttribute(key) {
    return this._attributes.has(key);
  }

  getAttributes() {
    return this._attributes.entries();
  }
}

/**
 * Mock Writer - creates model elements
 */
export class MockWriter {
  createElement(name, attributes = {}) {
    return new MockModelElement(name, attributes);
  }

  createContainerElement(name, attributes = {}, children = null) {
    const element = new MockViewElement(name, attributes, children ? [children] : []);
    return element;
  }

  createEmptyElement(name, attributes = {}) {
    return new MockViewElement(name, attributes, []);
  }
}

/**
 * Mock Consumable - tracks which elements have been consumed
 *
 * Includes strict API validation to catch incorrect usage patterns.
 * In CKEditor's real API, `attributes`, `classes`, and `styles` must be arrays
 * specifying which specific items to consume/test (e.g., ['href', 'class']).
 * Passing boolean values like `{ attributes: true }` is incorrect usage.
 */
export class MockConsumable {
  constructor() {
    this._consumed = new Set();
  }

  /**
   * Validates that options follow CKEditor's consumable API contract.
   * @param {Object} options - The options object to validate
   * @param {string} methodName - The method name for error messages
   * @throws {TypeError} If options violate the API contract
   */
  _validateOptions(options, methodName) {
    const arrayFields = ['attributes', 'classes', 'styles'];

    for (const field of arrayFields) {
      if (field in options && options[field] !== undefined) {
        if (!Array.isArray(options[field])) {
          throw new TypeError(
            `MockConsumable.${methodName}(): '${field}' option must be an array, ` +
            `got ${typeof options[field]} (${JSON.stringify(options[field])}). ` +
            `Use { ${field}: ['item1', 'item2'] } instead of { ${field}: true }.`
          );
        }
      }
    }
  }

  consume(element, options = {}) {
    this._validateOptions(options, 'consume');
    this._consumed.add(element);
    return true;
  }

  test(element, options = {}) {
    this._validateOptions(options, 'test');
    return !this._consumed.has(element);
  }

  isConsumed(element) {
    return this._consumed.has(element);
  }
}

/**
 * Mock ConversionApi - the API object passed to converter callbacks
 */
export class MockConversionApi {
  constructor() {
    this.writer = new MockWriter();
    this.consumable = new MockConsumable();
  }
}

/**
 * Helper to create a linked image view structure: <a href="..."><img .../></a>
 */
export function createLinkedImageView(linkAttrs, imgAttrs) {
  const img = new MockViewElement('img', imgAttrs);
  const anchor = new MockViewElement('a', linkAttrs, [img]);
  return { anchor, img };
}

/**
 * Helper to create a standalone image view: <img .../>
 */
export function createStandaloneImageView(imgAttrs) {
  return new MockViewElement('img', imgAttrs);
}

/**
 * Helper to create a figure with linked image
 */
export function createFigureWithLinkedImage(figureAttrs, linkAttrs, imgAttrs, captionText) {
  const img = new MockViewElement('img', imgAttrs);
  const anchor = new MockViewElement('a', linkAttrs, [img]);
  const figcaption = new MockViewElement('figcaption', {}, [captionText]);
  const figure = new MockViewElement('figure', figureAttrs, [anchor, figcaption]);
  return { figure, anchor, img, figcaption };
}
