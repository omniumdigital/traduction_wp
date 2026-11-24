import { Button, Card, CardBody, SelectControl, TextControl } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { useCallback, useEffect, useState } from "@wordpress/element";
import { downloadGlossary, uploadGlossary } from "./api";

export function Glossary({ request }) {
	const [items, setItems] = useState([]);
	const [form, setForm] = useState({ term: "", target_lang: "", translation: "", rule: "translate" });
	const [message, setMessage] = useState("");
	const [languages, setLanguages] = useState([]);
	useEffect(() => {
		request({ path: "/settings" })
			.then((response) => {
				const all = response.languages || [];
				const targets = response.settings?.target_langs || [];
				const configured = all.filter((item) => targets.includes(item.code));
				setLanguages(configured.length ? configured : all);
			})
			.catch(() => {});
	}, [request]);
	const languageName = (code) => languages.find((item) => item.code === code)?.name || code;
	const load = useCallback(
		() =>
			request({ path: "/glossary" })
				.then(setItems)
				.catch(() => {}),
		[request],
	);
	useEffect(() => {
		load();
	}, [load]);
	const add = async () => {
		await request({ path: "/glossary", method: "POST", data: form });
		setForm({ term: "", target_lang: "", translation: "", rule: "translate" });
		load();
	};
	const importFile = async (event) => {
		const file = event.target.files?.[0];
		if (!file) return;
		if (file.size > 1048576) {
			setMessage(__("CSV files must not exceed 1 MB.", "omnium-translate"));
			return;
		}
		await uploadGlossary(request, file);
		setMessage(__("Glossary imported.", "omnium-translate"));
		load();
	};
	return (
		<Card>
			<CardBody>
				<h1>{__("Glossary", "omnium-translate")}</h1>
				<div className="omtr-form-row">
					<TextControl
						label={__("Term", "omnium-translate")}
						value={form.term}
						onChange={(term) => setForm({ ...form, term })}
					/>
					<SelectControl
						label={__("Language", "omnium-translate")}
						value={form.target_lang}
						options={[
							{ label: __("Select a language", "omnium-translate"), value: "" },
							...languages.map((item) => ({ label: item.name, value: item.code })),
						]}
						onChange={(target_lang) => setForm({ ...form, target_lang })}
					/>
					<TextControl
						label={__("Translation", "omnium-translate")}
						value={form.translation}
						onChange={(translation) => setForm({ ...form, translation })}
					/>
					<SelectControl
						label={__("Rule", "omnium-translate")}
						value={form.rule}
						options={[
							{ label: __("Always translate", "omnium-translate"), value: "translate" },
							{ label: __("Never translate", "omnium-translate"), value: "keep" },
						]}
						onChange={(rule) => setForm({ ...form, rule })}
					/>
					<Button variant="primary" onClick={add} disabled={!form.term || !form.target_lang}>
						{__("Add", "omnium-translate")}
					</Button>
				</div>
				<div className="omtr-actions">
					<Button onClick={() => downloadGlossary(request)}>{__("Export CSV", "omnium-translate")}</Button>
					<Button onClick={() => document.getElementById("omtr-glossary-import").click()}>
						{__("Import CSV", "omnium-translate")}
					</Button>
					<input
						id="omtr-glossary-import"
						className="screen-reader-text"
						type="file"
						accept=".csv,text/csv"
						onChange={importFile}
					/>
				</div>
				{message && <p role="status">{message}</p>}
				<table className="widefat">
					<thead>
						<tr>
							<th>{__("Term", "omnium-translate")}</th>
							<th>{__("Language", "omnium-translate")}</th>
							<th>{__("Translation", "omnium-translate")}</th>
							<th>{__("Rule", "omnium-translate")}</th>
							<th />
						</tr>
					</thead>
					<tbody>
						{items.map((item) => (
							<tr key={item.id}>
								<td>{item.term}</td>
								<td>{languageName(item.target_lang)}</td>
								<td>{item.translation}</td>
								<td>{item.rule}</td>
								<td>
									<Button
										isDestructive
										onClick={async () => {
											await request({ path: `/glossary/${item.id}`, method: "DELETE" });
											load();
										}}
									>
										{__("Delete", "omnium-translate")}
									</Button>
								</td>
							</tr>
						))}
					</tbody>
				</table>
			</CardBody>
		</Card>
	);
}
