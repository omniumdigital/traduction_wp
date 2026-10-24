import apiFetch from "@wordpress/api-fetch";
import { __ } from "@wordpress/i18n";
import { useCallback, useState } from "@wordpress/element";

const initial = window.omtrAdmin || {
	locale: "en_US",
	restNamespace: "omtr/v1",
	wizardDone: false,
};

export const data = initial;

export const route = (path) => `/${initial.restNamespace || "omtr/v1"}${path}`;

export function useApi() {
	const [error, setError] = useState("");
	const request = useCallback(async (options) => {
		setError("");
		try {
			return await apiFetch({ ...options, path: route(options.path) });
		} catch (reason) {
			const message = reason?.message || __("The request could not be completed.", "omnium-translate");
			setError(message);
			throw reason;
		}
	}, []);
	return { error, setError, request };
}

export async function downloadGlossary(request) {
	const response = await request({ path: "/glossary/export", parse: false });
	const blob = await response.blob();
	const url = window.URL.createObjectURL(blob);
	const link = document.createElement("a");
	link.href = url;
	link.download = "omnium-glossary.csv";
	document.body.appendChild(link);
	link.click();
	link.remove();
	window.URL.revokeObjectURL(url);
}

export async function uploadGlossary(request, file) {
	const formData = new FormData();
	formData.append("file", file);
	return request({ path: "/glossary/import", method: "POST", body: formData });
}
