"use strict";
(() => {
  var En = Object.create;
  var Lt = Object.defineProperty;
  var Tn = Object.getOwnPropertyDescriptor;
  var An = Object.getOwnPropertyNames;
  var wn = Object.getPrototypeOf,
    Sn = Object.prototype.hasOwnProperty;
  var Ln = (n, s) => () => (
    s || n((s = { exports: {} }).exports, s), s.exports
  );
  var Cn = (n, s, m, g) => {
    if ((s && typeof s == "object") || typeof s == "function")
      for (let u of An(s))
        !Sn.call(n, u) &&
          u !== m &&
          Lt(n, u, {
            get: () => s[u],
            enumerable: !(g = Tn(s, u)) || g.enumerable,
          });
    return n;
  };
  var yn = (n, s, m) => (
    (m = n != null ? En(wn(n)) : {}),
    Cn(
      s || !n || !n.__esModule
        ? Lt(m, "default", { value: n, enumerable: !0 })
        : m,
      n
    )
  );
  var Ct = Ln((ke, Pe) => {
    (function (n, s) {
      typeof ke == "object" && typeof Pe < "u"
        ? (Pe.exports = s())
        : typeof define == "function" && define.amd
        ? define(s)
        : ((n = typeof globalThis < "u" ? globalThis : n || self),
          (n.DOMPurify = s()));
    })(ke, function () {
      "use strict";
      let {
          entries: n,
          setPrototypeOf: s,
          isFrozen: m,
          getPrototypeOf: g,
          getOwnPropertyDescriptor: u,
        } = Object,
        { freeze: r, seal: _, create: N } = Object,
        { apply: U, construct: Y } = typeof Reflect < "u" && Reflect;
      r ||
        (r = function (i) {
          return i;
        }),
        _ ||
          (_ = function (i) {
            return i;
          }),
        U ||
          (U = function (i, p, f) {
            return i.apply(p, f);
          }),
        Y ||
          (Y = function (i, p) {
            return new i(...p);
          });
      let ie = b(Array.prototype.forEach),
        Ue = b(Array.prototype.pop),
        $ = b(Array.prototype.push),
        ae = b(String.prototype.toLowerCase),
        me = b(String.prototype.toString),
        He = b(String.prototype.match),
        X = b(String.prototype.replace),
        Dt = b(String.prototype.indexOf),
        vt = b(String.prototype.trim),
        O = b(Object.prototype.hasOwnProperty),
        y = b(RegExp.prototype.test),
        K = Nt(TypeError);
      function b(d) {
        return function (i) {
          for (
            var p = arguments.length, f = new Array(p > 1 ? p - 1 : 0), w = 1;
            w < p;
            w++
          )
            f[w - 1] = arguments[w];
          return U(d, i, f);
        };
      }
      function Nt(d) {
        return function () {
          for (var i = arguments.length, p = new Array(i), f = 0; f < i; f++)
            p[f] = arguments[f];
          return Y(d, p);
        };
      }
      function c(d, i) {
        let p =
          arguments.length > 2 && arguments[2] !== void 0 ? arguments[2] : ae;
        s && s(d, null);
        let f = i.length;
        for (; f--; ) {
          let w = i[f];
          if (typeof w == "string") {
            let k = p(w);
            k !== w && (m(i) || (i[f] = k), (w = k));
          }
          d[w] = !0;
        }
        return d;
      }
      function kt(d) {
        for (let i = 0; i < d.length; i++) O(d, i) || (d[i] = null);
        return d;
      }
      function H(d) {
        let i = N(null);
        for (let [p, f] of n(d))
          O(d, p) &&
            (Array.isArray(f)
              ? (i[p] = kt(f))
              : f && typeof f == "object" && f.constructor === Object
              ? (i[p] = H(f))
              : (i[p] = f));
        return i;
      }
      function se(d, i) {
        for (; d !== null; ) {
          let f = u(d, i);
          if (f) {
            if (f.get) return b(f.get);
            if (typeof f.value == "function") return b(f.value);
          }
          d = g(d);
        }
        function p() {
          return null;
        }
        return p;
      }
      let Fe = r([
          "a",
          "abbr",
          "acronym",
          "address",
          "area",
          "article",
          "aside",
          "audio",
          "b",
          "bdi",
          "bdo",
          "big",
          "blink",
          "blockquote",
          "body",
          "br",
          "button",
          "canvas",
          "caption",
          "center",
          "cite",
          "code",
          "col",
          "colgroup",
          "content",
          "data",
          "datalist",
          "dd",
          "decorator",
          "del",
          "details",
          "dfn",
          "dialog",
          "dir",
          "div",
          "dl",
          "dt",
          "element",
          "em",
          "fieldset",
          "figcaption",
          "figure",
          "font",
          "footer",
          "form",
          "h1",
          "h2",
          "h3",
          "h4",
          "h5",
          "h6",
          "head",
          "header",
          "hgroup",
          "hr",
          "html",
          "i",
          "img",
          "input",
          "ins",
          "kbd",
          "label",
          "legend",
          "li",
          "main",
          "map",
          "mark",
          "marquee",
          "menu",
          "menuitem",
          "meter",
          "nav",
          "nobr",
          "ol",
          "optgroup",
          "option",
          "output",
          "p",
          "picture",
          "pre",
          "progress",
          "q",
          "rp",
          "rt",
          "ruby",
          "s",
          "samp",
          "section",
          "select",
          "shadow",
          "small",
          "source",
          "spacer",
          "span",
          "strike",
          "strong",
          "style",
          "sub",
          "summary",
          "sup",
          "table",
          "tbody",
          "td",
          "template",
          "textarea",
          "tfoot",
          "th",
          "thead",
          "time",
          "tr",
          "track",
          "tt",
          "u",
          "ul",
          "var",
          "video",
          "wbr",
        ]),
        he = r([
          "svg",
          "a",
          "altglyph",
          "altglyphdef",
          "altglyphitem",
          "animatecolor",
          "animatemotion",
          "animatetransform",
          "circle",
          "clippath",
          "defs",
          "desc",
          "ellipse",
          "filter",
          "font",
          "g",
          "glyph",
          "glyphref",
          "hkern",
          "image",
          "line",
          "lineargradient",
          "marker",
          "mask",
          "metadata",
          "mpath",
          "path",
          "pattern",
          "polygon",
          "polyline",
          "radialgradient",
          "rect",
          "stop",
          "style",
          "switch",
          "symbol",
          "text",
          "textpath",
          "title",
          "tref",
          "tspan",
          "view",
          "vkern",
        ]),
        ge = r([
          "feBlend",
          "feColorMatrix",
          "feComponentTransfer",
          "feComposite",
          "feConvolveMatrix",
          "feDiffuseLighting",
          "feDisplacementMap",
          "feDistantLight",
          "feDropShadow",
          "feFlood",
          "feFuncA",
          "feFuncB",
          "feFuncG",
          "feFuncR",
          "feGaussianBlur",
          "feImage",
          "feMerge",
          "feMergeNode",
          "feMorphology",
          "feOffset",
          "fePointLight",
          "feSpecularLighting",
          "feSpotLight",
          "feTile",
          "feTurbulence",
        ]),
        Pt = r([
          "animate",
          "color-profile",
          "cursor",
          "discard",
          "font-face",
          "font-face-format",
          "font-face-name",
          "font-face-src",
          "font-face-uri",
          "foreignobject",
          "hatch",
          "hatchpath",
          "mesh",
          "meshgradient",
          "meshpatch",
          "meshrow",
          "missing-glyph",
          "script",
          "set",
          "solidcolor",
          "unknown",
          "use",
        ]),
        _e = r([
          "math",
          "menclose",
          "merror",
          "mfenced",
          "mfrac",
          "mglyph",
          "mi",
          "mlabeledtr",
          "mmultiscripts",
          "mn",
          "mo",
          "mover",
          "mpadded",
          "mphantom",
          "mroot",
          "mrow",
          "ms",
          "mspace",
          "msqrt",
          "mstyle",
          "msub",
          "msup",
          "msubsup",
          "mtable",
          "mtd",
          "mtext",
          "mtr",
          "munder",
          "munderover",
          "mprescripts",
        ]),
        xt = r([
          "maction",
          "maligngroup",
          "malignmark",
          "mlongdiv",
          "mscarries",
          "mscarry",
          "msgroup",
          "mstack",
          "msline",
          "msrow",
          "semantics",
          "annotation",
          "annotation-xml",
          "mprescripts",
          "none",
        ]),
        Be = r(["#text"]),
        ze = r([
          "accept",
          "action",
          "align",
          "alt",
          "autocapitalize",
          "autocomplete",
          "autopictureinpicture",
          "autoplay",
          "background",
          "bgcolor",
          "border",
          "capture",
          "cellpadding",
          "cellspacing",
          "checked",
          "cite",
          "class",
          "clear",
          "color",
          "cols",
          "colspan",
          "controls",
          "controlslist",
          "coords",
          "crossorigin",
          "datetime",
          "decoding",
          "default",
          "dir",
          "disabled",
          "disablepictureinpicture",
          "disableremoteplayback",
          "download",
          "draggable",
          "enctype",
          "enterkeyhint",
          "face",
          "for",
          "headers",
          "height",
          "hidden",
          "high",
          "href",
          "hreflang",
          "id",
          "inputmode",
          "integrity",
          "ismap",
          "kind",
          "label",
          "lang",
          "list",
          "loading",
          "loop",
          "low",
          "max",
          "maxlength",
          "media",
          "method",
          "min",
          "minlength",
          "multiple",
          "muted",
          "name",
          "nonce",
          "noshade",
          "novalidate",
          "nowrap",
          "open",
          "optimum",
          "pattern",
          "placeholder",
          "playsinline",
          "poster",
          "preload",
          "pubdate",
          "radiogroup",
          "readonly",
          "rel",
          "required",
          "rev",
          "reversed",
          "role",
          "rows",
          "rowspan",
          "spellcheck",
          "scope",
          "selected",
          "shape",
          "size",
          "sizes",
          "span",
          "srclang",
          "start",
          "src",
          "srcset",
          "step",
          "style",
          "summary",
          "tabindex",
          "title",
          "translate",
          "type",
          "usemap",
          "valign",
          "value",
          "width",
          "wrap",
          "xmlns",
          "slot",
        ]),
        Ee = r([
          "accent-height",
          "accumulate",
          "additive",
          "alignment-baseline",
          "ascent",
          "attributename",
          "attributetype",
          "azimuth",
          "basefrequency",
          "baseline-shift",
          "begin",
          "bias",
          "by",
          "class",
          "clip",
          "clippathunits",
          "clip-path",
          "clip-rule",
          "color",
          "color-interpolation",
          "color-interpolation-filters",
          "color-profile",
          "color-rendering",
          "cx",
          "cy",
          "d",
          "dx",
          "dy",
          "diffuseconstant",
          "direction",
          "display",
          "divisor",
          "dur",
          "edgemode",
          "elevation",
          "end",
          "fill",
          "fill-opacity",
          "fill-rule",
          "filter",
          "filterunits",
          "flood-color",
          "flood-opacity",
          "font-family",
          "font-size",
          "font-size-adjust",
          "font-stretch",
          "font-style",
          "font-variant",
          "font-weight",
          "fx",
          "fy",
          "g1",
          "g2",
          "glyph-name",
          "glyphref",
          "gradientunits",
          "gradienttransform",
          "height",
          "href",
          "id",
          "image-rendering",
          "in",
          "in2",
          "k",
          "k1",
          "k2",
          "k3",
          "k4",
          "kerning",
          "keypoints",
          "keysplines",
          "keytimes",
          "lang",
          "lengthadjust",
          "letter-spacing",
          "kernelmatrix",
          "kernelunitlength",
          "lighting-color",
          "local",
          "marker-end",
          "marker-mid",
          "marker-start",
          "markerheight",
          "markerunits",
          "markerwidth",
          "maskcontentunits",
          "maskunits",
          "max",
          "mask",
          "media",
          "method",
          "mode",
          "min",
          "name",
          "numoctaves",
          "offset",
          "operator",
          "opacity",
          "order",
          "orient",
          "orientation",
          "origin",
          "overflow",
          "paint-order",
          "path",
          "pathlength",
          "patterncontentunits",
          "patterntransform",
          "patternunits",
          "points",
          "preservealpha",
          "preserveaspectratio",
          "primitiveunits",
          "r",
          "rx",
          "ry",
          "radius",
          "refx",
          "refy",
          "repeatcount",
          "repeatdur",
          "restart",
          "result",
          "rotate",
          "scale",
          "seed",
          "shape-rendering",
          "specularconstant",
          "specularexponent",
          "spreadmethod",
          "startoffset",
          "stddeviation",
          "stitchtiles",
          "stop-color",
          "stop-opacity",
          "stroke-dasharray",
          "stroke-dashoffset",
          "stroke-linecap",
          "stroke-linejoin",
          "stroke-miterlimit",
          "stroke-opacity",
          "stroke",
          "stroke-width",
          "style",
          "surfacescale",
          "systemlanguage",
          "tabindex",
          "targetx",
          "targety",
          "transform",
          "transform-origin",
          "text-anchor",
          "text-decoration",
          "text-rendering",
          "textlength",
          "type",
          "u1",
          "u2",
          "unicode",
          "values",
          "viewbox",
          "visibility",
          "version",
          "vert-adv-y",
          "vert-origin-x",
          "vert-origin-y",
          "width",
          "word-spacing",
          "wrap",
          "writing-mode",
          "xchannelselector",
          "ychannelselector",
          "x",
          "x1",
          "x2",
          "xmlns",
          "y",
          "y1",
          "y2",
          "z",
          "zoomandpan",
        ]),
        Ge = r([
          "accent",
          "accentunder",
          "align",
          "bevelled",
          "close",
          "columnsalign",
          "columnlines",
          "columnspan",
          "denomalign",
          "depth",
          "dir",
          "display",
          "displaystyle",
          "encoding",
          "fence",
          "frame",
          "height",
          "href",
          "id",
          "largeop",
          "length",
          "linethickness",
          "lspace",
          "lquote",
          "mathbackground",
          "mathcolor",
          "mathsize",
          "mathvariant",
          "maxsize",
          "minsize",
          "movablelimits",
          "notation",
          "numalign",
          "open",
          "rowalign",
          "rowlines",
          "rowspacing",
          "rowspan",
          "rspace",
          "rquote",
          "scriptlevel",
          "scriptminsize",
          "scriptsizemultiplier",
          "selection",
          "separator",
          "separators",
          "stretchy",
          "subscriptshift",
          "supscriptshift",
          "symmetric",
          "voffset",
          "width",
          "xmlns",
        ]),
        re = r([
          "xlink:href",
          "xml:id",
          "xlink:title",
          "xml:space",
          "xmlns:xlink",
        ]),
        Ut = _(/\{\{[\w\W]*|[\w\W]*\}\}/gm),
        Ht = _(/<%[\w\W]*|[\w\W]*%>/gm),
        Ft = _(/\${[\w\W]*}/gm),
        Bt = _(/^data-[\-\w.\u00B7-\uFFFF]/),
        zt = _(/^aria-[\-\w]+$/),
        Ve = _(
          /^(?:(?:(?:f|ht)tps?|mailto|tel|callto|sms|cid|xmpp):|[^a-z]|[a-z+.\-]+(?:[^a-z+.\-:]|$))/i
        ),
        Gt = _(/^(?:\w+script|data):/i),
        Vt = _(/[\u0000-\u0020\u00A0\u1680\u180E\u2000-\u2029\u205F\u3000]/g),
        We = _(/^html$/i),
        Wt = _(/^[a-z][.\w]*(-[.\w]+)+$/i);
      var qe = Object.freeze({
        __proto__: null,
        MUSTACHE_EXPR: Ut,
        ERB_EXPR: Ht,
        TMPLIT_EXPR: Ft,
        DATA_ATTR: Bt,
        ARIA_ATTR: zt,
        IS_ALLOWED_URI: Ve,
        IS_SCRIPT_OR_DATA: Gt,
        ATTR_WHITESPACE: Vt,
        DOCTYPE_NAME: We,
        CUSTOM_ELEMENT: Wt,
      });
      let qt = function () {
          return typeof window > "u" ? null : window;
        },
        jt = function (i, p) {
          if (typeof i != "object" || typeof i.createPolicy != "function")
            return null;
          let f = null,
            w = "data-tt-policy-suffix";
          p && p.hasAttribute(w) && (f = p.getAttribute(w));
          let k = "dompurify" + (f ? "#" + f : "");
          try {
            return i.createPolicy(k, {
              createHTML(B) {
                return B;
              },
              createScriptURL(B) {
                return B;
              },
            });
          } catch {
            return (
              console.warn(
                "TrustedTypes policy " + k + " could not be created."
              ),
              null
            );
          }
        };
      function je() {
        let d =
            arguments.length > 0 && arguments[0] !== void 0
              ? arguments[0]
              : qt(),
          i = (a) => je(a);
        if (
          ((i.version = "3.1.2"),
          (i.removed = []),
          !d || !d.document || d.document.nodeType !== 9)
        )
          return (i.isSupported = !1), i;
        let { document: p } = d,
          f = p,
          w = f.currentScript,
          {
            DocumentFragment: k,
            HTMLTemplateElement: B,
            Node: Te,
            Element: Ye,
            NodeFilter: Z,
            NamedNodeMap: $t = d.NamedNodeMap || d.MozNamedAttrMap,
            HTMLFormElement: Xt,
            DOMParser: Kt,
            trustedTypes: le,
          } = d,
          ce = Ye.prototype,
          Zt = se(ce, "cloneNode"),
          Qt = se(ce, "nextSibling"),
          Jt = se(ce, "childNodes"),
          Q = se(ce, "parentNode");
        if (typeof B == "function") {
          let a = p.createElement("template");
          a.content && a.content.ownerDocument && (p = a.content.ownerDocument);
        }
        let C,
          J = "",
          {
            implementation: Ae,
            createNodeIterator: en,
            createDocumentFragment: tn,
            getElementsByTagName: nn,
          } = p,
          { importNode: on } = f,
          v = {};
        i.isSupported =
          typeof n == "function" &&
          typeof Q == "function" &&
          Ae &&
          Ae.createHTMLDocument !== void 0;
        let {
            MUSTACHE_EXPR: we,
            ERB_EXPR: Se,
            TMPLIT_EXPR: Le,
            DATA_ATTR: an,
            ARIA_ATTR: sn,
            IS_SCRIPT_OR_DATA: rn,
            ATTR_WHITESPACE: $e,
            CUSTOM_ELEMENT: ln,
          } = qe,
          { IS_ALLOWED_URI: Xe } = qe,
          E = null,
          Ke = c({}, [...Fe, ...he, ...ge, ..._e, ...Be]),
          T = null,
          Ze = c({}, [...ze, ...Ee, ...Ge, ...re]),
          h = Object.seal(
            N(null, {
              tagNameCheck: {
                writable: !0,
                configurable: !1,
                enumerable: !0,
                value: null,
              },
              attributeNameCheck: {
                writable: !0,
                configurable: !1,
                enumerable: !0,
                value: null,
              },
              allowCustomizedBuiltInElements: {
                writable: !0,
                configurable: !1,
                enumerable: !0,
                value: !1,
              },
            })
          ),
          ee = null,
          Ce = null,
          Qe = !0,
          ye = !0,
          Je = !1,
          et = !0,
          z = !1,
          tt = !0,
          F = !1,
          Ie = !1,
          Re = !1,
          G = !1,
          ue = !1,
          fe = !1,
          nt = !0,
          ot = !1,
          cn = "user-content-",
          Me = !0,
          te = !1,
          V = {},
          W = null,
          it = c({}, [
            "annotation-xml",
            "audio",
            "colgroup",
            "desc",
            "foreignobject",
            "head",
            "iframe",
            "math",
            "mi",
            "mn",
            "mo",
            "ms",
            "mtext",
            "noembed",
            "noframes",
            "noscript",
            "plaintext",
            "script",
            "style",
            "svg",
            "template",
            "thead",
            "title",
            "video",
            "xmp",
          ]),
          at = null,
          st = c({}, ["audio", "video", "img", "source", "image", "track"]),
          be = null,
          rt = c({}, [
            "alt",
            "class",
            "for",
            "id",
            "label",
            "name",
            "pattern",
            "placeholder",
            "role",
            "summary",
            "title",
            "value",
            "style",
            "xmlns",
          ]),
          de = "http://www.w3.org/1998/Math/MathML",
          pe = "http://www.w3.org/2000/svg",
          P = "http://www.w3.org/1999/xhtml",
          q = P,
          Oe = !1,
          De = null,
          un = c({}, [de, pe, P], me),
          ne = null,
          fn = ["application/xhtml+xml", "text/html"],
          dn = "text/html",
          A = null,
          j = null,
          lt = 255,
          pn = p.createElement("form"),
          ct = function (e) {
            return e instanceof RegExp || e instanceof Function;
          },
          ve = function () {
            let e =
              arguments.length > 0 && arguments[0] !== void 0
                ? arguments[0]
                : {};
            if (!(j && j === e)) {
              if (
                ((!e || typeof e != "object") && (e = {}),
                (e = H(e)),
                (ne =
                  fn.indexOf(e.PARSER_MEDIA_TYPE) === -1
                    ? dn
                    : e.PARSER_MEDIA_TYPE),
                (A = ne === "application/xhtml+xml" ? me : ae),
                (E = O(e, "ALLOWED_TAGS") ? c({}, e.ALLOWED_TAGS, A) : Ke),
                (T = O(e, "ALLOWED_ATTR") ? c({}, e.ALLOWED_ATTR, A) : Ze),
                (De = O(e, "ALLOWED_NAMESPACES")
                  ? c({}, e.ALLOWED_NAMESPACES, me)
                  : un),
                (be = O(e, "ADD_URI_SAFE_ATTR")
                  ? c(H(rt), e.ADD_URI_SAFE_ATTR, A)
                  : rt),
                (at = O(e, "ADD_DATA_URI_TAGS")
                  ? c(H(st), e.ADD_DATA_URI_TAGS, A)
                  : st),
                (W = O(e, "FORBID_CONTENTS")
                  ? c({}, e.FORBID_CONTENTS, A)
                  : it),
                (ee = O(e, "FORBID_TAGS") ? c({}, e.FORBID_TAGS, A) : {}),
                (Ce = O(e, "FORBID_ATTR") ? c({}, e.FORBID_ATTR, A) : {}),
                (V = O(e, "USE_PROFILES") ? e.USE_PROFILES : !1),
                (Qe = e.ALLOW_ARIA_ATTR !== !1),
                (ye = e.ALLOW_DATA_ATTR !== !1),
                (Je = e.ALLOW_UNKNOWN_PROTOCOLS || !1),
                (et = e.ALLOW_SELF_CLOSE_IN_ATTR !== !1),
                (z = e.SAFE_FOR_TEMPLATES || !1),
                (tt = e.SAFE_FOR_XML !== !1),
                (F = e.WHOLE_DOCUMENT || !1),
                (G = e.RETURN_DOM || !1),
                (ue = e.RETURN_DOM_FRAGMENT || !1),
                (fe = e.RETURN_TRUSTED_TYPE || !1),
                (Re = e.FORCE_BODY || !1),
                (nt = e.SANITIZE_DOM !== !1),
                (ot = e.SANITIZE_NAMED_PROPS || !1),
                (Me = e.KEEP_CONTENT !== !1),
                (te = e.IN_PLACE || !1),
                (Xe = e.ALLOWED_URI_REGEXP || Ve),
                (q = e.NAMESPACE || P),
                (h = e.CUSTOM_ELEMENT_HANDLING || {}),
                e.CUSTOM_ELEMENT_HANDLING &&
                  ct(e.CUSTOM_ELEMENT_HANDLING.tagNameCheck) &&
                  (h.tagNameCheck = e.CUSTOM_ELEMENT_HANDLING.tagNameCheck),
                e.CUSTOM_ELEMENT_HANDLING &&
                  ct(e.CUSTOM_ELEMENT_HANDLING.attributeNameCheck) &&
                  (h.attributeNameCheck =
                    e.CUSTOM_ELEMENT_HANDLING.attributeNameCheck),
                e.CUSTOM_ELEMENT_HANDLING &&
                  typeof e.CUSTOM_ELEMENT_HANDLING
                    .allowCustomizedBuiltInElements == "boolean" &&
                  (h.allowCustomizedBuiltInElements =
                    e.CUSTOM_ELEMENT_HANDLING.allowCustomizedBuiltInElements),
                z && (ye = !1),
                ue && (G = !0),
                V &&
                  ((E = c({}, Be)),
                  (T = []),
                  V.html === !0 && (c(E, Fe), c(T, ze)),
                  V.svg === !0 && (c(E, he), c(T, Ee), c(T, re)),
                  V.svgFilters === !0 && (c(E, ge), c(T, Ee), c(T, re)),
                  V.mathMl === !0 && (c(E, _e), c(T, Ge), c(T, re))),
                e.ADD_TAGS && (E === Ke && (E = H(E)), c(E, e.ADD_TAGS, A)),
                e.ADD_ATTR && (T === Ze && (T = H(T)), c(T, e.ADD_ATTR, A)),
                e.ADD_URI_SAFE_ATTR && c(be, e.ADD_URI_SAFE_ATTR, A),
                e.FORBID_CONTENTS &&
                  (W === it && (W = H(W)), c(W, e.FORBID_CONTENTS, A)),
                Me && (E["#text"] = !0),
                F && c(E, ["html", "head", "body"]),
                E.table && (c(E, ["tbody"]), delete ee.tbody),
                e.TRUSTED_TYPES_POLICY)
              ) {
                if (typeof e.TRUSTED_TYPES_POLICY.createHTML != "function")
                  throw K(
                    'TRUSTED_TYPES_POLICY configuration option must provide a "createHTML" hook.'
                  );
                if (typeof e.TRUSTED_TYPES_POLICY.createScriptURL != "function")
                  throw K(
                    'TRUSTED_TYPES_POLICY configuration option must provide a "createScriptURL" hook.'
                  );
                (C = e.TRUSTED_TYPES_POLICY), (J = C.createHTML(""));
              } else
                C === void 0 && (C = jt(le, w)),
                  C !== null && typeof J == "string" && (J = C.createHTML(""));
              r && r(e), (j = e);
            }
          },
          ut = c({}, ["mi", "mo", "mn", "ms", "mtext"]),
          ft = c({}, ["foreignobject", "annotation-xml"]),
          mn = c({}, ["title", "style", "font", "a", "script"]),
          dt = c({}, [...he, ...ge, ...Pt]),
          pt = c({}, [..._e, ...xt]),
          hn = function (e) {
            let t = Q(e);
            (!t || !t.tagName) &&
              (t = { namespaceURI: q, tagName: "template" });
            let o = ae(e.tagName),
              l = ae(t.tagName);
            return De[e.namespaceURI]
              ? e.namespaceURI === pe
                ? t.namespaceURI === P
                  ? o === "svg"
                  : t.namespaceURI === de
                  ? o === "svg" && (l === "annotation-xml" || ut[l])
                  : !!dt[o]
                : e.namespaceURI === de
                ? t.namespaceURI === P
                  ? o === "math"
                  : t.namespaceURI === pe
                  ? o === "math" && ft[l]
                  : !!pt[o]
                : e.namespaceURI === P
                ? (t.namespaceURI === pe && !ft[l]) ||
                  (t.namespaceURI === de && !ut[l])
                  ? !1
                  : !pt[o] && (mn[o] || !dt[o])
                : !!(ne === "application/xhtml+xml" && De[e.namespaceURI])
              : !1;
          },
          D = function (e) {
            $(i.removed, { element: e });
            try {
              e.parentNode.removeChild(e);
            } catch {
              e.remove();
            }
          },
          Ne = function (e, t) {
            try {
              $(i.removed, { attribute: t.getAttributeNode(e), from: t });
            } catch {
              $(i.removed, { attribute: null, from: t });
            }
            if ((t.removeAttribute(e), e === "is" && !T[e]))
              if (G || ue)
                try {
                  D(t);
                } catch {}
              else
                try {
                  t.setAttribute(e, "");
                } catch {}
          },
          mt = function (e) {
            let t = null,
              o = null;
            if (Re) e = "<remove></remove>" + e;
            else {
              let L = He(e, /^[\r\n\t ]+/);
              o = L && L[0];
            }
            ne === "application/xhtml+xml" &&
              q === P &&
              (e =
                '<html xmlns="http://www.w3.org/1999/xhtml"><head></head><body>' +
                e +
                "</body></html>");
            let l = C ? C.createHTML(e) : e;
            if (q === P)
              try {
                t = new Kt().parseFromString(l, ne);
              } catch {}
            if (!t || !t.documentElement) {
              t = Ae.createDocument(q, "template", null);
              try {
                t.documentElement.innerHTML = Oe ? J : l;
              } catch {}
            }
            let S = t.body || t.documentElement;
            return (
              e &&
                o &&
                S.insertBefore(p.createTextNode(o), S.childNodes[0] || null),
              q === P
                ? nn.call(t, F ? "html" : "body")[0]
                : F
                ? t.documentElement
                : S
            );
          },
          ht = function (e) {
            return en.call(
              e.ownerDocument || e,
              e,
              Z.SHOW_ELEMENT |
                Z.SHOW_COMMENT |
                Z.SHOW_TEXT |
                Z.SHOW_PROCESSING_INSTRUCTION |
                Z.SHOW_CDATA_SECTION,
              null
            );
          },
          gn = function (e) {
            return (
              e instanceof Xt &&
              ((typeof e.__depth < "u" && typeof e.__depth != "number") ||
                (typeof e.__removalCount < "u" &&
                  typeof e.__removalCount != "number") ||
                typeof e.nodeName != "string" ||
                typeof e.textContent != "string" ||
                typeof e.removeChild != "function" ||
                !(e.attributes instanceof $t) ||
                typeof e.removeAttribute != "function" ||
                typeof e.setAttribute != "function" ||
                typeof e.namespaceURI != "string" ||
                typeof e.insertBefore != "function" ||
                typeof e.hasChildNodes != "function")
            );
          },
          gt = function (e) {
            return typeof Te == "function" && e instanceof Te;
          },
          x = function (e, t, o) {
            v[e] &&
              ie(v[e], (l) => {
                l.call(i, t, o, j);
              });
          },
          _t = function (e) {
            let t = null;
            if ((x("beforeSanitizeElements", e, null), gn(e))) return D(e), !0;
            let o = A(e.nodeName);
            if (
              (x("uponSanitizeElement", e, { tagName: o, allowedTags: E }),
              (e.hasChildNodes() &&
                !gt(e.firstElementChild) &&
                y(/<[/\w]/g, e.innerHTML) &&
                y(/<[/\w]/g, e.textContent)) ||
                e.nodeType === 7 ||
                (tt && e.nodeType === 8 && y(/<[/\w]/g, e.data)))
            )
              return D(e), !0;
            if (!E[o] || ee[o]) {
              if (
                !ee[o] &&
                Tt(o) &&
                ((h.tagNameCheck instanceof RegExp && y(h.tagNameCheck, o)) ||
                  (h.tagNameCheck instanceof Function && h.tagNameCheck(o)))
              )
                return !1;
              if (Me && !W[o]) {
                let l = Q(e) || e.parentNode,
                  S = Jt(e) || e.childNodes;
                if (S && l) {
                  let L = S.length;
                  for (let I = L - 1; I >= 0; --I) {
                    let R = Zt(S[I], !0);
                    (R.__removalCount = (e.__removalCount || 0) + 1),
                      l.insertBefore(R, Qt(e));
                  }
                }
              }
              return D(e), !0;
            }
            return (e instanceof Ye && !hn(e)) ||
              ((o === "noscript" || o === "noembed" || o === "noframes") &&
                y(/<\/no(script|embed|frames)/i, e.innerHTML))
              ? (D(e), !0)
              : (z &&
                  e.nodeType === 3 &&
                  ((t = e.textContent),
                  ie([we, Se, Le], (l) => {
                    t = X(t, l, " ");
                  }),
                  e.textContent !== t &&
                    ($(i.removed, { element: e.cloneNode() }),
                    (e.textContent = t))),
                x("afterSanitizeElements", e, null),
                !1);
          },
          Et = function (e, t, o) {
            if (nt && (t === "id" || t === "name") && (o in p || o in pn))
              return !1;
            if (!(ye && !Ce[t] && y(an, t))) {
              if (!(Qe && y(sn, t))) {
                if (!T[t] || Ce[t]) {
                  if (
                    !(
                      (Tt(e) &&
                        ((h.tagNameCheck instanceof RegExp &&
                          y(h.tagNameCheck, e)) ||
                          (h.tagNameCheck instanceof Function &&
                            h.tagNameCheck(e))) &&
                        ((h.attributeNameCheck instanceof RegExp &&
                          y(h.attributeNameCheck, t)) ||
                          (h.attributeNameCheck instanceof Function &&
                            h.attributeNameCheck(t)))) ||
                      (t === "is" &&
                        h.allowCustomizedBuiltInElements &&
                        ((h.tagNameCheck instanceof RegExp &&
                          y(h.tagNameCheck, o)) ||
                          (h.tagNameCheck instanceof Function &&
                            h.tagNameCheck(o))))
                    )
                  )
                    return !1;
                } else if (!be[t]) {
                  if (!y(Xe, X(o, $e, ""))) {
                    if (
                      !(
                        (t === "src" || t === "xlink:href" || t === "href") &&
                        e !== "script" &&
                        Dt(o, "data:") === 0 &&
                        at[e]
                      )
                    ) {
                      if (!(Je && !y(rn, X(o, $e, "")))) {
                        if (o) return !1;
                      }
                    }
                  }
                }
              }
            }
            return !0;
          },
          Tt = function (e) {
            return e !== "annotation-xml" && He(e, ln);
          },
          At = function (e) {
            x("beforeSanitizeAttributes", e, null);
            let { attributes: t } = e;
            if (!t) return;
            let o = {
                attrName: "",
                attrValue: "",
                keepAttr: !0,
                allowedAttributes: T,
              },
              l = t.length;
            for (; l--; ) {
              let S = t[l],
                { name: L, namespaceURI: I, value: R } = S,
                oe = A(L),
                M = L === "value" ? R : vt(R);
              if (
                ((o.attrName = oe),
                (o.attrValue = M),
                (o.keepAttr = !0),
                (o.forceKeepAttr = void 0),
                x("uponSanitizeAttribute", e, o),
                (M = o.attrValue),
                o.forceKeepAttr || (Ne(L, e), !o.keepAttr))
              )
                continue;
              if (!et && y(/\/>/i, M)) {
                Ne(L, e);
                continue;
              }
              z &&
                ie([we, Se, Le], (St) => {
                  M = X(M, St, " ");
                });
              let wt = A(e.nodeName);
              if (Et(wt, oe, M)) {
                if (
                  (ot &&
                    (oe === "id" || oe === "name") &&
                    (Ne(L, e), (M = cn + M)),
                  C &&
                    typeof le == "object" &&
                    typeof le.getAttributeType == "function" &&
                    !I)
                )
                  switch (le.getAttributeType(wt, oe)) {
                    case "TrustedHTML": {
                      M = C.createHTML(M);
                      break;
                    }
                    case "TrustedScriptURL": {
                      M = C.createScriptURL(M);
                      break;
                    }
                  }
                try {
                  I ? e.setAttributeNS(I, L, M) : e.setAttribute(L, M),
                    Ue(i.removed);
                } catch {}
              }
            }
            x("afterSanitizeAttributes", e, null);
          },
          _n = function a(e) {
            let t = null,
              o = ht(e);
            for (x("beforeSanitizeShadowDOM", e, null); (t = o.nextNode()); ) {
              if ((x("uponSanitizeShadowNode", t, null), _t(t))) continue;
              let l = Q(t);
              t.nodeType === 1 &&
                (l && l.__depth
                  ? (t.__depth = (t.__removalCount || 0) + l.__depth + 1)
                  : (t.__depth = 1)),
                t.__depth >= lt && D(t),
                t.content instanceof k &&
                  ((t.content.__depth = t.__depth), a(t.content)),
                At(t);
            }
            x("afterSanitizeShadowDOM", e, null);
          };
        return (
          (i.sanitize = function (a) {
            let e =
                arguments.length > 1 && arguments[1] !== void 0
                  ? arguments[1]
                  : {},
              t = null,
              o = null,
              l = null,
              S = null;
            if (
              ((Oe = !a), Oe && (a = "<!-->"), typeof a != "string" && !gt(a))
            )
              if (typeof a.toString == "function") {
                if (((a = a.toString()), typeof a != "string"))
                  throw K("dirty is not a string, aborting");
              } else throw K("toString is not a function");
            if (!i.isSupported) return a;
            if (
              (Ie || ve(e),
              (i.removed = []),
              typeof a == "string" && (te = !1),
              te)
            ) {
              if (a.nodeName) {
                let R = A(a.nodeName);
                if (!E[R] || ee[R])
                  throw K(
                    "root node is forbidden and cannot be sanitized in-place"
                  );
              }
            } else if (a instanceof Te)
              (t = mt("<!---->")),
                (o = t.ownerDocument.importNode(a, !0)),
                (o.nodeType === 1 && o.nodeName === "BODY") ||
                o.nodeName === "HTML"
                  ? (t = o)
                  : t.appendChild(o);
            else {
              if (!G && !z && !F && a.indexOf("<") === -1)
                return C && fe ? C.createHTML(a) : a;
              if (((t = mt(a)), !t)) return G ? null : fe ? J : "";
            }
            t && Re && D(t.firstChild);
            let L = ht(te ? a : t);
            for (; (l = L.nextNode()); ) {
              if (_t(l)) continue;
              let R = Q(l);
              l.nodeType === 1 &&
                (R && R.__depth
                  ? (l.__depth = (l.__removalCount || 0) + R.__depth + 1)
                  : (l.__depth = 1)),
                l.__depth >= lt && D(l),
                l.content instanceof k &&
                  ((l.content.__depth = l.__depth), _n(l.content)),
                At(l);
            }
            if (te) return a;
            if (G) {
              if (ue)
                for (S = tn.call(t.ownerDocument); t.firstChild; )
                  S.appendChild(t.firstChild);
              else S = t;
              return (
                (T.shadowroot || T.shadowrootmode) && (S = on.call(f, S, !0)), S
              );
            }
            let I = F ? t.outerHTML : t.innerHTML;
            return (
              F &&
                E["!doctype"] &&
                t.ownerDocument &&
                t.ownerDocument.doctype &&
                t.ownerDocument.doctype.name &&
                y(We, t.ownerDocument.doctype.name) &&
                (I =
                  "<!DOCTYPE " +
                  t.ownerDocument.doctype.name +
                  `>
` +
                  I),
              z &&
                ie([we, Se, Le], (R) => {
                  I = X(I, R, " ");
                }),
              C && fe ? C.createHTML(I) : I
            );
          }),
          (i.setConfig = function () {
            let a =
              arguments.length > 0 && arguments[0] !== void 0
                ? arguments[0]
                : {};
            ve(a), (Ie = !0);
          }),
          (i.clearConfig = function () {
            (j = null), (Ie = !1);
          }),
          (i.isValidAttribute = function (a, e, t) {
            j || ve({});
            let o = A(a),
              l = A(e);
            return Et(o, l, t);
          }),
          (i.addHook = function (a, e) {
            typeof e == "function" && ((v[a] = v[a] || []), $(v[a], e));
          }),
          (i.removeHook = function (a) {
            if (v[a]) return Ue(v[a]);
          }),
          (i.removeHooks = function (a) {
            v[a] && (v[a] = []);
          }),
          (i.removeAllHooks = function () {
            v = {};
          }),
          i
        );
      }
      var Yt = je();
      return Yt;
    });
  });
  var Mt = yn(Ct());
  var yt = `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
<g clip-path="url(#clip0_466_934)">
  <path d="M5.41675 10.8333L6.07046 12.1408C6.2917 12.5832 6.40232 12.8045 6.55011 12.9962C6.68124 13.1663 6.83375 13.3188 7.00388 13.45C7.19559 13.5977 7.41684 13.7084 7.85932 13.9296L9.16675 14.5833L7.85932 15.237C7.41684 15.4583 7.19559 15.5689 7.00388 15.7167C6.83375 15.8478 6.68124 16.0003 6.55011 16.1704C6.40232 16.3622 6.2917 16.5834 6.07046 17.0259L5.41675 18.3333L4.76303 17.0259C4.54179 16.5834 4.43117 16.3622 4.28339 16.1704C4.15225 16.0003 3.99974 15.8478 3.82962 15.7167C3.6379 15.5689 3.41666 15.4583 2.97418 15.237L1.66675 14.5833L2.97418 13.9296C3.41666 13.7084 3.6379 13.5977 3.82962 13.45C3.99974 13.3188 4.15225 13.1663 4.28339 12.9962C4.43117 12.8045 4.54179 12.5832 4.76303 12.1408L5.41675 10.8333Z" stroke="#181825" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
  <path d="M12.5001 1.66666L13.4823 4.22034C13.7173 4.83136 13.8348 5.13688 14.0175 5.39386C14.1795 5.62162 14.3785 5.82061 14.6062 5.98256C14.8632 6.16529 15.1687 6.2828 15.7797 6.5178L18.3334 7.49999L15.7797 8.48217C15.1687 8.71718 14.8632 8.83469 14.6062 9.01742C14.3785 9.17937 14.1795 9.37836 14.0175 9.60612C13.8348 9.8631 13.7173 10.1686 13.4823 10.7796L12.5001 13.3333L11.5179 10.7796C11.2829 10.1686 11.1654 9.8631 10.9827 9.60612C10.8207 9.37836 10.6217 9.17937 10.3939 9.01742C10.137 8.83469 9.83145 8.71718 9.22043 8.48217L6.66675 7.49999L9.22043 6.5178C9.83145 6.28279 10.137 6.16529 10.3939 5.98256C10.6217 5.82061 10.8207 5.62162 10.9827 5.39386C11.1654 5.13688 11.2829 4.83136 11.5179 4.22034L12.5001 1.66666Z" stroke="#181825" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
</g>
<defs>
  <clipPath id="clip0_466_934">
    <rect width="20" height="20" fill="white"/>
  </clipPath>
</defs>
</svg>
`;
  var bt = "phc_EKMR6Jt4OTMEYmoUlz0v58KPwqcFxI7aZCLckpSD8Tv",
    In = "https://eu.i.posthog.com",
    Rn = `ph_${bt}_posthog`,
    Mn = (n) => {
      let s = n.target,
        m = document.querySelector(".dialog-input-submit");
      m !== null && (m.disabled = s.value.trim() === "");
    },
    bn = () => {
      let n = document.querySelector(".dialog-ask-anything-input");
      n && n.addEventListener("input", Mn);
    },
    Ot = (n, s) => {
      let m = localStorage.getItem(Rn);
      if (m === null) return;
      let { distinct_id: g, $client_session_props: u } = JSON.parse(m);
      fetch(`${In}/capture`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          api_key: bt,
          event: n,
          distinct_id: g,
          properties: {
            ...s,
            $session_id: u?.sessionId,
            $host: document.location.host,
            $pathname: document.location.pathname,
          },
        }),
      });
    },
    It = (n, s) => (m) => {
      m.preventDefault();
      let g = document.getElementById("send-message-button");
      if (g === null) return;
      (n.dataset.question = s.value),
        window.scrollTo({ top: 0, left: 0, behavior: "smooth" });
      let r = new URLSearchParams(window.location.search).get("variant"),
        _ = new CustomEvent("enableDialogAssistantEvent", {
          detail: {
            type: "PRODUCT_QUESTION",
            payload: {
              question: n.dataset.question,
              productId: n.dataset.productId,
              productTitle: n.dataset.productTitle,
              handle: n.dataset.handle,
              selectedVariantId: r ?? n.dataset.selectedVariantId,
            },
          },
        });
      window.dispatchEvent(_),
        Ot("user_sent_custom_message", { message: n.dataset.question }),
        s.blur(),
        (s.value = ""),
        (g.disabled = !0);
    },
    On = (n, s) => (m) => {
      m.preventDefault();
      let g = document.getElementById("dialog-suggestions-container");
      if (g === null) return;
      let u = g.children[s],
        r = u.dataset.value,
        _ = u.dataset.answer;
      (n.dataset.question = r),
        (n.dataset.answer = _),
        window.scrollTo({ top: 0, left: 0, behavior: "smooth" });
      let U = new URLSearchParams(window.location.search).get("variant"),
        Y = new CustomEvent("enableDialogAssistantEvent", {
          detail: {
            type: "PRODUCT_QUESTION",
            payload: {
              question: r,
              answer: _,
              productId: n.dataset.productId,
              handle: n.dataset.handle,
              fromQuestionSuggestion: !0,
              productTitle: n.dataset.productTitle,
              selectedVariantId: U ?? n.dataset.selectedVariantId,
            },
          },
        });
      window.dispatchEvent(Y),
        Ot("user_clicked_suggestion", { question: r ?? "" });
    },
    xe = 0,
    Rt = (n) => {
      let s = ++xe,
        m = window.DIALOG_PRODUCT_VARIABLES?.productId,
        g = window.location.pathname.split("?")[0];
      fetch(
        `https://rtbzcxkmwj.execute-api.eu-west-1.amazonaws.com/ai/product-questions?pagePath=${g}&locale=${n}&productId=${
          m ?? ""
        }`,
        { headers: { Authorization: window.DIALOG_VARIABLES.apiKey } }
      )
        .then((u) => u.json())
        .then((u) => {
          if (s !== xe) {
            console.log("ignoring outdated suggestions response");
            return;
          }
          if (u.assistantName !== void 0) {
            let r = document.getElementById("assistant-name");
            r !== null && (r.innerHTML = u.assistantName);
          }
          if (u.description !== void 0) {
            let r = document.getElementById("description");
            r !== null && (r.innerHTML = u.description);
          }
          if (u.inputPlaceholder !== void 0) {
            let r = document.getElementById("dialog-ask-anything-input");
            r !== null && (r.placeholder = u.inputPlaceholder);
          }
          u.questions !== void 0 &&
            u.questions.slice(0, 2).forEach((r, _) => {
              let N = document.getElementById(`dialog-suggestion-${_}`);
              if (N !== null) {
                (N.style.width = "fit-content"),
                  (N.dataset.value = r.question),
                  r.answer !== void 0 && (N.dataset.answer = r.answer);
                let U = Mt.default.sanitize(r.question);
                N.innerHTML = `${yt}<span data-value="${U}">${U}</span>`;
              }
            });
        })
        .catch((u) => {
          if (s !== xe) return;
          let r = document.getElementById("dialog-instant");
          r !== null && (r.style.display = "none"), console.error("Error:", u);
        });
    },
    Dn = (n) => (s) => {
      let g = new URLSearchParams(window.location.search).get("variant"),
        u = new CustomEvent("enableDialogAssistantEvent", {
          detail: {
            type: "START_DIAGNOSTIC",
            payload: {
              buttonType: "productPageButton",
              productId: n.dataset.productId,
              handle: n.dataset.handle,
              productTitle: n.dataset.productTitle,
              selectedVariantId: g ?? n.dataset.selectedVariantId,
            },
          },
        });
      window.dispatchEvent(u);
    },
    vn = (n) => {
      let s = document.getElementById("dialog-diagnostic-btn");
      s !== null && s.addEventListener("click", Dn(n));
    },
    Nn = () => {
      let n = window.Weglot?.getCurrentLang();
      return n !== void 0
        ? n
        : window.DIALOG_VARIABLES.locale !== void 0
        ? window.DIALOG_VARIABLES.locale
        : window.Shopify?.locale ?? "en";
    },
    kn = (n) => {
      let s = n?.cloneNode(!0);
      n?.remove(), s !== void 0 && document.body.appendChild(s);
    };
  document.addEventListener("DOMContentLoaded", () => {
    let n = document.getElementById("dialog-shopify-ai-product");
    kn(n);
    let s = document.querySelectorAll(".dialog-suggestion"),
      m = document.querySelector(".dialog-ask-anything-input"),
      g = document.querySelector(".dialog-input-submit");
    window.Weglot !== void 0 &&
      window.Weglot.on("languageChanged", (u) => {
        Rt(u);
      }),
      Rt(Nn()),
      bn(),
      !(n === null || m === null || g === null) &&
        (vn(n),
        m.addEventListener("keypress", (u) => {
          u.key === "Enter" && It(n, m)(u);
        }),
        g.addEventListener("click", It(n, m)),
        s.forEach((u, r) => {
          u.addEventListener("click", On(n, r));
        }));
  });
})();
/*! Bundled license information:

dompurify/dist/purify.js:
  (*! @license DOMPurify 3.1.2 | (c) Cure53 and other contributors | Released under the Apache license 2.0 and Mozilla Public License 2.0 | github.com/cure53/DOMPurify/blob/3.1.2/LICENSE *)
*/
