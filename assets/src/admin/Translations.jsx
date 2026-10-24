import { Button, Card, CardBody, SelectControl, TextControl, TextareaControl } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { useCallback, useEffect, useState } from "@wordpress/element";

function SegmentRow({ item, request, onSaved }) {
	const [text, setText] = useState(item.text || item.translation || "");
	const [busy, setBusy] = useState(false);
	const save = async () => {
		setBusy(true);
		await request({ path: `/segments/${item.id}`, method: "PUT", data: { target_lang: item.target_lang, text } });
		setBusy(false);
		onSaved();
	};
	const lock = async () => {
		setBusy(true);
		await request({
			path: `/segments/${item.id}/lock`,
			method: item.status === "locked" ? "DELETE" : "POST",
			data: { target_lang: item.target_lang },
		});
		setBusy(false);
		onSaved();
	};
	return (
		<div className="omtr-segment">
			<div>
				<small>
					{item.target_lang} · {item.status}
				</small>
				<p>{item.source_text}</p>
			</div>
			<TextareaControl
				label={__("Translation", "omnium-translate")}
				value={text}
				onChange={setText}
				hideLabelFromVision
			/>
			<Button disabled={busy} onClick={save}>
				{__("Save", "omnium-translate")}
			</Button>
			<Button disabled={busy} onClick={lock}>
				{item.status === "locked" ? __("Unlock", "omnium-translate") : __("Lock", "omnium-translate")}
			</Button>
		</div>
	);
}

export function Translations({ request }) {
	const [items, setItems] = useState([]);
	const [search, setSearch] = useState("");
	const [language, setLanguage] = useState("");
	const load = useCallback(
		() =>
			request({ path: `/segments?search=${encodeURIComponent(search)}${language ? `&target_lang=${language}` : ""}` })
				.then(setItems)
				.catch(() => {}),
		[request, search, language],
	);
	useEffect(() => {
		load();
	}, [load]);
	return (
		<Card>
			<CardBody>
				<h1>{__("Translations", "omnium-translate")}</h1>
				<div className="omtr-toolbar">
					<TextControl
						label={__("Filter segments", "omnium-translate")}
						value={search}
						onChange={setSearch}
						hideLabelFromVision
					/>
					<SelectControl
						label={__("Language", "omnium-translate")}
						value={language}
						options={[{ label: __("All languages", "omnium-translate"), value: "" }]}
						onChange={setLanguage}
						hideLabelFromVision
					/>
				</div>
				{items.map((item) => (
					<SegmentRow key={`${item.id}-${item.target_lang}`} item={item} request={request} onSaved={load} />
				))}
			</CardBody>
		</Card>
	);
}
