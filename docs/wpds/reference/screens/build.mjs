import * as esbuild from "esbuild";
import { fileURLToPath } from "node:url";
import { dirname, resolve } from "node:path";

const here = dirname(fileURLToPath(import.meta.url));

await esbuild.build({
  entryPoints: [resolve(here, "src/index.jsx")],
  bundle: true,
  outdir: resolve(here, "build"),
  entryNames: "app",
  assetNames: "[name]",
  jsx: "automatic",
  loader: { ".js": "jsx", ".jsx": "jsx" },
  define: { "process.env.NODE_ENV": JSON.stringify("production") },
  logLevel: "info"
});

console.log("built screens/build/app.js and app.css");
