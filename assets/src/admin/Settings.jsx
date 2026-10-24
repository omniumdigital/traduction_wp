import {
	Button,
	ColorPicker,
	Panel,
	PanelBody,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { useEffect, useState } from "@wordpress/element";
import { Loading } from "./common";
import { Contrast } from "./Contrast";
import { SwitcherPreview } from "./SwitcherPreview";

export function Settings({ request, data: initialData }) {
	const [data, setData] = useState(initialData || null);
	const [providers, setProviders] = useState([]);
	const [key, setKey] = useState("");
	useEffect(() => {
		if (initialData) return;
		Promise.all([request({ path: "/settings" }), request({ path: "/providers" })])
			.then(([settings, available]) => {
				setData(settings);
				setProviders(available || []);
			})
			.catch(() => {});
	}, [initialData, request]);
	useEffect(() => {
		if (initialData)
			request({ path: "/providers" })
				.then(setProviders)
				.catch(() => {});
	}, [initialData, request]);
	const save = async (patch) => setData(await request({ path: "/settings", method: "PUT", data: patch }));
	if (!data) return <Loading />;
	const settings = data.settings || {};
	const switcher = settings.switcher || {};
	const colors = switcher.colors || {};
	const languages = data.languages || [];
	const flags = data.flags || [];
	const updateSwitcher = (patch) => save({ switcher: { ...switcher, ...patch } });
	const updateColors = (patch) => updateSwitcher({ colors: { ...colors, ...patch } });
	return (
		<div className="omtr-settings">
			<Panel>
				<PanelBody title={__("Languages and translation", "omnium-translate")} initialOpen>
					<SelectControl
						label={__("Source language", "omnium-translate")}
						value={settings.source_lang || ""}
						options={languages.map((item) => ({ label: item.name, value: item.code }))}
						onChange={(source_lang) => save({ source_lang })}
					/>
					<TextControl
						label={__("Target languages (comma separated)", "omnium-translate")}
						value={(settings.target_langs || []).join(", ")}
						onChange={(value) =>
							save({
								target_langs: value
									.split(",")
									.map((item) => item.trim())
									.filter(Boolean),
							})
						}
					/>
					<ToggleControl
						label={__("Translate automatically when content changes", "omnium-translate")}
						checked={settings.auto_translate}
						onChange={(auto_translate) => save({ auto_translate })}
					/>
					<ToggleControl
						label={__("Translate unknown segments on the fly", "omnium-translate")}
						checked={settings.on_the_fly}
						onChange={(on_the_fly) => save({ on_the_fly })}
					/>
					<ToggleControl
						label={__("Enable visual translation editing", "omnium-translate")}
						checked={Boolean(settings.visual_editor)}
						onChange={(visual_editor) => save({ visual_editor })}
					/>
				</PanelBody>
				<PanelBody title={__("Provider and limits", "omnium-translate")} initialOpen>
					<SelectControl
						label={__("Provider", "omnium-translate")}
						value={settings.provider || ""}
						options={providers.map((item) => ({
							label: `${item.label} (${
								item.region === "eu" ? __("EU", "omnium-translate") : __("Outside the EU", "omnium-translate")
							})`,
							value: item.id,
						}))}
						onChange={(provider) => save({ provider })}
					/>
					<TextControl
						label={__("Monthly word cap", "omnium-translate")}
						type="number"
						value={settings.monthly_word_cap || 0}
						onChange={(monthly_word_cap) => save({ monthly_word_cap: Number(monthly_word_cap) })}
					/>
					<TextControl
						label={__("Hourly quota", "omnium-translate")}
						type="number"
						value={settings.hourly_quota || 100}
						onChange={(hourly_quota) => save({ hourly_quota: Number(hourly_quota) })}
					/>
					<TextControl
						type="password"
						label={__("API key", "omnium-translate")}
						value={key}
						onChange={setKey}
						autoComplete="new-password"
						help={
							providers.find((item) => item.id === settings.provider)?.key?.masked ||
							__("The key is never shown in full.", "omnium-translate")
						}
					/>
					<Button
						onClick={async () => {
							await request({ path: `/providers/${settings.provider}/key`, method: "PUT", data: { key } });
							setKey("");
						}}
					>
						{__("Save key", "omnium-translate")}
					</Button>
				</PanelBody>
				<PanelBody title={__("SEO", "omnium-translate")} initialOpen>
					<ToggleControl
						label={__("Prevent indexing languages with incomplete translations", "omnium-translate")}
						checked={Boolean(settings.noindex_partial)}
						onChange={(noindex_partial) => save({ noindex_partial })}
					/>
				</PanelBody>
				<PanelBody title={__("Site tone and context", "omnium-translate")} initialOpen>
					<TextControl
						label={__("Tone", "omnium-translate")}
						value={settings.site_tone || ""}
						onChange={(site_tone) => setData({ ...data, settings: { ...settings, site_tone } })}
						onBlur={() => save({ site_tone: settings.site_tone })}
					/>
					<TextareaControl
						label={__("Context", "omnium-translate")}
						value={settings.site_context || ""}
						onChange={(site_context) => setData({ ...data, settings: { ...settings, site_context } })}
						onBlur={() => save({ site_context: settings.site_context })}
					/>
				</PanelBody>
				<PanelBody title={__("Language flags", "omnium-translate")} initialOpen>
					{languages.map((language) => (
						<SelectControl
							key={language.code}
							label={language.name}
							value={settings.flags?.[language.code] || language.flags?.[0] || ""}
							options={(language.flags || flags).map((flag) => ({ label: flag.toUpperCase(), value: flag }))}
							onChange={(flag) => save({ flags: { ...(settings.flags || {}), [language.code]: flag } })}
						/>
					))}
				</PanelBody>
				<PanelBody title={__("Language selector", "omnium-translate")} initialOpen>
					<SelectControl
						label={__("Variant", "omnium-translate")}
						value={switcher.variant || "dropdown"}
						options={[
							{ label: __("Dropdown", "omnium-translate"), value: "dropdown" },
							{ label: __("Inline", "omnium-translate"), value: "inline" },
							{ label: __("Floating", "omnium-translate"), value: "floating" },
						]}
						onChange={(variant) => updateSwitcher({ variant })}
					/>
					<SelectControl
						label={__("Display", "omnium-translate")}
						value={switcher.display || "flag-name"}
						options={[
							{ label: __("Flag and name", "omnium-translate"), value: "flag-name" },
							{ label: __("Flag only", "omnium-translate"), value: "flag" },
							{ label: __("Name only", "omnium-translate"), value: "name" },
							{ label: __("Language code", "omnium-translate"), value: "code" },
						]}
						onChange={(display) => updateSwitcher({ display })}
					/>
					<ToggleControl
						label={__("Automatic dark mode", "omnium-translate")}
						checked={switcher.dark_mode}
						onChange={(dark_mode) => updateSwitcher({ dark_mode })}
					/>
					<div className="omtr-colors">
						<ColorPicker color={colors.bg || "#ffffff"} onChangeComplete={({ hex }) => updateColors({ bg: hex })} />
						<ColorPicker color={colors.fg || "#0a0a0a"} onChangeComplete={({ hex }) => updateColors({ fg: hex })} />
						<ColorPicker
							color={colors.hover || "#f4f4f5"}
							onChangeComplete={({ hex }) => updateColors({ hover: hex })}
						/>
						<ColorPicker
							color={colors.border || "#e4e4e7"}
							onChangeComplete={({ hex }) => updateColors({ border: hex })}
						/>
					</div>
					<TextControl
						label={__("Corner radius", "omnium-translate")}
						type="number"
						min="0"
						max="24"
						value={switcher.radius || 8}
						onChange={(radius) => updateSwitcher({ radius: Number(radius) })}
					/>
					<TextControl
						label={__("Flag size", "omnium-translate")}
						type="number"
						min="12"
						max="32"
						value={switcher.flag_size || 18}
						onChange={(flag_size) => updateSwitcher({ flag_size: Number(flag_size) })}
					/>
					<Contrast foreground={colors.fg || "#0a0a0a"} background={colors.bg || "#ffffff"} />
					<SwitcherPreview settings={settings} flagsUrl={window.omtrAdmin?.flagsUrl} />
				</PanelBody>
				<PanelBody title={__("Configuration", "omnium-translate")} initialOpen>
					<div className="omtr-actions">
						<Button
							onClick={() =>
								window.open(
									`${window.location.origin}${
										window.omtrAdmin?.restNamespace ? `/wp-json/${window.omtrAdmin.restNamespace}` : ""
									}/config/export`,
									"_blank",
								)
							}
						>
							{__("Export configuration", "omnium-translate")}
						</Button>
						<Button onClick={() => document.getElementById("omtr-config-import").click()}>
							{__("Import configuration", "omnium-translate")}
						</Button>
						<input
							id="omtr-config-import"
							className="screen-reader-text"
							type="file"
							accept="application/json"
							onChange={async (event) => {
								const file = event.target.files?.[0];
								if (file && file.size <= 1048576)
									await request({ path: "/config/import", method: "POST", data: await file.text() });
							}}
						/>
					</div>
					<ToggleControl
						label={__("Disable update checks", "omnium-translate")}
						checked={settings.updates_disabled}
						onChange={(updates_disabled) => save({ updates_disabled })}
					/>
					<ToggleControl
						label={__("Delete all data on uninstall", "omnium-translate")}
						checked={settings.uninstall_purge}
						onChange={(uninstall_purge) => save({ uninstall_purge })}
					/>
				</PanelBody>
			</Panel>
		</div>
	);
}
