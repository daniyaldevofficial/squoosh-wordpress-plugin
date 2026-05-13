// Detect base path dynamically
const BASE_PATH = self.location.pathname.replace(/\/serviceworker\.js$/, '') || '/';
const ASSET_NAMES = [
  "logo-99b7d28c.svg",
  "github-logo-eaea4a88.svg",
  "icon-demo-large-photo-18da387a.jpg",
  "icon-demo-device-screen-5d52d8b9.jpg",
  "icon-demo-artwork-9eba1655.jpg",
  "icon-demo-logo-326ed9b6.png",
  "small-db1eae6f.svg",
  "simple-258b6ed5.svg",
  "secure-a66bbdfe.svg",
  "demo-device-screen-b9d088e8.png",
  "demo-artwork-c444f915.jpg",
  "demo-large-photo-a6b23f7b.jpg",
  "rotate-e8fb6784.wasm",
  "imagequant-a10bbe1a.wasm",
  "squoosh_oxipng_bg-60d7d0b0.wasm",
  "squoosh_oxipng_bg-5c8fadb7.wasm",
  "qoi_enc-9285b08c.wasm",
  "qoi_dec-3728a8ee.wasm",
  "webp_dec-12bed04a.wasm",
  "webp_enc-a8223a7d.wasm",
  "webp_enc_simd-75acd924.wasm",
  "wp2_dec-9a40adf1.wasm",
  "mozjpeg_enc-f6bf569c.wasm",
  "jxl_dec-e90a5afa.wasm",
  "wp2_enc_mt-1feb6658.wasm",
  "wp2_enc_mt_simd-0b0595e9.wasm",
  "wp2_enc-89317929.wasm",
  "avif_dec-d634d9c0.wasm",
  "jxl_enc-68f8271f.wasm",
  "jxl_enc_mt-669d03c7.wasm",
  "avif_enc-90ce2a03.wasm",
  "jxl_enc_mt_simd-efe18ebf.wasm",
  "avif_enc_mt-9d34100e.wasm",
  "squoosh_resize_bg-3d426466.wasm",
  "squooshhqx_bg-6e04a330.wasm",
  "initial-app-4aa9e0b2.js",
  "idb-keyval-4fe2029e.js",
  "supports-wasm-threads-9275c454.js",
  "features-worker-386aaeec.js",
  "util-d4fc8e28.js",
  "avif_enc_mt.worker-7977db9e.js",
  "jxl_enc_mt_simd.worker-dd206d0c.js",
  "jxl_enc_mt.worker-95b27835.js",
  "wp2_enc_mt.worker-04e62a32.js",
  "wp2_enc_mt_simd.worker-8b6577c8.js",
  "workerHelpers-0eac9207.js",
  "Compress-2132674c.js",
  "sw-bridge-deeedd5e.js",
  "blob-anim-ad7d39ed.js",
  "avif_dec-d76f181f.js",
  "webp_dec-36c82cbe.js",
  "avif_enc_mt-83a02aa9.js",
  "avif_enc-3114815b.js",
  "jxl_enc_mt_simd-91fc90e8.js",
  "jxl_enc_mt-8927cc86.js",
  "jxl_enc-c802bba0.js",
  "squoosh_oxipng-771fbe6b.js",
  "squoosh_oxipng-74b27c9c.js",
  "webp_enc_simd-2d7d2456.js",
  "webp_enc-75623855.js",
  "wp2_enc_mt_simd-4cb31c0d.js",
  "wp2_enc_mt-39e93186.js",
  "wp2_enc-9f185f33.js"
];
const ASSETS = ASSET_NAMES.map(name => BASE_PATH.replace(/\/$/, '') + '/c/' + name);
const VERSION = "22971c45ab0505402b0c43990de45feeef01acea";
if (!self.define) {
  let modules = {};

  const loadModule = (url, base) => {
    // Append .js only if not already present
    if (!url.endsWith(".js")) {
      url = url + ".js";
    }

    // Handle absolute paths (start with "/")
    if (url.startsWith("/")) {
      url = location.origin + url;
    } else {
      url = new URL(url, base).href;
    }

    return (
      modules[url] ||
      new Promise((resolve) => {
        if ("document" in self) {
          const link = document.createElement("link");
          link.rel = "preload";
          link.as = "script";
          link.href = url;
          link.onload = () => {
            const script = document.createElement("script");
            script.src = url;
            script.onload = resolve;
            document.head.appendChild(script);
          };
          document.head.appendChild(link);
        } else {
          self.nextDefineUri = url;
          importScripts(url);
          resolve();
        }
      }).then(() => {
        let mod = modules[url];
        if (!mod) throw new Error(`Module ${url} didn’t register its module`);
        return mod;
      })
    );
  };

  self.define = (deps, factory) => {
    const uri =
      self.nextDefineUri ||
      ("document" in self ? document.currentScript.src : "") ||
      location.href;

    if (modules[uri]) return;

    let exportsObj = {};
    const requireFn = (dep) => loadModule(dep, uri);
    const context = {
      module: { uri },
      exports: exportsObj,
      require: requireFn,
    };

    modules[uri] = Promise.resolve()
      .then(() => Promise.all(deps.map((d) => context[d] || requireFn(d))))
      .then((resolved) => {
        factory(...resolved);
        return exportsObj;
      });
  };
}
// Helper function to generate dynamic paths based on service worker location
const FILE_PATH = (() => BASE_PATH.replace(/\/$/, '') + '/c/')();
const f = (filename) => FILE_PATH + filename;
const fa = (filenames) => filenames.map(f);
define([f("supports-wasm-threads-9275c454"), f("idb-keyval-4fe2029e")], (function(e, A) {
    var t = "data:image/webp;base64,UklGRh4AAABXRUJQVlA4TBEAAAAvAAAAAAfQ//73v/+BiOh/AAA=",
        n = "data:image/avif;base64,AAAAIGZ0eXBhdmlmAAAAAGF2aWZtaWYxbWlhZk1BMUEAAADybWV0YQAAAAAAAAAoaGRscgAAAAAAAAAAcGljdAAAAAAAAAAAAAAAAGxpYmF2aWYAAAAADnBpdG0AAAAAAAEAAAAeaWxvYwAAAABEAAABAAEAAAABAAABGgAAABUAAAAoaWluZgAAAAAAAQAAABppbmZlAgAAAAABAABhdjAxQ29sb3IAAAAAamlwcnAAAABLaXBjbwAAABRpc3BlAAAAAAAAAAEAAAABAAAAEHBpeGkAAAAAAwgICAAAAAxhdjFDgS0AAAAAABNjb2xybmNseAACAAIAAoAAAAAXaXBtYQAAAAAAAAABAAEEAQKDBAAAAB1tZGF0EgAKBDgADskyCx/wAABYAAAAAK+w";
    const a = f("initial-app-4aa9e0b2.js"),
        _ = fa(["logo-99b7d28c.svg", "github-logo-eaea4a88.svg", "demo-large-photo-a6b23f7b.jpg", "demo-artwork-c444f915.jpg", "demo-device-screen-b9d088e8.png", "icon-demo-large-photo-18da387a.jpg", "icon-demo-artwork-9eba1655.jpg", "icon-demo-device-screen-5d52d8b9.jpg", "small-db1eae6f.svg", "simple-258b6ed5.svg", "secure-a66bbdfe.svg", "icon-demo-logo-326ed9b6.png"]),
        s = f("Compress-2132674c.js"),
        i = fa(["initial-app-4aa9e0b2.js", "util-d4fc8e28.js", "features-worker-386aaeec.js", "logo-99b7d28c.svg", "github-logo-eaea4a88.svg", "demo-large-photo-a6b23f7b.jpg", "demo-artwork-c444f915.jpg", "demo-device-screen-b9d088e8.png", "icon-demo-large-photo-18da387a.jpg", "icon-demo-artwork-9eba1655.jpg", "icon-demo-device-screen-5d52d8b9.jpg", "small-db1eae6f.svg", "simple-258b6ed5.svg", "secure-a66bbdfe.svg", "icon-demo-logo-326ed9b6.png"]),
        r = f("sw-bridge-deeedd5e.js"),
        c = [f("idb-keyval-4fe2029e.js"), self.location.href],
        o = f("blob-anim-ad7d39ed.js"),
        l = fa(["initial-app-4aa9e0b2.js", "logo-99b7d28c.svg", "github-logo-eaea4a88.svg", "demo-large-photo-a6b23f7b.jpg", "demo-artwork-c444f915.jpg", "demo-device-screen-b9d088e8.png", "icon-demo-large-photo-18da387a.jpg", "icon-demo-artwork-9eba1655.jpg", "icon-demo-device-screen-5d52d8b9.jpg", "small-db1eae6f.svg", "simple-258b6ed5.svg", "secure-a66bbdfe.svg", "icon-demo-logo-326ed9b6.png"]),
        N = f("features-worker-386aaeec.js"),
        E = fa(["util-d4fc8e28.js", "supports-wasm-threads-9275c454.js", "jxl_dec-e90a5afa.wasm", "qoi_dec-3728a8ee.wasm", "wp2_dec-9a40adf1.wasm", "mozjpeg_enc-f6bf569c.wasm", "qoi_enc-9285b08c.wasm", "rotate-e8fb6784.wasm", "imagequant-a10bbe1a.wasm", "squoosh_resize_bg-3d426466.wasm", "squooshhqx_bg-6e04a330.wasm"]);
    var d = Object.freeze({
        __proto__: null,
        main: N,
        deps: E
    });
    const f_avif_dec = f("avif_dec-d76f181f.js"),
        u = fa(["avif_dec-d634d9c0.wasm"]);
    var p = Object.freeze({
        __proto__: null,
        main: f_avif_dec,
        deps: u
    });
    const T = f("webp_dec-36c82cbe.js"),
        m = fa(["webp_dec-12bed04a.wasm"]);
    var P = Object.freeze({
        __proto__: null,
        main: T,
        deps: m
    });
    const D = f("avif_enc_mt-83a02aa9.js"),
        I = fa(["avif_enc_mt-9d34100e.wasm", "avif_enc_mt.worker-7977db9e.js"]);
    var h = Object.freeze({
        __proto__: null,
        main: D,
        deps: I
    });
    const U = f("avif_enc-3114815b.js"),
        w = fa(["avif_enc-90ce2a03.wasm"]);
    var G = Object.freeze({
        __proto__: null,
        main: U,
        deps: w
    });
    const R = f("jxl_enc_mt_simd-91fc90e8.js"),
        L = fa(["jxl_enc_mt_simd-efe18ebf.wasm", "jxl_enc_mt_simd.worker-dd206d0c.js"]);
    var Y = Object.freeze({
        __proto__: null,
        main: R,
        deps: L
    });
    const M = f("jxl_enc_mt-8927cc86.js"),
        S = fa(["jxl_enc_mt-669d03c7.wasm", "jxl_enc_mt.worker-95b27835.js"]);
    var g = Object.freeze({
        __proto__: null,
        main: M,
        deps: S
    });
    const v = f("jxl_enc-c802bba0.js"),
        b = fa(["jxl_enc-68f8271f.wasm"]);
    var B = Object.freeze({
        __proto__: null,
        main: v,
        deps: b
    });
    const j = f("squoosh_oxipng-771fbe6b.js"),
        y = fa(["workerHelpers-0eac9207.js", "squoosh_oxipng_bg-5c8fadb7.wasm"]);
    var O = Object.freeze({
        __proto__: null,
        main: j,
        deps: y
    });
    const z = f("squoosh_oxipng-74b27c9c.js"),
        W = fa(["squoosh_oxipng_bg-60d7d0b0.wasm"]);
    var k = Object.freeze({
        __proto__: null,
        main: z,
        deps: W
    });
    const x = f("webp_enc_simd-2d7d2456.js"),
        q = fa(["webp_enc_simd-75acd924.wasm"]);
    var Q = Object.freeze({
        __proto__: null,
        main: x,
        deps: q
    });
    const C = f("webp_enc-75623855.js"),
        Z = fa(["webp_enc-a8223a7d.wasm"]);
    var X = Object.freeze({
        __proto__: null,
        main: C,
        deps: Z
    });
    const F = f("wp2_enc_mt_simd-4cb31c0d.js"),
        K = fa(["wp2_enc_mt_simd-0b0595e9.wasm", "wp2_enc_mt_simd.worker-8b6577c8.js"]);
    var V = Object.freeze({
        __proto__: null,
        main: F,
        deps: K
    });
    const H = f("wp2_enc_mt-39e93186.js"),
        J = fa(["wp2_enc_mt-1feb6658.wasm", "wp2_enc_mt.worker-04e62a32.js"]);
    var $ = Object.freeze({
        __proto__: null,
        main: H,
        deps: J
    });
    const ee = f("wp2_enc-9f185f33.js"),
        Ae = fa(["wp2_enc-89317929.wasm"]);
    var te = Object.freeze({
        __proto__: null,
        main: ee,
        deps: Ae
    });

    function ne(e) {
        return e.startsWith(FILE_PATH + "demo-")
    }
    let ae = new Set([s, ...i, r, ...c, o, ...l]);
    ae = function(e, A) {
        const t = new Set(e);
        for (const e of A) t.delete(e);
        return t
    }(ae, new Set([a, ..._.filter((e => e.endsWith(".js") || ne(e))), N, A.swUrl]));
    const _e = ["/", ...ae],
        se = (async () => {
            const [A, a, _, s] = await Promise.all([e.checkThreadsSupport(), e.simd(), ...[t, n].map((async e => {
                if (!self.createImageBitmap) return !1;
                const A = await fetch(e),
                    t = await A.blob();
                return createImageBitmap(t).then((() => !0), (() => !1))
            }))]), i = [];

            function r(e) {
                i.push(e.main, ...e.deps)
            }
            return r(d), s || r(p), _ || r(P), r(A ? h : G), r(A && a ? Y : A ? g : B), r(A ? O : k), r(a ? Q : X), r(A && a ? V : A ? $ : te), [...new Set(i)]
        })();

    function ie(e) {
        const A = e.request.formData();
        e.respondWith(Response.redirect("./?share-target")), e.waitUntil(async function() {
            var t;
            await (t = "share-ready", new Promise((e => {
                oe.has(t) || oe.set(t, []), oe.get(t).push(e)
            })));
            const n = await self.clients.get(e.resultingClientId),
                a = (await A).get("file");
            n.postMessage({
                file: a,
                action: "load-image"
            })
        }())
    }

    function re(e) {
        return e.map((e => new Request(e, {
            cache: "no-cache"
        })))
    }
    async function ce(e) {
        return (await caches.open(e)).addAll(re(await se))
    }
    const oe = new Map;
    self.addEventListener("message", (e => {
        const A = oe.get(e.data);
        if (A) {
            oe.delete(e.data);
            for (const e of A) e()
        }
    }));
    const le = "static-" + VERSION,
        Ne = "dynamic",
        Ee = [le, Ne];
    self.addEventListener("install", (e => {
        e.waitUntil(async function() {
            const e = [];
            e.push(async function(e) {
                return (await caches.open(e)).addAll(re(_e))
            }(le)), await A.get("user-interacted") && e.push(ce(le)), await Promise.all(e)
        }())
    })), self.addEventListener("activate", (e => {
        self.clients.claim(), e.waitUntil(async function() {
            const e = (await caches.keys()).map((e => {
                if (!Ee.includes(e)) return caches.delete(e)
            }));
            await Promise.all(e)
        }())
    })), self.addEventListener("fetch", (e => {
        const A = new URL(e.request.url);
        if (A.origin === location.origin)
            if ("/editor" !== A.pathname) {
                if ("/" === A.pathname && A.searchParams.has("share-target") && "POST" === e.request.method) ie(e);
                else if ("GET" === e.request.method) return ne(A.pathname) ? (function(e, A) {
                        e.respondWith(async function() {
                            const {
                                request: t
                            } = e, n = await caches.match(t);
                            if (n) return n;
                            const a = await fetch(t),
                                _ = a.clone();
                            return e.waitUntil(async function() {
                                const e = await caches.open(A);
                                await e.put(t, _)
                            }()), a
                        }())
                    }(e, Ne), void
                    function(e, A, t) {
                        e.waitUntil(async function() {
                            const e = await caches.open(A),
                                n = (await e.keys()).map((A => {
                                    const n = new URL(A.url).pathname.slice(1);
                                    if (!t.includes(n)) return e.delete(A)
                                }));
                            await Promise.all(n)
                        }())
                    }(e, Ne, ASSETS)) : void
                function(e) {
                    e.respondWith(async function() {
                        return await caches.match(e.request, {
                            ignoreSearch: !0
                        }) || fetch(e.request)
                    }())
                }(e)
            } else e.respondWith(Response.redirect("./"))
    })), self.addEventListener("message", (e => {
        switch (e.data) {
            case "cache-all":
                e.waitUntil(ce(le));
                break;
            case "skip-waiting":
                self.skipWaiting()
        }
    }))
}));
