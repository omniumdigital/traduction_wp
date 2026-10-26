import { Button, Card, CardBody, CheckboxControl, Notice, SelectControl, TextControl } from "@wordpress/components";
import { __, sprintf } from "@wordpress/i18n";
import { useEffect, useState } from "@wordpress/element";
import { Loading } from "./common";
import { ProviderPicker } from "./ProviderPicker";

export function Wizard({ data, request, onComplete }) {
	const [step, setStep] = useState(0);
	const [settings, setSettings] = useState({ source_lang: "", target_langs: [], provider: "", model: "" });
	const [providers, setProviders] = useState([]);
	const [languages, setLanguages] = useState(data.languages || []);
	const [key, setKey] = useState("");
	const [keyStatus, setKeyStatus] = useState(null);
	const [estimate, setEstimate] = useState(null);
	const [insertMenu, setInsertMenu] = useState(true);

	useEffect(() => {
		Promise.all([request({ path: "/settings" }), request({ path: "/providers" })])
			.then(([response, available]) => {
				setSettings(response.settings || {});
				setLanguages(response.languages || data.languages || []);
				setProviders(available || []);
			})
			.catch(() => {});
	}, [request]);

	const updateTargets = (code) =>
		setSettings((current) => ({
			...current,
			target_langs: current.target_langs?.includes(code)
				? current.target_langs.filter((item) => item !== code)
				: [...(current.target_langs || []), code],
		}));
	const provider = providers.find((item) => item.id === settings.provider);
	const saveLanguages = async () => {
		await request({
			path: "/settings",
			method: "PUT",
			data: {
				source_lang: settings.source_lang,
				target_langs: settings.target_langs,
			},
		});
		setStep(1);
	};
	const testKey = async () => {
		setKeyStatus(null);
		try {
			if (key) {
				const storedKey = await request({ path: `/providers/${settings.provider}/key`, method: "PUT", data: { key } });
				setKey("");
				setProviders((current) =>
					current.map((item) =>
						item.id === settings.provider ? { ...item, key: { ...item.key, ...storedKey } } : item,
					),
				);
			}
			setKeyStatus(await request({ path: `/providers/${settings.provider}/test`, method: "POST" }));
		} catch (reason) {
			setKeyStatus({ ok: false, message: reason?.message || __("Key test failed.", "omnium-translate") });
		}
	};
	const loadEstimate = async () => {
		setEstimate(
			await request({ path: "/estimate", method: "POST", data: { all: true, languages: settings.target_langs } }),
		);
		setStep(2);
	};
	const finish = async () => {
		await request({ path: "/translate/all", method: "POST", data: { languages: settings.target_langs } });
		await request({ path: "/wizard/complete", method: "POST", data: { insert_menu: insertMenu } });
		onComplete();
	};
	return (
		<div className="omtr-wizard">
			<fieldset className="omtr-stepper">
				<legend className="screen-reader-text">{__("Setup progress", "omnium-translate")}</legend>
				{[
					__("Languages", "omnium-translate"),
					__("AI provider", "omnium-translate"),
					__("Launch", "omnium-translate"),
				].map((label, index) => (
					<span className={index === step ? "is-current" : index < step ? "is-done" : ""} key={label}>
						{index + 1}. {label}
					</span>
				))}
			</fieldset>
			{step === 0 && (
				<Card>
					<CardBody>
						<h1>{__("Set up your translations", "omnium-translate")}</h1>
						<p>{__("Choose the source language and the languages you want to publish.", "omnium-translate")}</p>
						<SelectControl
							label={__("Source language", "omnium-translate")}
							value={settings.source_lang}
							options={[
								{ label: __("Select a language", "omnium-translate"), value: "" },
								...languages.map((item) => ({ label: item.name, value: item.code })),
							]}
							onChange={(source_lang) => setSettings((current) => ({ ...current, source_lang }))}
						/>
						<fieldset>
							<legend>{__("Target languages", "omnium-translate")}</legend>
							{languages
								.filter((item) => item.code !== settings.source_lang)
								.map((item) => (
									<CheckboxControl
										key={item.code}
										label={item.name}
										checked={settings.target_langs?.includes(item.code)}
										onChange={() => updateTargets(item.code)}
									/>
								))}
						</fieldset>
						<Button
							variant="primary"
							disabled={!settings.source_lang || !settings.target_langs?.length}
							onClick={saveLanguages}
						>
							{__("Continue", "omnium-translate")}
						</Button>
					</CardBody>
				</Card>
			)}
			{step === 1 && (
				<Card>
					<CardBody>
						<h1>{__("Connect an AI provider", "omnium-translate")}</h1>
						<ProviderPicker
							providers={providers}
							value={settings.provider}
							onChange={(providerValue) => {
								setSettings((current) => ({ ...current, provider: providerValue, model: "" }));
								setKeyStatus(null);
							}}
						/>
						{provider && (
							<>
								<SelectControl
									label={__("Model", "omnium-translate")}
									value={settings.model || provider.models?.find((item) => item.recommended)?.id || ""}
									options={(provider.models || []).map((item) => ({ label: item.label, value: item.id }))}
									onChange={(model) => setSettings((current) => ({ ...current, model }))}
								/>
								<p>
									<a href={provider.key_url} target="_blank" rel="noreferrer">
										{__("Create an API key", "omnium-translate")}
									</a>
								</p>
								<TextControl
									type="password"
									label={__("API key", "omnium-translate")}
									value={key}
									onChange={setKey}
									help={
										provider.key?.masked
											? sprintf(__("Stored key: %s", "omnium-translate"), provider.key.masked)
											: __("Your key is encrypted and will never be displayed in full.", "omnium-translate")
									}
									autoComplete="new-password"
								/>
								<Button variant="secondary" disabled={!key && !provider.key?.set} onClick={testKey}>
									{__("Test the key", "omnium-translate")}
								</Button>
								{keyStatus && (
									<Notice status={keyStatus.ok ? "success" : "error"} isDismissible={false}>
										{keyStatus.message}
									</Notice>
								)}
							</>
						)}
						<div className="omtr-actions">
							<Button onClick={() => setStep(0)}>{__("Back", "omnium-translate")}</Button>
							<Button variant="primary" disabled={!settings.provider || !keyStatus?.ok} onClick={loadEstimate}>
								{__("Continue", "omnium-translate")}
							</Button>
						</div>
					</CardBody>
				</Card>
			)}
			{step === 2 && (
				<Card>
					<CardBody>
						<h1>{__("Review and launch", "omnium-translate")}</h1>
						{estimate ? (
							<div className="omtr-estimate">
								<strong>
									{estimate.pages} {__("pages", "omnium-translate")}
								</strong>
								<span>
									{estimate.segments} {__("segments", "omnium-translate")}
								</span>
								<span>
									{estimate.words} {__("words", "omnium-translate")}
								</span>
								<span>
									{estimate.cost
										? `${estimate.cost} ${estimate.currency}`
										: __("Cost unavailable", "omnium-translate")}
								</span>
							</div>
						) : (
							<Loading />
						)}
						<CheckboxControl
							label={__("Add the language selector to the main menu", "omnium-translate")}
							checked={insertMenu}
							onChange={setInsertMenu}
						/>
						<p>
							{__(
								"The translation will run in the background. You can pause the queue from the dashboard.",
								"omnium-translate",
							)}
						</p>
						<div className="omtr-actions">
							<Button onClick={() => setStep(1)}>{__("Back", "omnium-translate")}</Button>
							<Button variant="primary" disabled={!estimate} onClick={finish}>
								{__("Translate the site", "omnium-translate")}
							</Button>
						</div>
					</CardBody>
				</Card>
			)}
		</div>
	);
}
