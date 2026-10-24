export function contrastRatio(foreground, background) {
	const channel = (hex) => {
		const value = parseInt(hex.slice(1), 16);
		return [value >> 16, (value >> 8) & 255, value & 255].map((part) => {
			const color = part / 255;
			return color <= 0.03928 ? color / 12.92 : ((color + 0.055) / 1.055) ** 2.4;
		});
	};
	const luminance = (color) => 0.2126 * color[0] + 0.7152 * color[1] + 0.0722 * color[2];
	const foregroundLuminance = luminance(channel(foreground));
	const backgroundLuminance = luminance(channel(background));
	return (
		(Math.max(foregroundLuminance, backgroundLuminance) + 0.05) /
		(Math.min(foregroundLuminance, backgroundLuminance) + 0.05)
	);
}
