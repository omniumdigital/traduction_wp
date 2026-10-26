import { Button, Card, CardBody, Notice } from "@wordpress/components";
import { __, sprintf } from "@wordpress/i18n";
import { useCallback, useEffect, useState } from "@wordpress/element";
import { Loading } from "./common";

const REFRESH_MS = 5000;

export function Dashboard({ request }) {
	const [dashboard, setDashboard] = useState(null);
	const [estimate, setEstimate] = useState(null);
	const [retried, setRetried] = useState(null);
	const refresh = useCallback(async () => {
		try {
			setDashboard(await request({ path: "/dashboard" }));
		} catch (_) {}
	}, [request]);
	useEffect(() => {
		refresh();
	}, [refresh]);
	const busy = Boolean(
		dashboard && !estimate && ((dashboard.queue?.pending || 0) > 0 || dashboard.queue?.running),
	);
	// Live follow-up: poll while a run is outstanding, stop when idle.
	useEffect(() => {
		if (!busy) return undefined;
		const timer = setInterval(refresh, REFRESH_MS);
		return () => clearInterval(timer);
	}, [busy, refresh]);
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
			await refresh();
		} catch (_) {
			setEstimate(null);
		}
	};
	const retryFailed = async () => {
		try {
			const response = await request({ path: "/translations/retry", method: "POST" });
			setRetried(response?.retried || 0);
			await refresh();
		} catch (_) {}
	};
	if (!dashboard) return <Loading />;
	return (
		<div className="omtr-dashboard">
			{busy && (
				<Notice status="info" isDismissible={false} className="omtr-live">
					<span className="omtr-live__dot" aria-hidden="true" />
					{__("Translation in progress…", "omnium-translate")}{" "}
					{sprintf(
						__("%d tasks pending (%d translation batches).", "omnium-translate"),
						dashboard.queue?.pending || 0,
						dashboard.queue?.batches || 0,
					)}
					{dashboard.queue?.last_activity && (
						<> {sprintf(__("Last activity: %s", "omnium-translate"), dashboard.queue.last_activity)}</>
					)}
				</Notice>
			)}
			<div className="omtr-card-grid">
				{(dashboard.languages || []).map((language) => (
					<Card key={language.code}>
						<CardBody>
							<span>{language.code.toUpperCase()}</span>
							<strong className="omtr-stat">{language.coverage}%</strong>
							<small>{__("coverage", "omnium-translate")}</small>
							<div
								className="omtr-progress"
								role="progressbar"
								aria-valuemin={0}
								aria-valuemax={100}
								aria-valuenow={language.coverage}
								aria-label={sprintf(
									__("%s coverage: %d%%", "omnium-translate"),
									language.code.toUpperCase(),
									language.coverage,
								)}
							>
								<div style={{ width: `${Math.max(0, Math.min(100, language.coverage))}%` }} />
							</div>
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
							await refresh();
						}}
					>
						{dashboard.queue?.paused ? __("Resume", "omnium-translate") : __("Pause", "omnium-translate")}
					</Button>
				</CardBody>
			</Card>
			{Boolean(dashboard.segments?.failed) && (
				<Notice
					status="warning"
					isDismissible={false}
					actions={[{ label: __("Retry failed segments", "omnium-translate"), onClick: retryFailed }]}
				>
					{sprintf(__("%d segments failed.", "omnium-translate"), dashboard.segments.failed)}{" "}
					{dashboard.failures?.reason?.message && (
						<> {sprintf(__("Last error: %s", "omnium-translate"), dashboard.failures.reason.message)}</>
					)}{" "}
					{__(
						"Retrying only resends what failed, already translated content is never paid twice.",
						"omnium-translate",
					)}
				</Notice>
			)}
			{retried !== null && (
				<Notice status="success" onDismiss={() => setRetried(null)}>
					{sprintf(__("%d segments queued for retry.", "omnium-translate"), retried)}
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
