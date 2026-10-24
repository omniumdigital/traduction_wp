import apiFetch from '@wordpress/api-fetch';
import { Button, Card, CardBody, CheckboxControl, ColorPicker, Notice, Panel, PanelBody, SelectControl, Spinner, TabPanel, TextControl, TextareaControl, ToggleControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { createRoot, useCallback, useEffect, useState } from '@wordpress/element';
import './style.css';

const textDomain = 'omnium-translate';
const initial = window.omtrAdmin || { restNamespace: 'omtr/v1', wizardDone: false, locale: 'en_US' };
const t = (value) => __(value, textDomain);
const route = (path) => `/${initial.restNamespace || 'omtr/v1'}${path}`;

function useApi() {
	const [error, setError] = useState('');
	const request = useCallback(async (options) => {
		setError('');
		try {
			return await apiFetch({ ...options, path: route(options.path) });
		} catch (reason) {
			const message = reason?.message || t('The request could not be completed.');
			setError(message);
			throw reason;
		}
	}, []);
	return { error, setError, request };
}

function Loading() { return <div className="omtr-loading"><Spinner /> <span>{t('Loading…')}</span></div>; }

function ErrorNotice({ error, onDismiss }) {
	if (!error) return null;
	return <Notice status="error" isDismissible={Boolean(onDismiss)} onRemove={onDismiss}>{error}</Notice>;
}

function ProviderPicker({ providers, value, onChange }) {
	const provider = providers.find((item) => item.id === value);
	return <div className="omtr-provider-grid">
		{providers.map((item) => <button type="button" key={item.id} className={`omtr-provider ${item.id === value ? 'is-selected' : ''}`} onClick={() => onChange(item.id)} aria-pressed={item.id === value}>
			<strong>{item.label}</strong>
			<span className="omtr-region">{item.region === 'eu' ? t('EU') : t('Outside the EU')}</span>
			{item.free_tier && <small>{t('Free tier: provider may reuse submitted data.')}</small>}
		</button>)}
		{provider?.free_tier && <Notice status="warning" isDismissible={false}>{t('This free tier may reuse submitted content. Check the provider terms before enabling it.')}</Notice>}
	</div>;
}

function Wizard({ data, request, onComplete }) {
	const [step, setStep] = useState(0);
	const [settings, setSettings] = useState({ source_lang: '', target_langs: [], provider: '', model: '' });
	const [providers, setProviders] = useState([]);
	const [key, setKey] = useState('');
	const [keyStatus, setKeyStatus] = useState(null);
	const [estimate, setEstimate] = useState(null);
	const [insertMenu, setInsertMenu] = useState(true);
	const languages = data.languages || [];
	useEffect(() => { Promise.all([request({ path: '/settings' }), request({ path: '/providers' })]).then(([response, available]) => {
		setSettings(response.settings || {}); setProviders(available || []);
	}).catch(() => {}); }, [request]);
	const updateTargets = (code) => setSettings((current) => ({ ...current, target_langs: current.target_langs?.includes(code) ? current.target_langs.filter((item) => item !== code) : [...(current.target_langs || []), code] }));
	const provider = providers.find((item) => item.id === settings.provider);
	const saveLanguages = async () => { await request({ path: '/settings', method: 'PUT', data: { source_lang: settings.source_lang, target_langs: settings.target_langs } }); setStep(1); };
	const testKey = async () => {
		setKeyStatus(null);
		try { if (key) await request({ path: `/providers/${settings.provider}/key`, method: 'PUT', data: { key } }); const result = await request({ path: `/providers/${settings.provider}/test`, method: 'POST' }); setKeyStatus(result); } catch (reason) { setKeyStatus({ ok: false, message: reason?.message || t('Key test failed.') }); }
	};
	const loadEstimate = async () => { const result = await request({ path: '/estimate', method: 'POST', data: { all: true, languages: settings.target_langs } }); setEstimate(result); setStep(2); };
	const finish = async () => { await request({ path: '/translate/all', method: 'POST', data: { languages: settings.target_langs } }); await request({ path: '/wizard/complete', method: 'POST', data: { insert_menu: insertMenu } }); onComplete(); };
	return <div className="omtr-wizard">
		<fieldset className="omtr-stepper"><legend className="screen-reader-text">{t('Setup progress')}</legend>{[t('Languages'), t('AI provider'), t('Launch')].map((label, index) => <span className={index === step ? 'is-current' : index < step ? 'is-done' : ''} key={label}>{index + 1}. {label}</span>)}</fieldset>
		{step === 0 && <Card><CardBody><h1>{t('Set up your translations')}</h1><p>{t('Choose the source language and the languages you want to publish.')}</p><SelectControl label={t('Source language')} value={settings.source_lang} options={[{ label: t('Select a language'), value: '' }, ...languages.map((item) => ({ label: item.name, value: item.code }))]} onChange={(source_lang) => setSettings((current) => ({ ...current, source_lang }))} />
			<fieldset><legend>{t('Target languages')}</legend>{languages.filter((item) => item.code !== settings.source_lang).map((item) => <CheckboxControl key={item.code} label={item.name} checked={settings.target_langs?.includes(item.code)} onChange={() => updateTargets(item.code)} />)}</fieldset><Button variant="primary" disabled={!settings.source_lang || !settings.target_langs?.length} onClick={saveLanguages}>{t('Continue')}</Button></CardBody></Card>}
		{step === 1 && <Card><CardBody><h1>{t('Connect an AI provider')}</h1><ProviderPicker providers={providers} value={settings.provider} onChange={(providerValue) => { setSettings((current) => ({ ...current, provider: providerValue, model: '' })); setKeyStatus(null); }} />{provider && <><SelectControl label={t('Model')} value={settings.model || provider.models?.find((item) => item.recommended)?.id || ''} options={(provider.models || []).map((item) => ({ label: item.label, value: item.id }))} onChange={(model) => setSettings((current) => ({ ...current, model }))} /><p><a href={provider.key_url} target="_blank" rel="noreferrer">{t('Create an API key')}</a></p><TextControl type="password" label={t('API key')} value={key} onChange={setKey} help={provider.key?.masked ? sprintf(t('Stored key: %s'), provider.key.masked) : t('Your key is encrypted and will never be displayed in full.')} autoComplete="new-password" /><Button variant="secondary" disabled={!key && !provider.key?.set} onClick={testKey}>{t('Test the key')}</Button>{keyStatus && <Notice status={keyStatus.ok ? 'success' : 'error'} isDismissible={false}>{keyStatus.message}</Notice>}</>}<div className="omtr-actions"><Button onClick={() => setStep(0)}>{t('Back')}</Button><Button variant="primary" disabled={!settings.provider || !keyStatus?.ok} onClick={loadEstimate}>{t('Continue')}</Button></div></CardBody></Card>}
		{step === 2 && <Card><CardBody><h1>{t('Review and launch')}</h1>{estimate ? <div className="omtr-estimate"><strong>{estimate.pages} {t('pages')}</strong><span>{estimate.segments} {t('segments')}</span><span>{estimate.words} {t('words')}</span><span>{estimate.cost ? `${estimate.cost} ${estimate.currency}` : t('Cost unavailable')}</span></div> : <Loading />}<CheckboxControl label={t('Add the language selector to the main menu')} checked={insertMenu} onChange={setInsertMenu} /><p>{t('The translation will run in the background. You can pause the queue from the dashboard.')}</p><div className="omtr-actions"><Button onClick={() => setStep(1)}>{t('Back')}</Button><Button variant="primary" disabled={!estimate} onClick={finish}>{t('Translate the site')}</Button></div></CardBody></Card>}
	</div>;
}

function Dashboard({ request }) {
	const [dashboard, setDashboard] = useState(null);
	const [estimate, setEstimate] = useState(null);
	useEffect(() => { request({ path: '/dashboard' }).then(setDashboard).catch(() => {}); }, [request]);
	const translateAll = async () => { const result = await request({ path: '/estimate', method: 'POST', data: { all: true } }); setEstimate(result); };
	const confirmAll = async () => { if (!window.confirm(t('Queue the translation of all these pages?'))) return; await request({ path: '/translate/all', method: 'POST' }); setEstimate(null); setDashboard(await request({ path: '/dashboard' })); };
	if (!dashboard) return <Loading />;
	return <div className="omtr-dashboard"><div className="omtr-card-grid">{(dashboard.languages || []).map((language) => <Card key={language.code}><CardBody><span>{language.code.toUpperCase()}</span><strong className="omtr-stat">{language.coverage}%</strong><small>{t('coverage')}</small></CardBody></Card>)}<Card><CardBody><span>{t('Words this month')}</span><strong className="omtr-stat">{dashboard.words?.used || 0}</strong><small>{dashboard.words?.cap ? sprintf(t('of %d'), dashboard.words.cap) : t('No monthly cap')}</small></CardBody></Card></div><Card><CardBody><h2>{t('Translation queue')}</h2><p>{dashboard.queue?.pending || 0} {t('pending')} · {dashboard.queue?.paused ? t('paused') : t('running')}</p><Button onClick={async () => { await request({ path: `/queue/${dashboard.queue?.paused ? 'resume' : 'pause'}`, method: 'POST' }); setDashboard(await request({ path: '/dashboard' })); }}>{dashboard.queue?.paused ? t('Resume') : t('Pause')}</Button></CardBody></Card><div className="omtr-actions"><Button variant="secondary" onClick={() => request({ path: '/crawl', method: 'POST' })}>{t('Translate new and changed content')}</Button><Button variant="primary" onClick={translateAll}>{t('Translate the whole site')}</Button></div>{estimate && <Notice status="warning" actions={[{ label: t('Confirm'), onClick: confirmAll }]}>{sprintf(t('%d pages and %d words will be queued.'), estimate.pages, estimate.words)}</Notice>}<Card><CardBody><h2>{t('Recent activity')}</h2><table className="widefat"><thead><tr><th>{t('Level')}</th><th>{t('Context')}</th><th>{t('Message')}</th><th>{t('Date')}</th></tr></thead><tbody>{(dashboard.log || []).map((entry, index) => <tr key={`${entry.created_at}-${index}`}><td>{entry.level}</td><td>{entry.context}</td><td>{entry.message}</td><td>{entry.created_at}</td></tr>)}</tbody></table></CardBody></Card></div>;
}

function Segments({ request }) {
	const [items, setItems] = useState([]); const [search, setSearch] = useState(''); const [language, setLanguage] = useState('');
	const load = useCallback(() => request({ path: `/segments?search=${encodeURIComponent(search)}${language ? `&target_lang=${language}` : ''}` }).then(setItems).catch(() => {}), [request, search, language]);
	useEffect(() => { load(); }, [load]);
	return <Card><CardBody><h1>{t('Translations')}</h1><div className="omtr-toolbar"><TextControl label={t('Filter segments')} value={search} onChange={setSearch} hideLabelFromVision /><SelectControl label={t('Language')} value={language} options={[{ label: t('All languages'), value: '' }]} onChange={setLanguage} hideLabelFromVision /></div>{items.map((item) => <SegmentRow key={item.id} item={item} request={request} onSaved={load} />)}</CardBody></Card>;
}
function SegmentRow({ item, request, onSaved }) {
	const [text, setText] = useState(item.text || item.translation || ''); const [busy, setBusy] = useState(false);
	const save = async () => { setBusy(true); await request({ path: `/segments/${item.id}`, method: 'PUT', data: { target_lang: item.target_lang, text } }); setBusy(false); onSaved(); };
	const lock = async () => { setBusy(true); await request({ path: `/segments/${item.id}/lock`, method: item.status === 'locked' ? 'DELETE' : 'POST', data: { target_lang: item.target_lang } }); setBusy(false); onSaved(); };
	return <div className="omtr-segment"><div><small>{item.target_lang} · {item.status}</small><p>{item.source_text}</p></div><TextareaControl label={t('Translation')} value={text} onChange={setText} hideLabelFromVision /><Button disabled={busy} onClick={save}>{t('Save')}</Button><Button disabled={busy} onClick={lock}>{item.status === 'locked' ? t('Unlock') : t('Lock')}</Button></div>;
}

function Glossary({ request }) {
	const [items, setItems] = useState([]); const [form, setForm] = useState({ term: '', target_lang: '', translation: '', rule: 'translate' });
	const load = useCallback(() => request({ path: '/glossary' }).then(setItems).catch(() => {}), [request]); useEffect(() => { load(); }, [load]);
	const add = async () => { await request({ path: '/glossary', method: 'POST', data: form }); setForm({ term: '', target_lang: '', translation: '', rule: 'translate' }); load(); };
	return <Card><CardBody><h1>{t('Glossary')}</h1><div className="omtr-form-row"><TextControl label={t('Term')} value={form.term} onChange={(term) => setForm({ ...form, term })} /><TextControl label={t('Language')} value={form.target_lang} onChange={(target_lang) => setForm({ ...form, target_lang })} /><TextControl label={t('Translation')} value={form.translation} onChange={(translation) => setForm({ ...form, translation })} /><SelectControl label={t('Rule')} value={form.rule} options={[{ label: t('Always translate'), value: 'translate' }, { label: t('Never translate'), value: 'keep' }]} onChange={(rule) => setForm({ ...form, rule })} /><Button variant="primary" onClick={add} disabled={!form.term || !form.target_lang}>{t('Add')}</Button></div><table className="widefat"><thead><tr><th>{t('Term')}</th><th>{t('Language')}</th><th>{t('Translation')}</th><th>{t('Rule')}</th><th /></tr></thead><tbody>{items.map((item) => <tr key={item.id}><td>{item.term}</td><td>{item.target_lang}</td><td>{item.translation}</td><td>{item.rule}</td><td><Button isDestructive onClick={async () => { await request({ path: `/glossary/${item.id}`, method: 'DELETE' }); load(); }}>{t('Delete')}</Button></td></tr>)}</tbody></table></CardBody></Card>;
}

function Contrast({ foreground, background }) {
	const channel = (hex) => { const value = parseInt(hex.slice(1), 16); return [value >> 16, (value >> 8) & 255, value & 255].map((part) => { const c = part / 255; return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4; }); };
	const a = channel(foreground); const b = channel(background); const luminance = (color) => 0.2126 * color[0] + 0.7152 * color[1] + 0.0722 * color[2]; const ratio = (Math.max(luminance(a), luminance(b)) + 0.05) / (Math.min(luminance(a), luminance(b)) + 0.05);
	return <Notice status={ratio < 4.5 ? 'warning' : 'success'} isDismissible={false}>{sprintf(t('Contrast ratio: %s:1'), ratio.toFixed(2))}{ratio < 4.5 && ` — ${t('Use at least 4.5:1 for normal text.')}`}</Notice>;
}

function Settings({ request }) {
	const [data, setData] = useState(null); const [providers, setProviders] = useState([]); const [key, setKey] = useState('');
	useEffect(() => { Promise.all([request({ path: '/settings' }), request({ path: '/providers' })]).then(([settings, available]) => { setData(settings); setProviders(available || []); }).catch(() => {}); }, [request]);
	const save = async (patch) => { const result = await request({ path: '/settings', method: 'PUT', data: patch }); setData(result); };
	if (!data) return <Loading />;
	const settings = data.settings || {}; const switcher = settings.switcher || {}; const colors = switcher.colors || {};
	return <div className="omtr-settings"><Panel><PanelBody title={t('Languages and translation')} initialOpen><TextControl label={t('Source language')} value={settings.source_lang || ''} onChange={(source_lang) => save({ source_lang })} /><TextControl label={t('Target languages (comma separated)')} value={(settings.target_langs || []).join(', ')} onChange={(value) => save({ target_langs: value.split(',').map((item) => item.trim()).filter(Boolean) })} /><ToggleControl label={t('Translate automatically when content changes')} checked={settings.auto_translate} onChange={(auto_translate) => save({ auto_translate })} /><ToggleControl label={t('Translate unknown segments on the fly')} checked={settings.on_the_fly} onChange={(on_the_fly) => save({ on_the_fly })} /></PanelBody><PanelBody title={t('Provider and limits')} initialOpen><SelectControl label={t('Provider')} value={settings.provider || ''} options={providers.map((item) => ({ label: `${item.label} (${item.region === 'eu' ? t('EU') : t('Outside the EU')})`, value: item.id }))} onChange={(provider) => save({ provider })} /><TextControl label={t('Monthly word cap')} type="number" value={settings.monthly_word_cap || 0} onChange={(monthly_word_cap) => save({ monthly_word_cap: Number(monthly_word_cap) })} /><TextControl label={t('Hourly quota')} type="number" value={settings.hourly_quota || 0} onChange={(hourly_quota) => save({ hourly_quota: Number(hourly_quota) })} /><TextControl type="password" label={t('API key')} value={key} onChange={setKey} autoComplete="new-password" help={providers.find((item) => item.id === settings.provider)?.key?.masked || t('The key is never shown in full.')} /><Button onClick={async () => { await request({ path: `/providers/${settings.provider}/key`, method: 'PUT', data: { key } }); setKey(''); }}>{t('Save key')}</Button></PanelBody><PanelBody title={t('Site tone and context')} initialOpen><TextControl label={t('Tone')} value={settings.site_tone || ''} onChange={(site_tone) => setData({ ...data, settings: { ...settings, site_tone } })} onBlur={() => save({ site_tone: settings.site_tone })} /><TextareaControl label={t('Context')} value={settings.site_context || ''} onChange={(site_context) => setData({ ...data, settings: { ...settings, site_context } })} onBlur={() => save({ site_context: settings.site_context })} /></PanelBody><PanelBody title={t('Language selector')} initialOpen><SelectControl label={t('Variant')} value={switcher.variant || 'dropdown'} options={[{ label: t('Dropdown'), value: 'dropdown' }, { label: t('Inline'), value: 'inline' }, { label: t('Floating'), value: 'floating' }]} onChange={(variant) => save({ switcher: { ...switcher, variant } })} /><SelectControl label={t('Display')} value={switcher.display || 'flag-name'} options={[{ label: t('Flag and name'), value: 'flag-name' }, { label: t('Flag only'), value: 'flag' }, { label: t('Name only'), value: 'name' }, { label: t('Language code'), value: 'code' }]} onChange={(display) => save({ switcher: { ...switcher, display } })} /><ToggleControl label={t('Automatic dark mode')} checked={switcher.dark_mode} onChange={(dark_mode) => save({ switcher: { ...switcher, dark_mode } })} /><div className="omtr-colors"><ColorPicker color={colors.bg || '#ffffff'} onChangeComplete={({ hex }) => save({ switcher: { ...switcher, colors: { ...colors, bg: hex } } })} /><ColorPicker color={colors.fg || '#0a0a0a'} onChangeComplete={({ hex }) => save({ switcher: { ...switcher, colors: { ...colors, fg: hex } } })} /></div><TextControl label={t('Corner radius')} type="number" min="0" max="24" value={switcher.radius || 8} onChange={(radius) => save({ switcher: { ...switcher, radius: Number(radius) } })} /><TextControl label={t('Flag size')} type="number" min="12" max="32" value={switcher.flag_size || 18} onChange={(flag_size) => save({ switcher: { ...switcher, flag_size: Number(flag_size) } })} /><Contrast foreground={colors.fg || '#0a0a0a'} background={colors.bg || '#ffffff'} /><div className="omtr-switcher-preview" style={{ '--omtr-bg': colors.bg || '#fff', '--omtr-fg': colors.fg || '#0a0a0a', '--omtr-hover': colors.hover || '#f4f4f5', '--omtr-border': colors.border || '#e4e4e7', '--omtr-radius': `${switcher.radius || 8}px`, '--omtr-flag-size': `${switcher.flag_size || 18}px` }}><button type="button">🇬🇧 English⌄</button><a href="#preview">Français</a></div></PanelBody><PanelBody title={t('Configuration')} initialOpen><div className="omtr-actions"><Button onClick={() => window.open(route('/config/export'), '_blank')}>{t('Export configuration')}</Button><Button onClick={() => document.getElementById('omtr-config-import').click()}>{t('Import configuration')}</Button><input id="omtr-config-import" className="screen-reader-text" type="file" accept="application/json" onChange={async (event) => { const file = event.target.files?.[0]; if (file && file.size <= 1048576) await request({ path: '/config/import', method: 'POST', data: await file.text() }); }} /></div><ToggleControl label={t('Disable update checks')} checked={settings.updates_disabled} onChange={(updates_disabled) => save({ updates_disabled })} /><ToggleControl label={t('Delete all data on uninstall')} checked={settings.uninstall_purge} onChange={(uninstall_purge) => save({ uninstall_purge })} /></PanelBody></Panel></div>;
}

function App() {
	const { error, setError, request } = useApi(); const [wizardDone, setWizardDone] = useState(Boolean(initial.wizardDone)); const [tab, setTab] = useState('dashboard');
	if (!wizardDone) return <><ErrorNotice error={error} onDismiss={() => setError('')} /><Wizard data={initial} request={request} onComplete={() => setWizardDone(true)} /></>;
	const tabs = [{ name: 'dashboard', title: t('Dashboard') }, { name: 'segments', title: t('Translations') }, { name: 'glossary', title: t('Glossary') }, { name: 'settings', title: t('Settings') }];
	return <div className="omtr-admin"><ErrorNotice error={error} onDismiss={() => setError('')} /><TabPanel className="omtr-tabs" tabs={tabs} initialTabName={tab} onSelect={setTab}>{(current) => <div role="tabpanel">{current.name === 'dashboard' && <Dashboard request={request} />}{current.name === 'segments' && <Segments request={request} />}{current.name === 'glossary' && <Glossary request={request} />}{current.name === 'settings' && <Settings request={request} />}</div>}</TabPanel></div>;
}

const root = document.getElementById('omtr-admin');
if (root) createRoot(root).render(<App />);
