import { Notice, Spinner } from "@wordpress/components";
import { __ } from "@wordpress/i18n";

export function Loading() {
	return (
		<div className="omtr-loading">
			<Spinner /> <span>{__("Loading…", "omnium-translate")}</span>
		</div>
	);
}

export function ErrorNotice({ error, onDismiss }) {
	if (!error) {
		return null;
	}
	return (
		<Notice status="error" isDismissible={Boolean(onDismiss)} onRemove={onDismiss}>
			{error}
		</Notice>
	);
}
