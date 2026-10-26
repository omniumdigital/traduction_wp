// Parcours admin headless pour la recette v1 (Gutenberg, Elementor, Oxygen).
// Usage (module playwright résolu hors des dépendances du projet) :
//   OMTR_BASE=http://localhost:8881 OMTR_LOCALE=fr_FR OMTR_TIMED=1 \
//   PLAYWRIGHT_MODULE=<chemin>/node_modules/playwright node admin-playwright.cjs
//
// Assistant complet (langues, fournisseur, clé factice, test, estimation,
// lancement, menu), puis chaque onglet sans erreur console. Vérifie que la clé
// disparaît du DOM après l'assistant et ne figure dans aucune réponse réseau.
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || "playwright");

const BASE = process.env.OMTR_BASE || "http://localhost:8881";
const LOCALE = process.env.OMTR_LOCALE || "fr_FR";
const TIMED = process.env.OMTR_TIMED === "1";
const FR = LOCALE.startsWith("fr");
const T = {
	headingWizard: FR ? "Configurer vos traductions" : "Set up your translations",
	sourceLabel: FR ? "Langue source" : "Source language",
	continue: FR ? "Continuer" : "Continue",
	headingProvider: FR ? "Connecter un fournisseur IA" : "Connect an AI provider",
	keyLabel: FR ? "Clé API" : "API key",
	testKey: FR ? "Tester la clé" : "Test the key",
	keyOk: FR ? "La clé API est valide." : "API key is valid.",
	headingLaunch: FR ? "Vérifier et lancer" : "Review and launch",
	launch: FR ? "Traduire le site" : "Translate the site",
	tabs: FR
		? ["Tableau de bord", "Traductions", "Glossaire", "Réglages"]
		: ["Dashboard", "Translations", "Glossary", "Settings"],
};
const SECRET = "local-mock-" + "x".repeat(20);

const run = async () => {
	const browser = await chromium.launch({ headless: true });
	const page = await browser.newPage();
	const errors = [];
	const thirdPartyErrors = [];
	const leaks = [];
	const pendingResponses = new Set();
	page.on("console", (message) => {
		if (message.type() === "error") {
			const location = message.location();
			const detail = `${message.text()} @${location.url || "?"}:${location.lineNumber || 0}`;
			if (detail.includes("/wordpress-seo.latest-stable/")) thirdPartyErrors.push(detail);
			else errors.push(detail);
		}
	});
	page.on("pageerror", (error) => errors.push("pageerror: " + error.message));
	page.on("response", (response) => {
		// Seules les réponses REST du plugin peuvent refléter la clé saisie.
		// Ignorer le reste évite d'attendre des flux WordPress longs (heartbeat,
		// téléchargements, réponses tierces) à la fin du parcours.
		if (!response.url().includes("/wp-json/omtr/")) return;
		const check = response
			.text()
			.then((body) => {
				if (body && body.includes(SECRET)) leaks.push(response.url());
			})
			.catch(() => {})
			.finally(() => pendingResponses.delete(check));
		pendingResponses.add(check);
	});

	const domSecret = () =>
		page.evaluate((secret) => {
			const html = document.documentElement.outerHTML;
			if (html.includes(secret)) return "outerHTML";
			// React met à jour la propriété value, pas l'attribut : vérifier aussi les valeurs vives.
			for (const input of document.querySelectorAll("input, textarea")) {
				if ((input.value || "").includes(secret)) return "input.value";
			}
			return "";
		}, SECRET);

	try {
		const start = Date.now();
		console.log("STEP=login");
		await page.goto(BASE + "/wp-login.php", { waitUntil: "domcontentloaded" });
		await page.locator("#user_login").fill("admin");
		await page.locator("#user_pass").fill("password");
		await page.locator("#wp-submit").click();
		await page.waitForLoadState("load");
		// Login redirects through wp-admin; third-party dashboard widgets may log
		// warnings before the Omnium interface is opened.
		errors.length = 0;

		// Assistant — écran 1 : langues.
		console.log("STEP=wizard-1");
		await page.goto(BASE + "/wp-admin/admin.php?page=omnium-translate", { waitUntil: "load" });
		await page.getByRole("heading", { name: T.headingWizard }).waitFor({ timeout: 20000 });
		await page.getByLabel(T.sourceLabel).selectOption("fr");
		await page.getByRole("checkbox", { name: "English", exact: true }).check();
		await page.getByRole("checkbox", { name: "Español", exact: true }).check();
		await page.getByRole("button", { name: T.continue, exact: true }).click();

		// Écran 2 : fournisseur + clé factice + test obligatoire.
		console.log("STEP=wizard-2");
		await page.getByRole("heading", { name: T.headingProvider }).waitFor();
		await page.getByRole("button", { name: /Anthropic Claude/ }).click();
		await page.getByLabel(T.keyLabel).fill(SECRET);
		await page.getByRole("button", { name: T.testKey }).click();
		await page.getByText(T.keyOk, { exact: false }).first().waitFor({ timeout: 20000 });
		const domAfterSave = await domSecret();
		await page.getByRole("button", { name: T.continue, exact: true }).click();

		// Écran 3 : estimation puis lancement (menu coché par défaut).
		console.log("STEP=wizard-3");
		await page.getByRole("heading", { name: T.headingLaunch }).waitFor();
		const launch = page.getByRole("button", { name: T.launch });
		await launch.waitFor({ timeout: 60000 });
		await launch.click();

		// Onglets.
		console.log("STEP=tabs");
		await page.getByRole("tab", { name: T.tabs[0] }).waitFor({ timeout: 30000 });
		const seconds = ((Date.now() - start) / 1000).toFixed(1);
		const domAfterWizard = await domSecret();
		for (const name of T.tabs) {
			await page.getByRole("tab", { name }).click();
			await page.locator('[role="tabpanel"]').first().waitFor();
			await page.waitForTimeout(700); // laisse les requêtes REST de l'onglet aboutir
			console.log(`TAB=${name}:errors=${errors.length}`);
		}
		await Promise.allSettled([...pendingResponses]);

		if (TIMED) console.log(`INSTALL_WIZARD_BROWSER_SECONDS=${seconds}`);
		console.log(`KEY_IN_DOM_AFTER_SAVE=${domAfterSave || "false"}`);
		console.log(`KEY_IN_DOM_AFTER_WIZARD=${domAfterWizard || "false"}`);
		console.log(`KEY_IN_API_RESPONSES=${leaks.length}${leaks.length ? ":" + leaks.join(",") : ""}`);
		console.log(`CONSOLE_ERRORS=${errors.length}`);
		console.log(`THIRD_PARTY_CONSOLE_ERRORS=${thirdPartyErrors.length}`);
		for (const error of errors.slice(0, 10)) console.log("CONSOLE_ERROR=" + error);
		return errors.length || leaks.length || domAfterWizard ? 1 : 0;
	} catch (error) {
		console.error("PLAYWRIGHT_FAILURE=" + error.message.split("\n")[0]);
		try {
			const content = await page.content();
			console.error("PAGE_TEXT=" + (await page.evaluate(() => document.body.innerText).catch(() => "")).slice(0, 500).replace(/\n/g, " | "));
			if (content.length < 200) console.error("PAGE_HTML=" + content.slice(0, 300));
			console.error("CONSOLE_SO_FAR=" + errors.slice(-5).join(" || "));
		} catch {}
		return 1;
	} finally {
		await browser.close();
	}
};

const watchdog = setTimeout(() => {
	console.error("PLAYWRIGHT_FAILURE=watchdog 6 min");
	process.exit(2);
}, 360000);

run().then((code) => {
	clearTimeout(watchdog);
	process.exitCode = code;
});
