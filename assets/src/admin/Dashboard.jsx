import { Button, Card, CardBody, Notice } from "@wordpress/components";
import { __, sprintf } from "@wordpress/i18n";
import { useEffect, useState } from "@wordpress/element";
import { Loading } from "./common";

export function Dashboard({ request }) {
	const [dashboard, setDashboard] = useState(null);
	const [estimate, setEstimate] = useState(null);
	useEffect(() => {
		request({ path: "/dashboard" })
			.then(setDashboard)
			.catch(() => {});
	}, [request]);
	const translateAll = async () => {
		try {
			setEstimate(await request({ path: "/estimate", method: "POST", data: { all: true } }));
		} catch (_) {}
	};
	const confirmAll = async () => {
		if (!window.confirm(__("Queue the translation of all these pages?", "omnium-translate"))) return;
		try {
			await request({ path: "/translate/all", method: "POST" });
			setEstimate(null);
			setDashboard(await request({ path: "/dashboard" }));
		} catch (_) {
			setEstimate(null);
		}
	};
	if (!dashboard) return <Loading />;
	return (
		<div className="omtr-dashboard">
			<div className="omtr-card-grid">
				{(dashboard.languages || []).map((language) => (
					<Card key={language.code}>
						<CardBody>
							<span>{language.code.toUpperCase()}</span>
							<strong className="omtr-stat">{language.coverage}%</strong>
							<small>{__("coverage", "omnium-translate")}</small>
						</CardBody>
					</Card>
				))}
				<Card>
					<CardBody>
						<span>{__("Words translated this month", "omnium-translate")}</span>
						<strong className="omtr-stat">{dashboard.words?.translated_month || 0}</strong>
						<small>
							{sprintf(
								__("%d words in total since installation", "omnium-translate"),
								dashboard.words?.translated_total || 0,
							)}
						</small>
						<small>
							{dashboard.words?.cap
								? sprintf(__("Monthly cap: %d words", "omnium-translate"), dashboard.words.cap)
								: __("No monthly cap", "omnium-translate")}
						</small>
					</CardBody>
				</Card>
			</div>
			<Card>
				<CardBody>
					<h2>{__("Translation queue", "omnium-translate")}</h2>
					<p>
						{dashboard.queue?.pending || 0} {__("pending", "omnium-translate")} ·{" "}
						{dashboard.queue?.paused ? __("paused", "omnium-translate") : __("running", "omnium-translate")}
						{Boolean(dashboard.segments?.failed) && (
							<> · {sprintf(__("%d failed", "omnium-translate"), dashboard.segments.failed)}</>
						)}
					</p>
					<Button
						onClick={async () => {
							await request({ path: `/queue/${dashboard.queue?.paused ? "resume" : "pause"}`, method: "POST" });
							setDashboard(await request({ path: "/dashboard" }));
						}}
					>
						{dashboard.queue?.paused ? __("Resume", "omnium-translate") : __("Pause", "omnium-translate")}
					</Button>
				</CardBody>
			</Card>
			{Boolean(dashboard.segments?.failed) && (
				<Notice status="warning" isDismissible={false}>
					{sprintf(__("%d segments failed.", "omnium-translate"), dashboard.segments.failed)}{" "}
					{__(
						"“Translate the whole site” will retry them without charging again for already translated content.",
						"omnium-translate",
					)}
				</Notice>
			)}
			<div className="omtr-actions">
				<Button variant="secondary" onClick={() => request({ path: "/crawl", method: "POST" })}>
					{__("Translate new and changed content", "omnium-translate")}
				</Button>
				<Button variant="primary" onClick={translateAll}>
					{__("Translate the whole site", "omnium-translate")}
				</Button>
			</div>
			{estimate && (
				<Notice status="warning" actions={[{ label: __("Confirm", "omnium-translate"), onClick: confirmAll }]}>
					{sprintf(__("%d pages and %d words will be queued.", "omnium-translate"), estimate.pages, estimate.words)}
				</Notice>
			)}
			<Card>
				<CardBody>
					<h2>{__("Recent activity", "omnium-translate")}</h2>
					<table className="widefat">
						<thead>
							<tr>
								<th>{__("Level", "omnium-translate")}</th>
								<th>{__("Context", "omnium-translate")}</th>
								<th>{__("Message", "omnium-translate")}</th>
								<th>{__("Date", "omnium-translate")}</th>
							</tr>
						</thead>
						<tbody>
							{(dashboard.log || []).map((entry, index) => (
								<tr key={`${entry.created_at}-${index}`}>
									<td>{entry.level}</td>
									<td>{entry.context}</td>
									<td>{entry.message}</td>
									<td>{entry.created_at}</td>
								</tr>
							))}
						</tbody>
					</table>
				</CardBody>
			</Card>
		</div>
	);
}
