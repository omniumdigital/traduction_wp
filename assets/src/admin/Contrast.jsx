import { Notice } from "@wordpress/components";
import { __, sprintf } from "@wordpress/i18n";
import { contrastRatio } from "./contrast-util";

export function Contrast({ foreground, background }) {
	const ratio = contrastRatio(foreground, background);
	return (
		<Notice status={ratio < 4.5 ? "warning" : "success"} isDismissible={false}>
			{sprintf(__("Contrast ratio: %s:1", "omnium-translate"), ratio.toFixed(2))}
			{ratio < 4.5 && ` — ${__("Use at least 4.5:1 for normal text.", "omnium-translate")}`}
		</Notice>
	);
}
