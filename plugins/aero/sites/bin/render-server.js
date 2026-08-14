"use strict";
Object.defineProperty(exports, Symbol.toStringTag, { value: "Module" });
const require$$0 = require("react");
const server = require("react-dom/server");
const core = require("@puckeditor/core");
var jsxRuntime = { exports: {} };
var reactJsxRuntime_production_min = {};
/**
 * @license React
 * react-jsx-runtime.production.min.js
 *
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
var hasRequiredReactJsxRuntime_production_min;
function requireReactJsxRuntime_production_min() {
  if (hasRequiredReactJsxRuntime_production_min) return reactJsxRuntime_production_min;
  hasRequiredReactJsxRuntime_production_min = 1;
  var f = require$$0, k = Symbol.for("react.element"), l = Symbol.for("react.fragment"), m = Object.prototype.hasOwnProperty, n = f.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED.ReactCurrentOwner, p = { key: true, ref: true, __self: true, __source: true };
  function q(c, a, g) {
    var b, d = {}, e = null, h = null;
    void 0 !== g && (e = "" + g);
    void 0 !== a.key && (e = "" + a.key);
    void 0 !== a.ref && (h = a.ref);
    for (b in a) m.call(a, b) && !p.hasOwnProperty(b) && (d[b] = a[b]);
    if (c && c.defaultProps) for (b in a = c.defaultProps, a) void 0 === d[b] && (d[b] = a[b]);
    return { $$typeof: k, type: c, key: e, ref: h, props: d, _owner: n.current };
  }
  reactJsxRuntime_production_min.Fragment = l;
  reactJsxRuntime_production_min.jsx = q;
  reactJsxRuntime_production_min.jsxs = q;
  return reactJsxRuntime_production_min;
}
var reactJsxRuntime_development = {};
/**
 * @license React
 * react-jsx-runtime.development.js
 *
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
var hasRequiredReactJsxRuntime_development;
function requireReactJsxRuntime_development() {
  if (hasRequiredReactJsxRuntime_development) return reactJsxRuntime_development;
  hasRequiredReactJsxRuntime_development = 1;
  if (process.env.NODE_ENV !== "production") {
    (function() {
      var React = require$$0;
      var REACT_ELEMENT_TYPE = Symbol.for("react.element");
      var REACT_PORTAL_TYPE = Symbol.for("react.portal");
      var REACT_FRAGMENT_TYPE = Symbol.for("react.fragment");
      var REACT_STRICT_MODE_TYPE = Symbol.for("react.strict_mode");
      var REACT_PROFILER_TYPE = Symbol.for("react.profiler");
      var REACT_PROVIDER_TYPE = Symbol.for("react.provider");
      var REACT_CONTEXT_TYPE = Symbol.for("react.context");
      var REACT_FORWARD_REF_TYPE = Symbol.for("react.forward_ref");
      var REACT_SUSPENSE_TYPE = Symbol.for("react.suspense");
      var REACT_SUSPENSE_LIST_TYPE = Symbol.for("react.suspense_list");
      var REACT_MEMO_TYPE = Symbol.for("react.memo");
      var REACT_LAZY_TYPE = Symbol.for("react.lazy");
      var REACT_OFFSCREEN_TYPE = Symbol.for("react.offscreen");
      var MAYBE_ITERATOR_SYMBOL = Symbol.iterator;
      var FAUX_ITERATOR_SYMBOL = "@@iterator";
      function getIteratorFn(maybeIterable) {
        if (maybeIterable === null || typeof maybeIterable !== "object") {
          return null;
        }
        var maybeIterator = MAYBE_ITERATOR_SYMBOL && maybeIterable[MAYBE_ITERATOR_SYMBOL] || maybeIterable[FAUX_ITERATOR_SYMBOL];
        if (typeof maybeIterator === "function") {
          return maybeIterator;
        }
        return null;
      }
      var ReactSharedInternals = React.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED;
      function error(format) {
        {
          {
            for (var _len2 = arguments.length, args = new Array(_len2 > 1 ? _len2 - 1 : 0), _key2 = 1; _key2 < _len2; _key2++) {
              args[_key2 - 1] = arguments[_key2];
            }
            printWarning("error", format, args);
          }
        }
      }
      function printWarning(level, format, args) {
        {
          var ReactDebugCurrentFrame2 = ReactSharedInternals.ReactDebugCurrentFrame;
          var stack = ReactDebugCurrentFrame2.getStackAddendum();
          if (stack !== "") {
            format += "%s";
            args = args.concat([stack]);
          }
          var argsWithFormat = args.map(function(item) {
            return String(item);
          });
          argsWithFormat.unshift("Warning: " + format);
          Function.prototype.apply.call(console[level], console, argsWithFormat);
        }
      }
      var enableScopeAPI = false;
      var enableCacheElement = false;
      var enableTransitionTracing = false;
      var enableLegacyHidden = false;
      var enableDebugTracing = false;
      var REACT_MODULE_REFERENCE;
      {
        REACT_MODULE_REFERENCE = Symbol.for("react.module.reference");
      }
      function isValidElementType(type) {
        if (typeof type === "string" || typeof type === "function") {
          return true;
        }
        if (type === REACT_FRAGMENT_TYPE || type === REACT_PROFILER_TYPE || enableDebugTracing || type === REACT_STRICT_MODE_TYPE || type === REACT_SUSPENSE_TYPE || type === REACT_SUSPENSE_LIST_TYPE || enableLegacyHidden || type === REACT_OFFSCREEN_TYPE || enableScopeAPI || enableCacheElement || enableTransitionTracing) {
          return true;
        }
        if (typeof type === "object" && type !== null) {
          if (type.$$typeof === REACT_LAZY_TYPE || type.$$typeof === REACT_MEMO_TYPE || type.$$typeof === REACT_PROVIDER_TYPE || type.$$typeof === REACT_CONTEXT_TYPE || type.$$typeof === REACT_FORWARD_REF_TYPE || // This needs to include all possible module reference object
          // types supported by any Flight configuration anywhere since
          // we don't know which Flight build this will end up being used
          // with.
          type.$$typeof === REACT_MODULE_REFERENCE || type.getModuleId !== void 0) {
            return true;
          }
        }
        return false;
      }
      function getWrappedName(outerType, innerType, wrapperName) {
        var displayName = outerType.displayName;
        if (displayName) {
          return displayName;
        }
        var functionName = innerType.displayName || innerType.name || "";
        return functionName !== "" ? wrapperName + "(" + functionName + ")" : wrapperName;
      }
      function getContextName(type) {
        return type.displayName || "Context";
      }
      function getComponentNameFromType(type) {
        if (type == null) {
          return null;
        }
        {
          if (typeof type.tag === "number") {
            error("Received an unexpected object in getComponentNameFromType(). This is likely a bug in React. Please file an issue.");
          }
        }
        if (typeof type === "function") {
          return type.displayName || type.name || null;
        }
        if (typeof type === "string") {
          return type;
        }
        switch (type) {
          case REACT_FRAGMENT_TYPE:
            return "Fragment";
          case REACT_PORTAL_TYPE:
            return "Portal";
          case REACT_PROFILER_TYPE:
            return "Profiler";
          case REACT_STRICT_MODE_TYPE:
            return "StrictMode";
          case REACT_SUSPENSE_TYPE:
            return "Suspense";
          case REACT_SUSPENSE_LIST_TYPE:
            return "SuspenseList";
        }
        if (typeof type === "object") {
          switch (type.$$typeof) {
            case REACT_CONTEXT_TYPE:
              var context = type;
              return getContextName(context) + ".Consumer";
            case REACT_PROVIDER_TYPE:
              var provider = type;
              return getContextName(provider._context) + ".Provider";
            case REACT_FORWARD_REF_TYPE:
              return getWrappedName(type, type.render, "ForwardRef");
            case REACT_MEMO_TYPE:
              var outerName = type.displayName || null;
              if (outerName !== null) {
                return outerName;
              }
              return getComponentNameFromType(type.type) || "Memo";
            case REACT_LAZY_TYPE: {
              var lazyComponent = type;
              var payload = lazyComponent._payload;
              var init = lazyComponent._init;
              try {
                return getComponentNameFromType(init(payload));
              } catch (x) {
                return null;
              }
            }
          }
        }
        return null;
      }
      var assign = Object.assign;
      var disabledDepth = 0;
      var prevLog;
      var prevInfo;
      var prevWarn;
      var prevError;
      var prevGroup;
      var prevGroupCollapsed;
      var prevGroupEnd;
      function disabledLog() {
      }
      disabledLog.__reactDisabledLog = true;
      function disableLogs() {
        {
          if (disabledDepth === 0) {
            prevLog = console.log;
            prevInfo = console.info;
            prevWarn = console.warn;
            prevError = console.error;
            prevGroup = console.group;
            prevGroupCollapsed = console.groupCollapsed;
            prevGroupEnd = console.groupEnd;
            var props = {
              configurable: true,
              enumerable: true,
              value: disabledLog,
              writable: true
            };
            Object.defineProperties(console, {
              info: props,
              log: props,
              warn: props,
              error: props,
              group: props,
              groupCollapsed: props,
              groupEnd: props
            });
          }
          disabledDepth++;
        }
      }
      function reenableLogs() {
        {
          disabledDepth--;
          if (disabledDepth === 0) {
            var props = {
              configurable: true,
              enumerable: true,
              writable: true
            };
            Object.defineProperties(console, {
              log: assign({}, props, {
                value: prevLog
              }),
              info: assign({}, props, {
                value: prevInfo
              }),
              warn: assign({}, props, {
                value: prevWarn
              }),
              error: assign({}, props, {
                value: prevError
              }),
              group: assign({}, props, {
                value: prevGroup
              }),
              groupCollapsed: assign({}, props, {
                value: prevGroupCollapsed
              }),
              groupEnd: assign({}, props, {
                value: prevGroupEnd
              })
            });
          }
          if (disabledDepth < 0) {
            error("disabledDepth fell below zero. This is a bug in React. Please file an issue.");
          }
        }
      }
      var ReactCurrentDispatcher = ReactSharedInternals.ReactCurrentDispatcher;
      var prefix;
      function describeBuiltInComponentFrame(name, source, ownerFn) {
        {
          if (prefix === void 0) {
            try {
              throw Error();
            } catch (x) {
              var match = x.stack.trim().match(/\n( *(at )?)/);
              prefix = match && match[1] || "";
            }
          }
          return "\n" + prefix + name;
        }
      }
      var reentry = false;
      var componentFrameCache;
      {
        var PossiblyWeakMap = typeof WeakMap === "function" ? WeakMap : Map;
        componentFrameCache = new PossiblyWeakMap();
      }
      function describeNativeComponentFrame(fn, construct) {
        if (!fn || reentry) {
          return "";
        }
        {
          var frame = componentFrameCache.get(fn);
          if (frame !== void 0) {
            return frame;
          }
        }
        var control;
        reentry = true;
        var previousPrepareStackTrace = Error.prepareStackTrace;
        Error.prepareStackTrace = void 0;
        var previousDispatcher;
        {
          previousDispatcher = ReactCurrentDispatcher.current;
          ReactCurrentDispatcher.current = null;
          disableLogs();
        }
        try {
          if (construct) {
            var Fake = function() {
              throw Error();
            };
            Object.defineProperty(Fake.prototype, "props", {
              set: function() {
                throw Error();
              }
            });
            if (typeof Reflect === "object" && Reflect.construct) {
              try {
                Reflect.construct(Fake, []);
              } catch (x) {
                control = x;
              }
              Reflect.construct(fn, [], Fake);
            } else {
              try {
                Fake.call();
              } catch (x) {
                control = x;
              }
              fn.call(Fake.prototype);
            }
          } else {
            try {
              throw Error();
            } catch (x) {
              control = x;
            }
            fn();
          }
        } catch (sample) {
          if (sample && control && typeof sample.stack === "string") {
            var sampleLines = sample.stack.split("\n");
            var controlLines = control.stack.split("\n");
            var s = sampleLines.length - 1;
            var c = controlLines.length - 1;
            while (s >= 1 && c >= 0 && sampleLines[s] !== controlLines[c]) {
              c--;
            }
            for (; s >= 1 && c >= 0; s--, c--) {
              if (sampleLines[s] !== controlLines[c]) {
                if (s !== 1 || c !== 1) {
                  do {
                    s--;
                    c--;
                    if (c < 0 || sampleLines[s] !== controlLines[c]) {
                      var _frame = "\n" + sampleLines[s].replace(" at new ", " at ");
                      if (fn.displayName && _frame.includes("<anonymous>")) {
                        _frame = _frame.replace("<anonymous>", fn.displayName);
                      }
                      {
                        if (typeof fn === "function") {
                          componentFrameCache.set(fn, _frame);
                        }
                      }
                      return _frame;
                    }
                  } while (s >= 1 && c >= 0);
                }
                break;
              }
            }
          }
        } finally {
          reentry = false;
          {
            ReactCurrentDispatcher.current = previousDispatcher;
            reenableLogs();
          }
          Error.prepareStackTrace = previousPrepareStackTrace;
        }
        var name = fn ? fn.displayName || fn.name : "";
        var syntheticFrame = name ? describeBuiltInComponentFrame(name) : "";
        {
          if (typeof fn === "function") {
            componentFrameCache.set(fn, syntheticFrame);
          }
        }
        return syntheticFrame;
      }
      function describeFunctionComponentFrame(fn, source, ownerFn) {
        {
          return describeNativeComponentFrame(fn, false);
        }
      }
      function shouldConstruct(Component) {
        var prototype = Component.prototype;
        return !!(prototype && prototype.isReactComponent);
      }
      function describeUnknownElementTypeFrameInDEV(type, source, ownerFn) {
        if (type == null) {
          return "";
        }
        if (typeof type === "function") {
          {
            return describeNativeComponentFrame(type, shouldConstruct(type));
          }
        }
        if (typeof type === "string") {
          return describeBuiltInComponentFrame(type);
        }
        switch (type) {
          case REACT_SUSPENSE_TYPE:
            return describeBuiltInComponentFrame("Suspense");
          case REACT_SUSPENSE_LIST_TYPE:
            return describeBuiltInComponentFrame("SuspenseList");
        }
        if (typeof type === "object") {
          switch (type.$$typeof) {
            case REACT_FORWARD_REF_TYPE:
              return describeFunctionComponentFrame(type.render);
            case REACT_MEMO_TYPE:
              return describeUnknownElementTypeFrameInDEV(type.type, source, ownerFn);
            case REACT_LAZY_TYPE: {
              var lazyComponent = type;
              var payload = lazyComponent._payload;
              var init = lazyComponent._init;
              try {
                return describeUnknownElementTypeFrameInDEV(init(payload), source, ownerFn);
              } catch (x) {
              }
            }
          }
        }
        return "";
      }
      var hasOwnProperty = Object.prototype.hasOwnProperty;
      var loggedTypeFailures = {};
      var ReactDebugCurrentFrame = ReactSharedInternals.ReactDebugCurrentFrame;
      function setCurrentlyValidatingElement(element) {
        {
          if (element) {
            var owner = element._owner;
            var stack = describeUnknownElementTypeFrameInDEV(element.type, element._source, owner ? owner.type : null);
            ReactDebugCurrentFrame.setExtraStackFrame(stack);
          } else {
            ReactDebugCurrentFrame.setExtraStackFrame(null);
          }
        }
      }
      function checkPropTypes(typeSpecs, values, location, componentName, element) {
        {
          var has = Function.call.bind(hasOwnProperty);
          for (var typeSpecName in typeSpecs) {
            if (has(typeSpecs, typeSpecName)) {
              var error$1 = void 0;
              try {
                if (typeof typeSpecs[typeSpecName] !== "function") {
                  var err = Error((componentName || "React class") + ": " + location + " type `" + typeSpecName + "` is invalid; it must be a function, usually from the `prop-types` package, but received `" + typeof typeSpecs[typeSpecName] + "`.This often happens because of typos such as `PropTypes.function` instead of `PropTypes.func`.");
                  err.name = "Invariant Violation";
                  throw err;
                }
                error$1 = typeSpecs[typeSpecName](values, typeSpecName, componentName, location, null, "SECRET_DO_NOT_PASS_THIS_OR_YOU_WILL_BE_FIRED");
              } catch (ex) {
                error$1 = ex;
              }
              if (error$1 && !(error$1 instanceof Error)) {
                setCurrentlyValidatingElement(element);
                error("%s: type specification of %s `%s` is invalid; the type checker function must return `null` or an `Error` but returned a %s. You may have forgotten to pass an argument to the type checker creator (arrayOf, instanceOf, objectOf, oneOf, oneOfType, and shape all require an argument).", componentName || "React class", location, typeSpecName, typeof error$1);
                setCurrentlyValidatingElement(null);
              }
              if (error$1 instanceof Error && !(error$1.message in loggedTypeFailures)) {
                loggedTypeFailures[error$1.message] = true;
                setCurrentlyValidatingElement(element);
                error("Failed %s type: %s", location, error$1.message);
                setCurrentlyValidatingElement(null);
              }
            }
          }
        }
      }
      var isArrayImpl = Array.isArray;
      function isArray(a) {
        return isArrayImpl(a);
      }
      function typeName(value) {
        {
          var hasToStringTag = typeof Symbol === "function" && Symbol.toStringTag;
          var type = hasToStringTag && value[Symbol.toStringTag] || value.constructor.name || "Object";
          return type;
        }
      }
      function willCoercionThrow(value) {
        {
          try {
            testStringCoercion(value);
            return false;
          } catch (e) {
            return true;
          }
        }
      }
      function testStringCoercion(value) {
        return "" + value;
      }
      function checkKeyStringCoercion(value) {
        {
          if (willCoercionThrow(value)) {
            error("The provided key is an unsupported type %s. This value must be coerced to a string before before using it here.", typeName(value));
            return testStringCoercion(value);
          }
        }
      }
      var ReactCurrentOwner = ReactSharedInternals.ReactCurrentOwner;
      var RESERVED_PROPS = {
        key: true,
        ref: true,
        __self: true,
        __source: true
      };
      var specialPropKeyWarningShown;
      var specialPropRefWarningShown;
      function hasValidRef(config2) {
        {
          if (hasOwnProperty.call(config2, "ref")) {
            var getter = Object.getOwnPropertyDescriptor(config2, "ref").get;
            if (getter && getter.isReactWarning) {
              return false;
            }
          }
        }
        return config2.ref !== void 0;
      }
      function hasValidKey(config2) {
        {
          if (hasOwnProperty.call(config2, "key")) {
            var getter = Object.getOwnPropertyDescriptor(config2, "key").get;
            if (getter && getter.isReactWarning) {
              return false;
            }
          }
        }
        return config2.key !== void 0;
      }
      function warnIfStringRefCannotBeAutoConverted(config2, self) {
        {
          if (typeof config2.ref === "string" && ReactCurrentOwner.current && self) ;
        }
      }
      function defineKeyPropWarningGetter(props, displayName) {
        {
          var warnAboutAccessingKey = function() {
            if (!specialPropKeyWarningShown) {
              specialPropKeyWarningShown = true;
              error("%s: `key` is not a prop. Trying to access it will result in `undefined` being returned. If you need to access the same value within the child component, you should pass it as a different prop. (https://reactjs.org/link/special-props)", displayName);
            }
          };
          warnAboutAccessingKey.isReactWarning = true;
          Object.defineProperty(props, "key", {
            get: warnAboutAccessingKey,
            configurable: true
          });
        }
      }
      function defineRefPropWarningGetter(props, displayName) {
        {
          var warnAboutAccessingRef = function() {
            if (!specialPropRefWarningShown) {
              specialPropRefWarningShown = true;
              error("%s: `ref` is not a prop. Trying to access it will result in `undefined` being returned. If you need to access the same value within the child component, you should pass it as a different prop. (https://reactjs.org/link/special-props)", displayName);
            }
          };
          warnAboutAccessingRef.isReactWarning = true;
          Object.defineProperty(props, "ref", {
            get: warnAboutAccessingRef,
            configurable: true
          });
        }
      }
      var ReactElement = function(type, key, ref, self, source, owner, props) {
        var element = {
          // This tag allows us to uniquely identify this as a React Element
          $$typeof: REACT_ELEMENT_TYPE,
          // Built-in properties that belong on the element
          type,
          key,
          ref,
          props,
          // Record the component responsible for creating this element.
          _owner: owner
        };
        {
          element._store = {};
          Object.defineProperty(element._store, "validated", {
            configurable: false,
            enumerable: false,
            writable: true,
            value: false
          });
          Object.defineProperty(element, "_self", {
            configurable: false,
            enumerable: false,
            writable: false,
            value: self
          });
          Object.defineProperty(element, "_source", {
            configurable: false,
            enumerable: false,
            writable: false,
            value: source
          });
          if (Object.freeze) {
            Object.freeze(element.props);
            Object.freeze(element);
          }
        }
        return element;
      };
      function jsxDEV(type, config2, maybeKey, source, self) {
        {
          var propName;
          var props = {};
          var key = null;
          var ref = null;
          if (maybeKey !== void 0) {
            {
              checkKeyStringCoercion(maybeKey);
            }
            key = "" + maybeKey;
          }
          if (hasValidKey(config2)) {
            {
              checkKeyStringCoercion(config2.key);
            }
            key = "" + config2.key;
          }
          if (hasValidRef(config2)) {
            ref = config2.ref;
            warnIfStringRefCannotBeAutoConverted(config2, self);
          }
          for (propName in config2) {
            if (hasOwnProperty.call(config2, propName) && !RESERVED_PROPS.hasOwnProperty(propName)) {
              props[propName] = config2[propName];
            }
          }
          if (type && type.defaultProps) {
            var defaultProps = type.defaultProps;
            for (propName in defaultProps) {
              if (props[propName] === void 0) {
                props[propName] = defaultProps[propName];
              }
            }
          }
          if (key || ref) {
            var displayName = typeof type === "function" ? type.displayName || type.name || "Unknown" : type;
            if (key) {
              defineKeyPropWarningGetter(props, displayName);
            }
            if (ref) {
              defineRefPropWarningGetter(props, displayName);
            }
          }
          return ReactElement(type, key, ref, self, source, ReactCurrentOwner.current, props);
        }
      }
      var ReactCurrentOwner$1 = ReactSharedInternals.ReactCurrentOwner;
      var ReactDebugCurrentFrame$1 = ReactSharedInternals.ReactDebugCurrentFrame;
      function setCurrentlyValidatingElement$1(element) {
        {
          if (element) {
            var owner = element._owner;
            var stack = describeUnknownElementTypeFrameInDEV(element.type, element._source, owner ? owner.type : null);
            ReactDebugCurrentFrame$1.setExtraStackFrame(stack);
          } else {
            ReactDebugCurrentFrame$1.setExtraStackFrame(null);
          }
        }
      }
      var propTypesMisspellWarningShown;
      {
        propTypesMisspellWarningShown = false;
      }
      function isValidElement(object) {
        {
          return typeof object === "object" && object !== null && object.$$typeof === REACT_ELEMENT_TYPE;
        }
      }
      function getDeclarationErrorAddendum() {
        {
          if (ReactCurrentOwner$1.current) {
            var name = getComponentNameFromType(ReactCurrentOwner$1.current.type);
            if (name) {
              return "\n\nCheck the render method of `" + name + "`.";
            }
          }
          return "";
        }
      }
      function getSourceInfoErrorAddendum(source) {
        {
          return "";
        }
      }
      var ownerHasKeyUseWarning = {};
      function getCurrentComponentErrorInfo(parentType) {
        {
          var info = getDeclarationErrorAddendum();
          if (!info) {
            var parentName = typeof parentType === "string" ? parentType : parentType.displayName || parentType.name;
            if (parentName) {
              info = "\n\nCheck the top-level render call using <" + parentName + ">.";
            }
          }
          return info;
        }
      }
      function validateExplicitKey(element, parentType) {
        {
          if (!element._store || element._store.validated || element.key != null) {
            return;
          }
          element._store.validated = true;
          var currentComponentErrorInfo = getCurrentComponentErrorInfo(parentType);
          if (ownerHasKeyUseWarning[currentComponentErrorInfo]) {
            return;
          }
          ownerHasKeyUseWarning[currentComponentErrorInfo] = true;
          var childOwner = "";
          if (element && element._owner && element._owner !== ReactCurrentOwner$1.current) {
            childOwner = " It was passed a child from " + getComponentNameFromType(element._owner.type) + ".";
          }
          setCurrentlyValidatingElement$1(element);
          error('Each child in a list should have a unique "key" prop.%s%s See https://reactjs.org/link/warning-keys for more information.', currentComponentErrorInfo, childOwner);
          setCurrentlyValidatingElement$1(null);
        }
      }
      function validateChildKeys(node, parentType) {
        {
          if (typeof node !== "object") {
            return;
          }
          if (isArray(node)) {
            for (var i = 0; i < node.length; i++) {
              var child = node[i];
              if (isValidElement(child)) {
                validateExplicitKey(child, parentType);
              }
            }
          } else if (isValidElement(node)) {
            if (node._store) {
              node._store.validated = true;
            }
          } else if (node) {
            var iteratorFn = getIteratorFn(node);
            if (typeof iteratorFn === "function") {
              if (iteratorFn !== node.entries) {
                var iterator = iteratorFn.call(node);
                var step;
                while (!(step = iterator.next()).done) {
                  if (isValidElement(step.value)) {
                    validateExplicitKey(step.value, parentType);
                  }
                }
              }
            }
          }
        }
      }
      function validatePropTypes(element) {
        {
          var type = element.type;
          if (type === null || type === void 0 || typeof type === "string") {
            return;
          }
          var propTypes;
          if (typeof type === "function") {
            propTypes = type.propTypes;
          } else if (typeof type === "object" && (type.$$typeof === REACT_FORWARD_REF_TYPE || // Note: Memo only checks outer props here.
          // Inner props are checked in the reconciler.
          type.$$typeof === REACT_MEMO_TYPE)) {
            propTypes = type.propTypes;
          } else {
            return;
          }
          if (propTypes) {
            var name = getComponentNameFromType(type);
            checkPropTypes(propTypes, element.props, "prop", name, element);
          } else if (type.PropTypes !== void 0 && !propTypesMisspellWarningShown) {
            propTypesMisspellWarningShown = true;
            var _name = getComponentNameFromType(type);
            error("Component %s declared `PropTypes` instead of `propTypes`. Did you misspell the property assignment?", _name || "Unknown");
          }
          if (typeof type.getDefaultProps === "function" && !type.getDefaultProps.isReactClassApproved) {
            error("getDefaultProps is only used on classic React.createClass definitions. Use a static property named `defaultProps` instead.");
          }
        }
      }
      function validateFragmentProps(fragment) {
        {
          var keys = Object.keys(fragment.props);
          for (var i = 0; i < keys.length; i++) {
            var key = keys[i];
            if (key !== "children" && key !== "key") {
              setCurrentlyValidatingElement$1(fragment);
              error("Invalid prop `%s` supplied to `React.Fragment`. React.Fragment can only have `key` and `children` props.", key);
              setCurrentlyValidatingElement$1(null);
              break;
            }
          }
          if (fragment.ref !== null) {
            setCurrentlyValidatingElement$1(fragment);
            error("Invalid attribute `ref` supplied to `React.Fragment`.");
            setCurrentlyValidatingElement$1(null);
          }
        }
      }
      var didWarnAboutKeySpread = {};
      function jsxWithValidation(type, props, key, isStaticChildren, source, self) {
        {
          var validType = isValidElementType(type);
          if (!validType) {
            var info = "";
            if (type === void 0 || typeof type === "object" && type !== null && Object.keys(type).length === 0) {
              info += " You likely forgot to export your component from the file it's defined in, or you might have mixed up default and named imports.";
            }
            var sourceInfo = getSourceInfoErrorAddendum();
            if (sourceInfo) {
              info += sourceInfo;
            } else {
              info += getDeclarationErrorAddendum();
            }
            var typeString;
            if (type === null) {
              typeString = "null";
            } else if (isArray(type)) {
              typeString = "array";
            } else if (type !== void 0 && type.$$typeof === REACT_ELEMENT_TYPE) {
              typeString = "<" + (getComponentNameFromType(type.type) || "Unknown") + " />";
              info = " Did you accidentally export a JSX literal instead of a component?";
            } else {
              typeString = typeof type;
            }
            error("React.jsx: type is invalid -- expected a string (for built-in components) or a class/function (for composite components) but got: %s.%s", typeString, info);
          }
          var element = jsxDEV(type, props, key, source, self);
          if (element == null) {
            return element;
          }
          if (validType) {
            var children = props.children;
            if (children !== void 0) {
              if (isStaticChildren) {
                if (isArray(children)) {
                  for (var i = 0; i < children.length; i++) {
                    validateChildKeys(children[i], type);
                  }
                  if (Object.freeze) {
                    Object.freeze(children);
                  }
                } else {
                  error("React.jsx: Static children should always be an array. You are likely explicitly calling React.jsxs or React.jsxDEV. Use the Babel transform instead.");
                }
              } else {
                validateChildKeys(children, type);
              }
            }
          }
          {
            if (hasOwnProperty.call(props, "key")) {
              var componentName = getComponentNameFromType(type);
              var keys = Object.keys(props).filter(function(k) {
                return k !== "key";
              });
              var beforeExample = keys.length > 0 ? "{key: someKey, " + keys.join(": ..., ") + ": ...}" : "{key: someKey}";
              if (!didWarnAboutKeySpread[componentName + beforeExample]) {
                var afterExample = keys.length > 0 ? "{" + keys.join(": ..., ") + ": ...}" : "{}";
                error('A props object containing a "key" prop is being spread into JSX:\n  let props = %s;\n  <%s {...props} />\nReact keys must be passed directly to JSX without using spread:\n  let props = %s;\n  <%s key={someKey} {...props} />', beforeExample, componentName, afterExample, componentName);
                didWarnAboutKeySpread[componentName + beforeExample] = true;
              }
            }
          }
          if (type === REACT_FRAGMENT_TYPE) {
            validateFragmentProps(element);
          } else {
            validatePropTypes(element);
          }
          return element;
        }
      }
      function jsxWithValidationStatic(type, props, key) {
        {
          return jsxWithValidation(type, props, key, true);
        }
      }
      function jsxWithValidationDynamic(type, props, key) {
        {
          return jsxWithValidation(type, props, key, false);
        }
      }
      var jsx = jsxWithValidationDynamic;
      var jsxs = jsxWithValidationStatic;
      reactJsxRuntime_development.Fragment = REACT_FRAGMENT_TYPE;
      reactJsxRuntime_development.jsx = jsx;
      reactJsxRuntime_development.jsxs = jsxs;
    })();
  }
  return reactJsxRuntime_development;
}
if (process.env.NODE_ENV === "production") {
  jsxRuntime.exports = requireReactJsxRuntime_production_min();
} else {
  jsxRuntime.exports = requireReactJsxRuntime_development();
}
var jsxRuntimeExports = jsxRuntime.exports;
const Hero = {
  label: "Hero",
  fields: {
    title: { type: "text", label: "Título principal" },
    subtitle: { type: "textarea", label: "Subtítulo" },
    ctaLabel: { type: "text", label: "Botón: texto" },
    ctaUrl: { type: "text", label: "Botón: URL" }
  },
  defaultProps: {
    title: "Bienvenido a nuestro sitio",
    subtitle: "Descubre todo lo que tenemos para ofrecerte.",
    ctaLabel: "Contáctanos",
    ctaUrl: "/contacto"
  },
  render: ({ title, subtitle, ctaLabel, ctaUrl }) => /* @__PURE__ */ jsxRuntimeExports.jsx("section", { className: "bg-brand-primary-dark text-white py-24 px-4 text-center", children: /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: "max-w-4xl mx-auto", children: [
    /* @__PURE__ */ jsxRuntimeExports.jsx("h1", { className: "font-heading text-4xl md:text-6xl font-bold mb-6 leading-tight", children: title }),
    /* @__PURE__ */ jsxRuntimeExports.jsx("p", { className: "text-xl md:text-2xl mb-10 opacity-90 leading-relaxed", children: subtitle }),
    ctaLabel && ctaUrl && /* @__PURE__ */ jsxRuntimeExports.jsx(
      "a",
      {
        href: ctaUrl,
        className: "inline-block bg-white text-brand-primary-dark font-semibold px-8 py-4 rounded-brand hover:opacity-90 transition-opacity",
        children: ctaLabel
      }
    )
  ] }) })
};
const TextBlock = {
  label: "Texto",
  fields: {
    heading: { type: "text", label: "Encabezado (opcional)" },
    content: { type: "textarea", label: "Contenido (HTML permitido)" },
    alignment: {
      type: "radio",
      label: "Alineación",
      options: [
        { label: "Izquierda", value: "text-left" },
        { label: "Centro", value: "text-center" }
      ]
    },
    bgWhite: {
      type: "radio",
      label: "Fondo",
      options: [
        { label: "Blanco", value: "white" },
        { label: "Gris claro", value: "gray" }
      ]
    }
  },
  defaultProps: {
    heading: "",
    content: '<p>Escribe tu contenido aquí. Puedes incluir HTML básico como <strong>negritas</strong>, <em>cursivas</em> y <a href="#">enlaces</a>.</p>',
    alignment: "text-left",
    bgWhite: "white"
  },
  render: ({ heading, content, alignment, bgWhite }) => /* @__PURE__ */ jsxRuntimeExports.jsx("section", { className: `py-14 px-4 ${bgWhite === "gray" ? "bg-brand-bg" : "bg-white"}`, children: /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: `max-w-4xl mx-auto ${alignment}`, children: [
    heading && /* @__PURE__ */ jsxRuntimeExports.jsx("h2", { className: "font-heading text-3xl font-bold mb-6 text-brand-text", children: heading }),
    /* @__PURE__ */ jsxRuntimeExports.jsx(
      "div",
      {
        className: "prose prose-lg max-w-none text-gray-700",
        dangerouslySetInnerHTML: { __html: content }
      }
    )
  ] }) })
};
const FeatureGrid = {
  label: "Características",
  fields: {
    title: { type: "text", label: "Título de sección (opcional)" },
    features: {
      type: "array",
      label: "Características",
      arrayFields: {
        icon: { type: "text", label: "Ícono (emoji)" },
        title: { type: "text", label: "Título" },
        description: { type: "textarea", label: "Descripción" }
      },
      getItemSummary: (item) => item.title || "Característica",
      defaultItemProps: {
        icon: "⭐",
        title: "Nueva característica",
        description: "Descripción del beneficio."
      }
    },
    columns: {
      type: "select",
      label: "Columnas",
      options: [
        { label: "2 columnas", value: "2" },
        { label: "3 columnas", value: "3" },
        { label: "4 columnas", value: "4" }
      ]
    }
  },
  defaultProps: {
    title: "",
    features: [
      { icon: "⭐", title: "Característica 1", description: "Descripción del primer beneficio." },
      { icon: "🚀", title: "Característica 2", description: "Descripción del segundo beneficio." },
      { icon: "💡", title: "Característica 3", description: "Descripción del tercer beneficio." }
    ],
    columns: "3"
  },
  render: ({ title, features, columns }) => {
    const colClass = { "2": "md:grid-cols-2", "3": "md:grid-cols-3", "4": "md:grid-cols-4" }[columns] || "md:grid-cols-3";
    return /* @__PURE__ */ jsxRuntimeExports.jsx("section", { className: "py-16 px-4 bg-gray-50", children: /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: "max-w-6xl mx-auto", children: [
      title && /* @__PURE__ */ jsxRuntimeExports.jsx("h2", { className: "text-3xl font-bold text-center mb-12 text-gray-900", children: title }),
      /* @__PURE__ */ jsxRuntimeExports.jsx("div", { className: `grid grid-cols-1 ${colClass} gap-8`, children: features.map((feature, i) => /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: "bg-white p-8 rounded-2xl shadow-sm text-center", children: [
        /* @__PURE__ */ jsxRuntimeExports.jsx("div", { className: "text-5xl mb-4", children: feature.icon }),
        /* @__PURE__ */ jsxRuntimeExports.jsx("h3", { className: "text-xl font-bold mb-3 text-gray-900", children: feature.title }),
        /* @__PURE__ */ jsxRuntimeExports.jsx("p", { className: "text-gray-600 leading-relaxed", children: feature.description })
      ] }, i)) })
    ] }) });
  }
};
const ImageBlock = {
  label: "Imagen",
  fields: {
    imageUrl: { type: "text", label: "URL de la imagen" },
    alt: { type: "text", label: "Texto alternativo (SEO)" },
    caption: { type: "text", label: "Pie de foto (opcional)" },
    size: {
      type: "radio",
      label: "Ancho",
      options: [
        { label: "Completo", value: "full" },
        { label: "Centrado", value: "centered" }
      ]
    }
  },
  defaultProps: {
    imageUrl: "https://placehold.co/1200x600/e2e8f0/94a3b8?text=Imagen",
    alt: "Imagen",
    caption: "",
    size: "full"
  },
  render: ({ imageUrl, alt, caption, size }) => /* @__PURE__ */ jsxRuntimeExports.jsx("div", { className: "py-8 px-4", children: /* @__PURE__ */ jsxRuntimeExports.jsxs("figure", { className: size === "centered" ? "max-w-3xl mx-auto" : "w-full", children: [
    /* @__PURE__ */ jsxRuntimeExports.jsx("img", { src: imageUrl, alt, className: "w-full rounded-xl object-cover" }),
    caption && /* @__PURE__ */ jsxRuntimeExports.jsx("figcaption", { className: "text-center text-gray-500 text-sm mt-3 italic", children: caption })
  ] }) })
};
const CTASection = {
  label: "Llamado a la acción",
  fields: {
    heading: { type: "text", label: "Título" },
    body: { type: "textarea", label: "Descripción" },
    buttonLabel: { type: "text", label: "Texto del botón" },
    buttonUrl: { type: "text", label: "URL del botón" },
    style: {
      type: "radio",
      label: "Estilo",
      options: [
        { label: "Sólido", value: "solid" },
        { label: "Contorno", value: "outline" }
      ]
    }
  },
  defaultProps: {
    heading: "¿Listo para comenzar?",
    body: "Contáctanos hoy y descubre cómo podemos ayudarte.",
    buttonLabel: "Comenzar ahora",
    buttonUrl: "/contacto",
    style: "solid"
  },
  render: ({ heading, body, buttonLabel, buttonUrl, style }) => {
    const solid = style !== "outline";
    const section = solid ? "bg-brand-primary text-white" : "bg-brand-bg text-brand-text border-2 border-brand-primary";
    const button = solid ? "bg-white text-brand-primary" : "bg-brand-primary text-white";
    return /* @__PURE__ */ jsxRuntimeExports.jsx("section", { className: `${section} py-20 px-4 text-center`, children: /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: "max-w-2xl mx-auto", children: [
      /* @__PURE__ */ jsxRuntimeExports.jsx("h2", { className: "font-heading text-3xl font-bold mb-4", children: heading }),
      /* @__PURE__ */ jsxRuntimeExports.jsx("p", { className: "text-lg mb-10 opacity-90 leading-relaxed", children: body }),
      buttonLabel && buttonUrl && /* @__PURE__ */ jsxRuntimeExports.jsx(
        "a",
        {
          href: buttonUrl,
          className: `inline-block font-semibold px-8 py-4 rounded-brand transition-opacity hover:opacity-90 ${button}`,
          children: buttonLabel
        }
      )
    ] }) });
  }
};
const Divider = {
  label: "Separador",
  fields: {
    height: {
      type: "select",
      label: "Altura",
      options: [
        { label: "Pequeño (16px)", value: "h-4" },
        { label: "Mediano (32px)", value: "h-8" },
        { label: "Grande (64px)", value: "h-16" },
        { label: "Extra grande (128px)", value: "h-32" }
      ]
    },
    showLine: {
      type: "radio",
      label: "Línea divisoria",
      options: [
        { label: "Sí", value: "yes" },
        { label: "No", value: "no" }
      ]
    }
  },
  defaultProps: {
    height: "h-8",
    showLine: "no"
  },
  render: ({ height, showLine }) => /* @__PURE__ */ jsxRuntimeExports.jsx("div", { className: `${height} flex items-center px-8`, children: showLine === "yes" && /* @__PURE__ */ jsxRuntimeExports.jsx("hr", { className: "w-full border-gray-200" }) })
};
const Banner = {
  label: "Banner (CTA)",
  fields: {
    title: { type: "text", label: "Título" },
    body: { type: "textarea", label: "Texto" },
    buttonLabel: { type: "text", label: "Texto del botón" },
    buttonUrl: { type: "text", label: "URL del botón" },
    align: {
      type: "radio",
      label: "Alineación",
      options: [
        { label: "Izquierda", value: "text-left" },
        { label: "Centro", value: "text-center" }
      ]
    }
  },
  defaultProps: {
    title: "Título del anuncio",
    body: "Describe la promoción o mensaje importante de forma breve.",
    buttonLabel: "Saber más",
    buttonUrl: "/contacto",
    align: "text-center"
  },
  render: ({ title, body, buttonLabel, buttonUrl, align }) => /* @__PURE__ */ jsxRuntimeExports.jsx("section", { className: "py-16 px-4 bg-gray-900 text-white", children: /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: `max-w-4xl mx-auto ${align}`, children: [
    /* @__PURE__ */ jsxRuntimeExports.jsx("h2", { className: "text-3xl font-bold mb-4", children: title }),
    /* @__PURE__ */ jsxRuntimeExports.jsx("p", { className: "text-lg mb-8 opacity-90 leading-relaxed", children: body }),
    buttonLabel && buttonUrl && /* @__PURE__ */ jsxRuntimeExports.jsx(
      "a",
      {
        href: buttonUrl,
        className: "inline-block bg-white text-gray-900 font-semibold px-8 py-4 rounded-full hover:bg-gray-100 transition-colors",
        children: buttonLabel
      }
    )
  ] }) })
};
const Badge = {
  label: "Badge",
  fields: {
    text: { type: "text", label: "Texto" },
    variant: {
      type: "select",
      label: "Color",
      options: [
        { label: "Marca", value: "brand" },
        { label: "Verde (éxito)", value: "green" },
        { label: "Rojo (alerta)", value: "red" },
        { label: "Gris (neutro)", value: "gray" }
      ]
    }
  },
  defaultProps: { text: "Nuevo", variant: "brand" },
  render: ({ text, variant }) => {
    const styles = {
      brand: "bg-brand-primary text-white",
      green: "bg-green-100 text-green-800",
      red: "bg-red-100 text-red-800",
      gray: "bg-gray-100 text-gray-800"
    };
    return /* @__PURE__ */ jsxRuntimeExports.jsx(
      "span",
      {
        className: `inline-flex items-center px-3 py-1 rounded-brand text-sm font-semibold ${styles[variant] || styles.brand}`,
        children: text
      }
    );
  }
};
const FAQ = {
  label: "FAQ (Acordeón)",
  fields: {
    title: { type: "text", label: "Título de sección (opcional)" },
    items: {
      type: "array",
      label: "Preguntas",
      arrayFields: {
        question: { type: "text", label: "Pregunta" },
        answer: { type: "textarea", label: "Respuesta (HTML permitido)" }
      },
      getItemSummary: (item) => item.question || "Pregunta",
      defaultItemProps: {
        question: "¿Nueva pregunta?",
        answer: "<p>Escribe la respuesta aquí.</p>"
      }
    }
  },
  defaultProps: {
    title: "Preguntas frecuentes",
    items: [
      { question: "¿Cómo funciona el servicio?", answer: "<p>Explicación breve de la respuesta.</p>" },
      { question: "¿Cuáles son los precios?", answer: "<p>Detalle de precios o planes.</p>" }
    ]
  },
  render: ({ title, items }) => /* @__PURE__ */ jsxRuntimeExports.jsx("section", { className: "py-16 px-4 bg-white", children: /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: "max-w-3xl mx-auto", children: [
    title && /* @__PURE__ */ jsxRuntimeExports.jsx("h2", { className: "text-3xl font-bold mb-10 text-gray-900 text-center", children: title }),
    /* @__PURE__ */ jsxRuntimeExports.jsx("div", { className: "space-y-3", children: items.map((item, i) => /* @__PURE__ */ jsxRuntimeExports.jsxs(
      "details",
      {
        className: "group bg-gray-50 rounded-xl border border-gray-200 px-6 py-4",
        children: [
          /* @__PURE__ */ jsxRuntimeExports.jsxs("summary", { className: "flex items-center justify-between cursor-pointer font-semibold text-gray-900 list-none", children: [
            /* @__PURE__ */ jsxRuntimeExports.jsx("span", { children: item.question }),
            /* @__PURE__ */ jsxRuntimeExports.jsx("span", { className: "text-gray-500 group-open:rotate-180 transition-transform", children: "▾" })
          ] }),
          /* @__PURE__ */ jsxRuntimeExports.jsx(
            "div",
            {
              className: "mt-3 text-gray-700 prose prose-sm max-w-none",
              dangerouslySetInnerHTML: { __html: item.answer }
            }
          )
        ]
      },
      i
    )) })
  ] }) })
};
const Tabs = {
  label: "Pestañas",
  fields: {
    tabs: {
      type: "array",
      label: "Pestañas",
      arrayFields: {
        label: { type: "text", label: "Etiqueta" },
        content: { type: "textarea", label: "Contenido (HTML permitido)" }
      },
      getItemSummary: (item) => item.label || "Pestaña",
      defaultItemProps: { label: "Pestaña", content: "<p>Contenido…</p>" }
    }
  },
  defaultProps: {
    tabs: [
      { label: "Descripción", content: "<p>Contenido de la primera pestaña.</p>" },
      { label: "Detalles", content: "<p>Contenido de la segunda pestaña.</p>" }
    ]
  },
  render: ({ tabs }) => /* @__PURE__ */ jsxRuntimeExports.jsx("section", { className: "py-16 px-4 bg-white", children: /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: "max-w-4xl mx-auto", children: [
    /* @__PURE__ */ jsxRuntimeExports.jsx("div", { className: "border-b border-gray-200 mb-6", children: /* @__PURE__ */ jsxRuntimeExports.jsx("div", { className: "flex flex-wrap gap-2", children: tabs.map((tab, i) => /* @__PURE__ */ jsxRuntimeExports.jsx(
      "a",
      {
        href: `#tab-${i}`,
        className: "px-4 py-2 text-sm font-semibold text-gray-600 border-b-2 border-transparent",
        children: tab.label
      },
      i
    )) }) }),
    tabs.map((tab, i) => /* @__PURE__ */ jsxRuntimeExports.jsx(
      "div",
      {
        id: `tab-${i}`,
        className: "text-gray-700 prose prose-lg max-w-none",
        dangerouslySetInnerHTML: { __html: tab.content }
      },
      i
    ))
  ] }) })
};
const Testimonials = {
  label: "Testimonios",
  fields: {
    title: { type: "text", label: "Título de sección (opcional)" },
    testimonials: {
      type: "array",
      label: "Testimonios",
      arrayFields: {
        quote: { type: "textarea", label: "Cita" },
        author: { type: "text", label: "Autor" },
        role: { type: "text", label: "Cargo / Empresa" }
      },
      getItemSummary: (item) => item.author || "Testimonio",
      defaultItemProps: {
        quote: "Excelente servicio, totalmente recomendado.",
        author: "Nombre",
        role: "Cliente"
      }
    }
  },
  defaultProps: {
    title: "Lo que dicen nuestros clientes",
    testimonials: [
      { quote: "Excelente servicio, totalmente recomendado.", author: "Ana G.", role: "Cliente" },
      { quote: "Muy profesionales y atentos al detalle.", author: "Carlos M.", role: "Empresario" }
    ]
  },
  render: ({ title, testimonials }) => /* @__PURE__ */ jsxRuntimeExports.jsx("section", { className: "py-16 px-4 bg-gray-50", children: /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: "max-w-6xl mx-auto", children: [
    title && /* @__PURE__ */ jsxRuntimeExports.jsx("h2", { className: "text-3xl font-bold text-center mb-12 text-gray-900", children: title }),
    /* @__PURE__ */ jsxRuntimeExports.jsx("div", { className: "grid grid-cols-1 md:grid-cols-2 gap-8", children: testimonials.map((t, i) => /* @__PURE__ */ jsxRuntimeExports.jsxs("blockquote", { className: "bg-white p-8 rounded-2xl shadow-sm", children: [
      /* @__PURE__ */ jsxRuntimeExports.jsxs("p", { className: "text-gray-700 text-lg leading-relaxed mb-6", children: [
        "“",
        t.quote,
        "”"
      ] }),
      /* @__PURE__ */ jsxRuntimeExports.jsxs("footer", { children: [
        /* @__PURE__ */ jsxRuntimeExports.jsx("div", { className: "font-bold text-gray-900", children: t.author }),
        /* @__PURE__ */ jsxRuntimeExports.jsx("div", { className: "text-gray-500 text-sm", children: t.role })
      ] })
    ] }, i)) })
  ] }) })
};
const Gallery = {
  label: "Galería",
  fields: {
    title: { type: "text", label: "Título de sección (opcional)" },
    images: {
      type: "array",
      label: "Imágenes",
      arrayFields: {
        url: { type: "text", label: "URL de la imagen" },
        alt: { type: "text", label: "Texto alternativo" }
      },
      getItemSummary: (item) => item.alt || "Imagen",
      defaultItemProps: {
        url: "https://placehold.co/600x400/e2e8f0/94a3b8?text=Imagen",
        alt: "Imagen"
      }
    }
  },
  defaultProps: {
    title: "Galería",
    images: [
      { url: "https://placehold.co/600x400/e2e8f0/94a3b8?text=1", alt: "Imagen 1" },
      { url: "https://placehold.co/600x400/e2e8f0/94a3b8?text=2", alt: "Imagen 2" },
      { url: "https://placehold.co/600x400/e2e8f0/94a3b8?text=3", alt: "Imagen 3" }
    ]
  },
  render: ({ title, images }) => /* @__PURE__ */ jsxRuntimeExports.jsx("section", { className: "py-16 px-4 bg-white", children: /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: "max-w-6xl mx-auto", children: [
    title && /* @__PURE__ */ jsxRuntimeExports.jsx("h2", { className: "text-3xl font-bold text-center mb-12 text-gray-900", children: title }),
    /* @__PURE__ */ jsxRuntimeExports.jsx("div", { className: "grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4", children: images.map((img, i) => /* @__PURE__ */ jsxRuntimeExports.jsx("img", { src: img.url, alt: img.alt, className: "w-full rounded-xl object-cover" }, i)) })
  ] }) })
};
const Video = {
  label: "Video",
  fields: {
    url: { type: "text", label: "URL de YouTube o Vimeo" },
    caption: { type: "text", label: "Pie (opcional)" }
  },
  defaultProps: { url: "", caption: "" },
  render: ({ url, caption }) => {
    const embed = (() => {
      if (!url) return null;
      const yt = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([A-Za-z0-9_-]{6,})/);
      if (yt) return `https://www.youtube.com/embed/${yt[1]}`;
      const vm = url.match(/vimeo\.com\/(\d+)/);
      if (vm) return `https://player.vimeo.com/video/${vm[1]}`;
      return url;
    })();
    return /* @__PURE__ */ jsxRuntimeExports.jsx("section", { className: "py-16 px-4 bg-white", children: /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: "max-w-4xl mx-auto", children: [
      embed ? /* @__PURE__ */ jsxRuntimeExports.jsx("div", { className: "rounded-2xl overflow-hidden", children: /* @__PURE__ */ jsxRuntimeExports.jsx("div", { className: "w-full aspect-video", children: /* @__PURE__ */ jsxRuntimeExports.jsx(
        "iframe",
        {
          src: embed,
          title: caption || "Video",
          className: "w-full h-full",
          frameBorder: "0",
          allow: "accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture",
          allowFullScreen: true
        }
      ) }) }) : /* @__PURE__ */ jsxRuntimeExports.jsx("div", { className: "w-full rounded-2xl bg-gray-100 text-gray-500 text-center py-24", children: "Añade la URL de un video de YouTube o Vimeo" }),
      caption && /* @__PURE__ */ jsxRuntimeExports.jsx("p", { className: "text-center text-gray-500 text-sm mt-3 italic", children: caption })
    ] }) });
  }
};
const LogoCloud = {
  label: "Logos (Marquee)",
  fields: {
    title: { type: "text", label: "Título de sección (opcional)" },
    logos: {
      type: "array",
      label: "Logos",
      arrayFields: {
        url: { type: "text", label: "URL del logo" },
        alt: { type: "text", label: "Nombre" }
      },
      getItemSummary: (item) => item.alt || "Logo",
      defaultItemProps: {
        url: "https://placehold.co/160x60/e2e8f0/94a3b8?text=Logo",
        alt: "Logo"
      }
    }
  },
  defaultProps: {
    title: "Confían en nosotros",
    logos: [
      { url: "https://placehold.co/160x60/e2e8f0/94a3b8?text=A", alt: "Marca A" },
      { url: "https://placehold.co/160x60/e2e8f0/94a3b8?text=B", alt: "Marca B" },
      { url: "https://placehold.co/160x60/e2e8f0/94a3b8?text=C", alt: "Marca C" },
      { url: "https://placehold.co/160x60/e2e8f0/94a3b8?text=D", alt: "Marca D" }
    ]
  },
  render: ({ title, logos }) => /* @__PURE__ */ jsxRuntimeExports.jsx("section", { className: "py-16 px-4 bg-gray-50", children: /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: "max-w-6xl mx-auto", children: [
    title && /* @__PURE__ */ jsxRuntimeExports.jsx("h2", { className: "text-2xl font-bold text-center mb-10 text-gray-900", children: title }),
    /* @__PURE__ */ jsxRuntimeExports.jsx("div", { className: "flex flex-wrap items-center justify-center gap-8", children: logos.map((logo, i) => /* @__PURE__ */ jsxRuntimeExports.jsx("img", { src: logo.url, alt: logo.alt, className: "h-12 w-auto opacity-75" }, i)) })
  ] }) })
};
const Stats = {
  label: "Estadísticas",
  fields: {
    title: { type: "text", label: "Título de sección (opcional)" },
    stats: {
      type: "array",
      label: "Estadísticas",
      arrayFields: {
        value: { type: "text", label: "Valor (ej. +500)" },
        label: { type: "text", label: "Etiqueta" }
      },
      getItemSummary: (item) => item.label || "Estadística",
      defaultItemProps: { value: "100+", label: "Clientes" }
    }
  },
  defaultProps: {
    title: "",
    stats: [
      { value: "+500", label: "Clientes" },
      { value: "10", label: "Años de experiencia" },
      { value: "24/7", label: "Soporte" }
    ]
  },
  render: ({ title, stats }) => /* @__PURE__ */ jsxRuntimeExports.jsx("section", { className: "py-16 px-4 bg-gray-900 text-white", children: /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: "max-w-6xl mx-auto", children: [
    title && /* @__PURE__ */ jsxRuntimeExports.jsx("h2", { className: "text-3xl font-bold text-center mb-12", children: title }),
    /* @__PURE__ */ jsxRuntimeExports.jsx("div", { className: "grid grid-cols-1 sm:grid-cols-3 gap-8 text-center", children: stats.map((s, i) => /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { children: [
      /* @__PURE__ */ jsxRuntimeExports.jsx("div", { className: "text-5xl font-bold mb-2", children: s.value }),
      /* @__PURE__ */ jsxRuntimeExports.jsx("div", { className: "text-lg opacity-90", children: s.label })
    ] }, i)) })
  ] }) })
};
const Rating = {
  label: "Valoración",
  fields: {
    score: {
      type: "select",
      label: "Estrellas",
      options: [
        { label: "1", value: "1" },
        { label: "2", value: "2" },
        { label: "3", value: "3" },
        { label: "4", value: "4" },
        { label: "5", value: "5" }
      ]
    },
    text: { type: "text", label: "Texto (opcional)" }
  },
  defaultProps: { score: "5", text: "" },
  render: ({ score, text }) => {
    const n = parseInt(score, 10) || 5;
    return /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: "py-8 px-4 text-center", children: [
      /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: "text-3xl mb-2", children: [
        "★".repeat(n),
        /* @__PURE__ */ jsxRuntimeExports.jsx("span", { className: "text-gray-300", children: "★".repeat(Math.max(0, 5 - n)) })
      ] }),
      text && /* @__PURE__ */ jsxRuntimeExports.jsx("p", { className: "text-gray-600", children: text })
    ] });
  }
};
const components = {
  Hero,
  TextBlock,
  FeatureGrid,
  ImageBlock,
  CTASection,
  Divider,
  Banner,
  Badge,
  FAQ,
  Tabs,
  Testimonials,
  Gallery,
  Video,
  LogoCloud,
  Stats,
  Rating
};
const categories = {
  sections: {
    title: "Secciones",
    components: ["Hero", "CTASection", "Banner", "FeatureGrid", "Stats", "Divider"]
  },
  content: {
    title: "Contenido",
    components: ["TextBlock", "ImageBlock", "Video", "Gallery", "Badge"]
  },
  social: {
    title: "Prueba social",
    components: ["Testimonials", "LogoCloud", "Rating"]
  },
  interactive: {
    title: "Interactivo",
    components: ["FAQ", "Tabs"]
  }
};
const config = { components, categories };
function renderPuckData(jsonString) {
  const data = JSON.parse(jsonString || '{"content":[],"root":{"props":{}}}');
  return server.renderToStaticMarkup(require$$0.createElement(core.Render, { config, data }));
}
exports.renderPuckData = renderPuckData;
