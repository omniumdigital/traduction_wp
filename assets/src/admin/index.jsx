import { TabPanel } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { createRoot, useState } from "@wordpress/element";
import "./style.css";
import { data, useApi } from "./api";
import { ErrorNotice } from "./common";
import { Dashboard } from "./Dashboard";
import { Glossary } from "./Glossary";
import { Settings } from "./Settings";
import { Translations } from "./Translations";
import { Wizard } from "./Wizard";

function App() {
	const { error, setError, request } = useApi();
	const [wizardDone, setWizardDone] = useState(Boolean(data.wizardDone));
	const [tab, setTab] = useState("dashboard");
	if (!wizardDone)
		return (
			<>
				<ErrorNotice error={error} onDismiss={() => setError("")} />
				<Wizard data={data} request={request} onComplete={() => setWizardDone(true)} />
			</>
		);
	const tabs = [
		{ name: "dashboard", title: __("Dashboard", "omnium-translate") },
		{ name: "translations", title: __("Translations", "omnium-translate") },
		{ name: "glossary", title: __("Glossary", "omnium-translate") },
		{ name: "settings", title: __("Settings", "omnium-translate") },
	];
	return (
		<div className="omtr-admin">
			<ErrorNotice error={error} onDismiss={() => setError("")} />
			<TabPanel className="omtr-tabs" tabs={tabs} initialTabName={tab} onSelect={setTab}>
				{(current) => (
					<div role="tabpanel">
						{current.name === "dashboard" && <Dashboard request={request} />}
						{current.name === "translations" && <Translations request={request} />}
						{current.name === "glossary" && <Glossary request={request} />}
						{current.name === "settings" && <Settings request={request} />}
					</div>
				)}
			</TabPanel>
		</div>
	);
}

const root = document.getElementById("omtr-admin");
if (root) createRoot(root).render(<App />);
