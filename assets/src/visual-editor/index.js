const config = window.omtrVisualEditor;
document.addEventListener("click", (event) => {
	const segment = event.target.closest("[data-omtr-segment]");
	if (!segment || segment.querySelector("textarea")) return;
	event.preventDefault();
	const id = Number(segment.dataset.omtrSegment);
	if (!Number.isSafeInteger(id) || id < 1) return;
	const previous = Array.from(segment.childNodes, (node) => node.cloneNode(true));
	const textarea = document.createElement("textarea");
	textarea.value = segment.textContent;
	const save = document.createElement("button");
	save.type = "button";
	save.textContent = config.save;
	const cancel = document.createElement("button");
	cancel.type = "button";
	cancel.textContent = config.cancel;
	segment.replaceChildren(textarea, save, cancel);
	const restore = () => {
		segment.replaceChildren(...previous);
	};
	cancel.addEventListener("click", (click) => {
		click.stopPropagation();
		restore();
	});
	save.addEventListener("click", async (click) => {
		click.stopPropagation();
		save.disabled = true;
		try {
			const response = await fetch(config.url + id, {
				method: "PUT",
				headers: { "Content-Type": "application/json", "X-WP-Nonce": config.nonce },
				body: JSON.stringify({ target_lang: config.language, text: textarea.value, ...(config.postId > 0 ? { post_id: config.postId } : {}) }),
			});
			if (!response.ok) throw new Error("save failed");
			location.reload();
		} catch {
			save.disabled = false;
		}
	});
	textarea.focus();
});
