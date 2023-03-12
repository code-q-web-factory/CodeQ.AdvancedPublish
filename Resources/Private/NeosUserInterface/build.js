require("esbuild").build({
	logLevel: "info",
	bundle: true,
	watch: process.argv.includes("--watch"),
	target: "es2020",
	entryPoints: {"Plugin": "src/index.js"},
	loader: {
		".js": "tsx",
	},
	outdir: "../../Public/NeosUserInterface",
	plugins: [require("@mhsdesign/esbuild-neos-ui-extensibility").neosUiExtensibility()]
})
