import { Button, Notice } from "@wordpress/components";
import { useSelect } from "@wordpress/data";
import { PluginDocumentSettingPanel } from "@wordpress/editor";
import { useState } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
import apiFetch from "@wordpress/api-fetch";
import { registerPlugin } from "@wordpress/plugins";

function TranslatePanel() {
	const postId = useSelect((select) => select("core/editor").getCurrentPostId(), []);
	const [status, setStatus] = useState("");
	const [busy, setBusy] = useState(false);
	const translate = async () => {
		setBusy(true);
		setStatus("");
		try {
			await apiFetch({ path: "/omtr/v1/translate", method: "POST", data: { post_id: postId } });
			setStatus("queued");
		} catch {
			setStatus("error");
		} finally {
			setBusy(false);
		}
	};
	return (
		<PluginDocumentSettingPanel name="omtr-translate" title={__("Translation", "omnium-translate")}>
			<Button variant="secondary" disabled={!postId || busy} onClick={translate}>
				{__("Translate this page", "omnium-translate")}
			</Button>
			{status && (
				<Notice status={status === "queued" ? "success" : "error"} isDismissible={false}>
					{status === "queued"
						? __("Translation queued.", "omnium-translate")
						: __("Translation could not be queued.", "omnium-translate")}
				</Notice>
			)}
		</PluginDocumentSettingPanel>
	);
}

registerPlugin("omtr-translate", { render: TranslatePanel });
