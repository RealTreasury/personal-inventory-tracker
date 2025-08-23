/*! Personal Inventory Tracker Enhanced */
var PITOcr = (() => {
  var __create = Object.create;
  var __defProp = Object.defineProperty;
  var __getOwnPropDesc = Object.getOwnPropertyDescriptor;
  var __getOwnPropNames = Object.getOwnPropertyNames;
  var __getProtoOf = Object.getPrototypeOf;
  var __hasOwnProp = Object.prototype.hasOwnProperty;
  var __require = /* @__PURE__ */ ((x) => typeof require !== "undefined" ? require : typeof Proxy !== "undefined" ? new Proxy(x, {
    get: (a, b) => (typeof require !== "undefined" ? require : a)[b]
  }) : x)(function(x) {
    if (typeof require !== "undefined") return require.apply(this, arguments);
    throw Error('Dynamic require of "' + x + '" is not supported');
  });
  var __export = (target, all) => {
    for (var name in all)
      __defProp(target, name, { get: all[name], enumerable: true });
  };
  var __copyProps = (to, from, except, desc) => {
    if (from && typeof from === "object" || typeof from === "function") {
      for (let key of __getOwnPropNames(from))
        if (!__hasOwnProp.call(to, key) && key !== except)
          __defProp(to, key, { get: () => from[key], enumerable: !(desc = __getOwnPropDesc(from, key)) || desc.enumerable });
    }
    return to;
  };
  var __toESM = (mod, isNodeMode, target) => (target = mod != null ? __create(__getProtoOf(mod)) : {}, __copyProps(
    // If the importer is in node compatibility mode or this is not an ESM
    // file that has been converted to a CommonJS file using a Babel-
    // compatible transform (i.e. "__esModule" has not been set), then set
    // "default" to the CommonJS "module.exports" for node compatibility.
    isNodeMode || !mod || !mod.__esModule ? __defProp(target, "default", { value: mod, enumerable: true }) : target,
    mod
  ));
  var __toCommonJS = (mod) => __copyProps(__defProp({}, "__esModule", { value: true }), mod);

  // src/ocr.js
  var ocr_exports = {};
  __export(ocr_exports, {
    bindOcrToInput: () => bindOcrToInput,
    extractItemSuggestions: () => extractItemSuggestions
  });
  var tesseractPromise;
  function loadTesseract() {
    if (!tesseractPromise) {
      tesseractPromise = import("https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.esm.min.js");
    }
    return tesseractPromise;
  }
  async function extractItemSuggestions(image, minConfidence = 60) {
    if (typeof window === "undefined") {
      console.warn("OCR is only supported in browser environments.");
      return [];
    }
    const Tesseract = await loadTesseract();
    const { data } = await Tesseract.recognize(image, "eng");
    return data.lines.filter((line) => line.confidence >= minConfidence).map((line) => ({
      text: line.text.trim(),
      confidence: line.confidence
    }));
  }
  function bindOcrToInput(input, callback, minConfidence = 60) {
    if (typeof window === "undefined") {
      console.warn("OCR binding skipped: not in a browser");
      return;
    }
    input.addEventListener("change", async () => {
      const [file] = input.files;
      if (file) {
        const items = await extractItemSuggestions(file, minConfidence);
        callback(items);
      }
    });
  }
  return __toCommonJS(ocr_exports);
})();
