// Measure external front-end resources in rendered HTML and browser requests.
// Usage: OMTR_URL=http://localhost:8883/en/ PLAYWRIGHT_MODULE=... node front-resources.cjs
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || "playwright");

const url = process.env.OMTR_URL;
if (!url) throw new Error("OMTR_URL is required");

async function main() {
	const browser = await chromium.launch({ headless: true });
	const page = await browser.newPage();
	const requests = new Set();
	page.on("request", (request) => requests.add(request.url()));
	try {
		const response = await page.goto(url, { waitUntil: "load", timeout: 60000 });
		await page.waitForTimeout(1500);
		const assets = await page.evaluate(() =>
			[...document.querySelectorAll("script[src], link[href], img[src], iframe[src]")]
				.map((element) => element.src || element.href)
				.filter(Boolean),
		);
		const origin = new URL(url).origin;
		const external = (items) =>
			[...new Set(items)]
				.filter((item) => /^https?:/.test(item) && new URL(item).origin !== origin)
				.sort();
		console.log(
			JSON.stringify({
				url,
				status: response?.status() || 0,
				html: external(assets),
				network: external([...requests]),
			}),
		);
	} finally {
		await browser.close();
	}
}

main().catch((error) => {
	console.error(error.message);
	process.exitCode = 1;
});
