import { Notice } from "@wordpress/components";
import { __ } from "@wordpress/i18n";

export function ProviderPicker({ providers, value, onChange }) {
	const provider = providers.find((item) => item.id === value);
	return (
		<div className="omtr-provider-grid">
			{providers.map((item) => (
				<button
					type="button"
					key={item.id}
					className={`omtr-provider ${item.id === value ? "is-selected" : ""}`}
					onClick={() => onChange(item.id)}
					aria-pressed={item.id === value}
				>
					<strong>{item.label}</strong>
					<span className="omtr-region">
						{item.region === "eu" ? __("EU", "omnium-translate") : __("Outside the EU", "omnium-translate")}
					</span>
					{item.free_tier && <small>{__("Free tier: provider may reuse submitted data.", "omnium-translate")}</small>}
				</button>
			))}
			{provider?.free_tier && (
				<Notice status="warning" isDismissible={false}>
					{__(
						"This free tier may reuse submitted content. Check the provider terms before enabling it.",
						"omnium-translate",
					)}
				</Notice>
			)}
		</div>
	);
}
