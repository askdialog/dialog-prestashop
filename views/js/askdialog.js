/*
 * ATTENTION: The "eval" devtool has been used (maybe by default in mode: "development").
 * This devtool is neither made for production nor for readable output files.
 * It uses "eval()" calls to create a separate source file in the browser devtools.
 * If you are trying to read the output file, select a different devtool (https://webpack.js.org/configuration/devtool/)
 * or disable the default devtool with "devtool: false".
 * If you are looking for production-ready output files, see mode: "production" (https://webpack.js.org/configuration/mode/).
 */
/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./js/askdialog.js":
/*!*************************!*\
  !*** ./js/askdialog.js ***!
  \*************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

eval("__webpack_require__.r(__webpack_exports__);\n/* harmony import */ var jquery__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! jquery */ \"jquery\");\n/* harmony import */ var jquery__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(jquery__WEBPACK_IMPORTED_MODULE_0__);\n/* harmony import */ var prestashop__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! prestashop */ \"prestashop\");\n/* harmony import */ var prestashop__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(prestashop__WEBPACK_IMPORTED_MODULE_1__);\n\n\nfunction askDialogAddToCart(id_product, id_product_attribute, quantity) {\n  jquery__WEBPACK_IMPORTED_MODULE_0___default().ajax({\n    type: \"POST\",\n    headers: { \"cache-control\": \"no-cache\" },\n    url: (prestashop__WEBPACK_IMPORTED_MODULE_1___default().urls).pages.cart,\n    data: {\n      \"controller\": \"cart\",\n      \"add\": 1,\n      \"ajax\": true,\n      \"qty\": quantity,\n      \"id_product\": id_product,\n      \"token\": (prestashop__WEBPACK_IMPORTED_MODULE_1___default().static_token),\n      \"id_product_attribute\": id_product_attribute\n    },\n    success: function(data) {\n      prestashop__WEBPACK_IMPORTED_MODULE_1___default().emit(\"updateCart\", {\n        reason: {\n          idProduct: id_product,\n          idProductAttribute: id_product_attribute,\n          quantity,\n          idCustomization: 0,\n          token: (prestashop__WEBPACK_IMPORTED_MODULE_1___default().static_token),\n          action: \"add-to-cart\"\n        },\n        resp: data\n      });\n    },\n    error: function(jqXHR, textStatus, errorThrown) {\n      console.log(\"Error: \" + textStatus + \" \" + errorThrown);\n    }\n  });\n}\nfunction askDialogAddCurrentProductToCart() {\n  var id_product = jquery__WEBPACK_IMPORTED_MODULE_0___default()(\"#product_page_product_id\").val();\n  var id_product_attribute = jquery__WEBPACK_IMPORTED_MODULE_0___default()(\"#idCombination\").val();\n  var quantity = jquery__WEBPACK_IMPORTED_MODULE_0___default()(\"#quantity_wanted\").val();\n  var action = \"update\";\n  askDialogAddToCart(id_product, id_product_attribute, quantity);\n}\nwindow.askDialogAddToCart = askDialogAddToCart;\nwindow.askDialogAddToCart = askDialogAddCurrentProductToCart;\n\n\n//# sourceURL=webpack://askdialog_module/./js/askdialog.js?");

/***/ }),

/***/ "jquery":
/*!*************************!*\
  !*** external "jQuery" ***!
  \*************************/
/***/ ((module) => {

module.exports = jQuery;

/***/ }),

/***/ "prestashop":
/*!*****************************!*\
  !*** external "prestashop" ***!
  \*****************************/
/***/ ((module) => {

module.exports = prestashop;

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module can't be inlined because the eval devtool is used.
/******/ 	var __webpack_exports__ = __webpack_require__("./js/askdialog.js");
/******/ 	
/******/ })()
;