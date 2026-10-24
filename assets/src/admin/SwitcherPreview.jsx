import { __ } from "@wordpress/i18n";

export function SwitcherPreview({ settings, flagsUrl }) {
	const switcher = settings.switcher || {};
	const colors = switcher.colors || {};
	const flagCode = settings.flags?.en || "gb";
	const flagSource = flagsUrl ? `${flagsUrl.replace(/\/$/, "")}/${flagCode}.svg` : "";
	const style = {
		"--omtr-bg": colors.bg || "#ffffff",
		"--omtr-fg": colors.fg || "#0a0a0a",
		"--omtr-hover": colors.hover || "#f4f4f5",
		"--omtr-border": colors.border || "#e4e4e7",
		"--omtr-radius": `${switcher.radius || 8}px`,
		"--omtr-flag-size": `${switcher.flag_size || 18}px`,
	};
	const flag = flagSource ? (
		<img className="omtr-switcher__flag" src={flagSource} alt="" width="24" height="18" />
	) : null;
	return (
		<div
			className={`omtr-switcher omtr-switcher--${switcher.variant || "dropdown"} omtr-switcher--${
				switcher.display || "flag-name"
			}`}
			style={style}
		>
			{switcher.variant === "inline" ? (
				<ul className="omtr-switcher__list">
					<li className="omtr-switcher__item">
						<a className="omtr-switcher__link is-active" href="#preview" lang="en" aria-current="page">
							{flag}
							<span className="omtr-switcher__label">{__("English", "omnium-translate")}</span>
							<span className="omtr-switcher__check" aria-hidden="true">
								✓
							</span>
						</a>
					</li>
					<li className="omtr-switcher__item">
						<a className="omtr-switcher__link" href="#preview" lang="fr">
							{__("French", "omnium-translate")}
						</a>
					</li>
				</ul>
			) : (
				<details className="omtr-switcher__dropdown" open>
					<summary className="omtr-switcher__trigger" aria-haspopup="true">
						{flag}
						<span className="omtr-switcher__label">{__("English", "omnium-translate")}</span>
						<span className="omtr-switcher__chevron" aria-hidden="true" />
					</summary>
					<ul className="omtr-switcher__list">
						<li className="omtr-switcher__item">
							<a className="omtr-switcher__link is-active" href="#preview" lang="en" aria-current="page">
								{flag}
								<span className="omtr-switcher__label">{__("English", "omnium-translate")}</span>
								<span className="omtr-switcher__check" aria-hidden="true">
									✓
								</span>
							</a>
						</li>
						<li className="omtr-switcher__item">
							<a className="omtr-switcher__link" href="#preview" lang="fr">
								{__("French", "omnium-translate")}
							</a>
						</li>
					</ul>
				</details>
			)}
		</div>
	);
}
