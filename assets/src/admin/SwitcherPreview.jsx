import { __ } from "@wordpress/i18n";

function renderItemContent(display, flag, label, code) {
	const showFlag = Boolean(flag) && (display === "flag-name" || display === "flag");
	if (display === "flag" && flag) {
		return flag;
	}
	const text = display === "code" || display === "flag" ? code : label;
	return (
		<>
			{showFlag && flag}
			<span className="omtr-switcher__label">{text}</span>
		</>
	);
}

export function SwitcherPreview({ settings, flagsUrl }) {
	const switcher = settings.switcher || {};
	const colors = switcher.colors || {};
	const display = switcher.display || "flag-name";
	const variant = switcher.variant || "dropdown";
	const flagCodeEn = settings.flags?.en || "gb";
	const flagCodeFr = settings.flags?.fr || "fr";
	const baseUrl = flagsUrl ? flagsUrl.replace(/\/$/, "") : "";
	const flagEn = baseUrl ? (
		<img className="omtr-switcher__flag" src={`${baseUrl}/${flagCodeEn}.svg`} alt="" width="24" height="18" />
	) : null;
	const flagFr = baseUrl ? (
		<img className="omtr-switcher__flag" src={`${baseUrl}/${flagCodeFr}.svg`} alt="" width="24" height="18" />
	) : null;
	const style = {
		"--omtr-bg": colors.bg || "#ffffff",
		"--omtr-fg": colors.fg || "#0a0a0a",
		"--omtr-hover": colors.hover || "#f4f4f5",
		"--omtr-border": colors.border || "#e4e4e7",
		"--omtr-radius": `${switcher.radius || 8}px`,
		"--omtr-flag-size": `${switcher.flag_size || 16}px`,
	};

	const englishContent = renderItemContent(display, flagEn, __("English", "omnium-translate"), "EN");
	const frenchContent = renderItemContent(display, flagFr, __("French", "omnium-translate"), "FR");

	return (
		<div
			className={`omtr-switcher notranslate omtr-switcher--${variant} omtr-switcher--${display}`}
			style={style}
			data-omtr-skip
			translate="no"
		>
			{variant === "inline" ? (
				<ul className="omtr-switcher__list">
					<li className="omtr-switcher__item">
						<a className="omtr-switcher__link is-active" href="#preview" lang="en" aria-current="page">
							{englishContent}
							<span className="omtr-switcher__check" aria-hidden="true">
								✓
							</span>
						</a>
					</li>
					<li className="omtr-switcher__item">
						<a className="omtr-switcher__link" href="#preview" lang="fr">
							{frenchContent}
						</a>
					</li>
				</ul>
			) : (
				<details className="omtr-switcher__dropdown" open>
					<summary className="omtr-switcher__trigger" aria-haspopup="true">
						{englishContent}
						<span className="omtr-switcher__chevron" aria-hidden="true" />
					</summary>
					<ul className="omtr-switcher__list">
						<li className="omtr-switcher__item">
							<a className="omtr-switcher__link is-active" href="#preview" lang="en" aria-current="page">
								{englishContent}
								<span className="omtr-switcher__check" aria-hidden="true">
									✓
								</span>
							</a>
						</li>
						<li className="omtr-switcher__item">
							<a className="omtr-switcher__link" href="#preview" lang="fr">
								{frenchContent}
							</a>
						</li>
					</ul>
				</details>
			)}
		</div>
	);
}
